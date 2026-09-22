<?php

namespace App\Filament\Resources\CustomerSubscriptions\Tables;

use App\Models\CustomerSubscription;
use App\Models\SubscriptionTypeRelease;
use App\Services\SiteDeploymentScheduler;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class CustomerSubscriptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('url')
                    ->searchable(),
                TextColumn::make('subscriptionType.name')
                    ->searchable(),
                TextColumn::make('target_release')
                    ->label('Target release')
                    ->getStateUsing(fn (CustomerSubscription $record): ?string => $record->targetRelease()?->tag),
                TextColumn::make('deployedRelease.tag')
                    ->label('Deployed release')
                    ->placeholder(fn (CustomerSubscription $record): string => $record->deployed_tag_raw ?: '—'),
                TextColumn::make('release_status')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(fn (CustomerSubscription $record): string => $record->releaseStatus())
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'up_to_date' => 'Up to date',
                        'outdated' => 'Outdated',
                        'pinned' => 'Pinned',
                        default => 'Unknown',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'up_to_date' => 'success',
                        'outdated' => 'warning',
                        'pinned' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('deployed_confirmed_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('logo_1')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('logo_2')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('logo_3')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('logo_4')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('logo_5')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('customer.id')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('forge_site_id')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('server_id')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('app_name')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('database_name')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('database_user')
                    ->label('DB user')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('site_created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('github_sent_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('env_sent_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deployment_script_sent_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('ssl_deployed_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deployed_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('domain')
                    ->searchable()
                    ->toggleable(),
                IconColumn::make('panic_button_enabled')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('subscription_type_id')
                    ->label('Subscription type')
                    ->relationship('subscriptionType', 'name')
                    ->searchable()
                    ->preload(),
                Filter::make('outdated')
                    ->label('Outdated only')
                    ->query(fn (Builder $query): Builder => $query->whereOutdated()),
                SelectFilter::make('deployed_release_id')
                    ->label('Deployed release')
                    ->relationship('deployedRelease', 'tag')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('upgradeNow')
                    ->label('Upgrade now')
                    ->icon('heroicon-o-arrow-up-circle')
                    ->requiresConfirmation()
                    ->action(function (CustomerSubscription $record): void {
                        try {
                            $batchId = app(SiteDeploymentScheduler::class)->scheduleUpgrade($record, force: true);
                            Notification::make()
                                ->title('Upgrade queued')
                                ->body('Batch '.$batchId)
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Log::error('filament.upgrade_now.failed', [
                                'customer_subscription_id' => $record->id,
                                'message' => $e->getMessage(),
                            ]);
                            Notification::make()
                                ->title('Could not queue upgrade')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('pinRelease')
                    ->label('Pin to release…')
                    ->icon('heroicon-o-map-pin')
                    ->form([
                        Select::make('pinned_release_id')
                            ->label('Release')
                            ->options(fn (CustomerSubscription $record): array => SubscriptionTypeRelease::query()
                                ->where('subscription_type_id', $record->subscription_type_id)
                                ->published()
                                ->orderByDesc('published_at')
                                ->pluck('tag', 'id')
                                ->all())
                            ->required()
                            ->searchable(),
                    ])
                    ->action(function (CustomerSubscription $record, array $data): void {
                        $record->forceFill(['pinned_release_id' => $data['pinned_release_id']])->save();
                        Notification::make()->title('Release pinned')->success()->send();
                    }),
                Action::make('unpinRelease')
                    ->label('Unpin')
                    ->icon('heroicon-o-x-mark')
                    ->visible(fn (CustomerSubscription $record): bool => filled($record->pinned_release_id))
                    ->requiresConfirmation()
                    ->action(function (CustomerSubscription $record): void {
                        $record->forceFill(['pinned_release_id' => null])->save();
                        Notification::make()->title('Pin removed')->success()->send();
                    }),
                Action::make('rollBack')
                    ->label('Roll back')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->visible(fn (CustomerSubscription $record): bool => filled($record->deployed_release_id))
                    ->requiresConfirmation()
                    ->modalHeading('Roll back to currently deployed release')
                    ->modalDescription(function (CustomerSubscription $record): string {
                        $tag = $record->deployedRelease?->tag ?? $record->deployed_tag_raw ?? 'unknown';
                        $warning = '';
                        if ($record->deployedRelease?->requires_manual_rollback) {
                            $warning = ' WARNING: this release is flagged as requiring manual rollback (destructive migrations).';
                        }

                        return "This pins the site to {$tag} and queues an upgrade (redeploy of that tag).{$warning}";
                    })
                    ->action(function (CustomerSubscription $record): void {
                        if (! $record->deployed_release_id) {
                            return;
                        }
                        $record->forceFill(['pinned_release_id' => $record->deployed_release_id])->save();
                        try {
                            $batchId = app(SiteDeploymentScheduler::class)->scheduleUpgrade($record, force: true);
                            Notification::make()
                                ->title('Rollback upgrade queued')
                                ->body('Pinned to deployed release and queued batch '.$batchId)
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Pinned, but upgrade failed to queue')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('upgradeSelected')
                        ->label('Upgrade selected to target')
                        ->icon('heroicon-o-rocket-launch')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $scheduler = app(SiteDeploymentScheduler::class);
                            $queued = 0;
                            foreach ($records as $record) {
                                try {
                                    $scheduler->scheduleUpgrade($record, force: true);
                                    $queued++;
                                } catch (\Throwable $e) {
                                    Log::warning('filament.bulk_upgrade.skip', [
                                        'customer_subscription_id' => $record->id,
                                        'message' => $e->getMessage(),
                                    ]);
                                }
                            }
                            Notification::make()
                                ->title("Queued {$queued} upgrade(s)")
                                ->success()
                                ->send();
                        }),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
