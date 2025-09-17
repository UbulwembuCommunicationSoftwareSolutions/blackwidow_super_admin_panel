<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Models\SubscriptionType;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CustomerSubscriptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'customerSubscriptions';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('url')
            ->columns([
                TextColumn::make('subscriptionType.name')
                    ->label('Subscription Type')
                    ->sortable(),

                TextColumn::make('url')
                    ->label('URL')
                    ->searchable(),

                TextColumn::make('deployed_version')
                    ->label('Deployed Version')
                    ->searchable()
                    ->sortable(),

                ToggleColumn::make('panic_button_enabled')
                    ->label('Panic Button')
                    ->disabled(fn ($record) => !$record || !$this->isAppTypeSubscription($record->subscription_type_id)),
            ])
            ->filters([
                SelectFilter::make('subscription_type_id')
                    ->label('Subscription Type')
                    ->relationship('subscriptionType', 'name')
                    ->options(SubscriptionType::pluck('name', 'id')),
            ])
            ->headerActions([
                Action::make('create')
                    ->label('Create Subscription')
                    ->icon('heroicon-o-plus')
                    ->action(function () {
                        return redirect()->route('filament.admin.resources.customer-subscriptions.create', [
                            'customer' => $this->ownerRecord->id,
                        ]);
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('navigate')
                    ->label('Navigate')
                    ->icon('heroicon-o-arrow-right')
                    ->action(function ($record) {
                        return redirect()->route('filament.admin.resources.customer-subscriptions.edit', $record);
                    }),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    private function isAppTypeSubscription($subscriptionTypeId): bool
    {
        // App type subscription IDs: 3 (responder), 4 (reporter), 5 (security), 6 (driver), 7 (survey)
        return in_array($subscriptionTypeId, [3, 4, 5, 6, 7]);
    }
}
