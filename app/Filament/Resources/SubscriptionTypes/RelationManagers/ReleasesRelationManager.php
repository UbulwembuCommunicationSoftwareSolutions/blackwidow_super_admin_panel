<?php

namespace App\Filament\Resources\SubscriptionTypes\RelationManagers;

use App\Jobs\RedeployOutdatedSitesJob;
use App\Jobs\SyncGithubReleasesJob;
use App\Models\CustomerSubscription;
use App\Models\SubscriptionType;
use App\Models\SubscriptionTypeRelease;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ReleasesRelationManager extends RelationManager
{
    protected static string $relationship = 'releases';

    protected static ?string $title = 'Releases';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('tag')
            ->modifyQueryUsing(fn (Builder $query) => $query->orderByDesc('published_at')->orderByDesc('id'))
            ->columns([
                TextColumn::make('tag')
                    ->searchable()
                    ->sortable()
                    ->description(fn (SubscriptionTypeRelease $record): ?string => $record->name),
                TextColumn::make('published_at')
                    ->dateTime()
                    ->sortable(),
                IconColumn::make('is_prerelease')
                    ->label('Pre')
                    ->boolean(),
                IconColumn::make('is_draft')
                    ->label('Draft / gone')
                    ->boolean(),
                TextColumn::make('is_current')
                    ->label('Current')
                    ->badge()
                    ->getStateUsing(function (SubscriptionTypeRelease $record): string {
                        /** @var SubscriptionType $owner */
                        $owner = $this->getOwnerRecord();

                        return (int) $owner->current_release_id === (int) $record->id ? 'current' : '';
                    })
                    ->color('success'),
                TextColumn::make('sites_on_release')
                    ->label('Sites')
                    ->getStateUsing(fn (SubscriptionTypeRelease $record): int => CustomerSubscription::query()
                        ->where('deployed_release_id', $record->id)
                        ->count()),
                TextColumn::make('body')
                    ->label('Notes')
                    ->limit(80)
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->wrap(),
            ])
            ->headerActions([
                Action::make('syncFromGithub')
                    ->label('Sync from GitHub now')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function (): void {
                        /** @var SubscriptionType $owner */
                        $owner = $this->getOwnerRecord();
                        SyncGithubReleasesJob::dispatchSync((int) $owner->id);
                        Notification::make()
                            ->title('Releases synced from GitHub')
                            ->success()
                            ->send();
                    }),
                Action::make('upgradeAllOutdated')
                    ->label('Upgrade all outdated sites')
                    ->icon('heroicon-o-rocket-launch')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Upgrade all outdated sites of this type')
                    ->modalDescription(function (): string {
                        /** @var SubscriptionType $owner */
                        $owner = $this->getOwnerRecord();
                        $count = CustomerSubscription::query()
                            ->where('subscription_type_id', $owner->id)
                            ->whereOutdated()
                            ->count();

                        return "This will queue upgrade batches for {$count} outdated site(s). Continue?";
                    })
                    ->action(function (): void {
                        /** @var SubscriptionType $owner */
                        $owner = $this->getOwnerRecord();
                        RedeployOutdatedSitesJob::dispatch((int) $owner->id);
                        Notification::make()
                            ->title('Outdated site upgrades queued')
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                Action::make('setAsCurrent')
                    ->label('Set as current')
                    ->icon('heroicon-o-check-circle')
                    ->requiresConfirmation()
                    ->modalHeading('Set as current release')
                    ->modalDescription(function (SubscriptionTypeRelease $record): string {
                        /** @var SubscriptionType $owner */
                        $owner = $this->getOwnerRecord();
                        $outdated = CustomerSubscription::query()
                            ->where('subscription_type_id', $owner->id)
                            ->whereNull('pinned_release_id')
                            ->where(function (Builder $q) use ($record): void {
                                $q->whereNull('deployed_release_id')
                                    ->orWhere('deployed_release_id', '!=', $record->id);
                            })
                            ->count();

                        return "Sites without a pin will target {$record->tag}. Approximately {$outdated} site(s) will become outdated. Continue?";
                    })
                    ->visible(function (SubscriptionTypeRelease $record): bool {
                        /** @var SubscriptionType $owner */
                        $owner = $this->getOwnerRecord();

                        return ! $record->is_draft && (int) $owner->current_release_id !== (int) $record->id;
                    })
                    ->action(function (SubscriptionTypeRelease $record): void {
                        /** @var SubscriptionType $owner */
                        $owner = $this->getOwnerRecord();
                        $owner->forceFill([
                            'current_release_id' => $record->id,
                            'master_version' => $record->tag,
                        ])->save();

                        Notification::make()
                            ->title("Current release set to {$record->tag}")
                            ->success()
                            ->send();
                    }),
            ])
            ->paginated([10, 25, 50]);
    }

    public function canCreate(): bool
    {
        return false;
    }

    public function canEdit(Model $record): bool
    {
        return false;
    }

    public function canDelete(Model $record): bool
    {
        return false;
    }
}
