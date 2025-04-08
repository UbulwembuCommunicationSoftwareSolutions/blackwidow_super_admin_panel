<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

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

                ToggleColumn::make('panic_button_enabled')
                    ->label('Panic Button')
                    ->visible(fn ($record) => $this->isAppTypeSubscription($record->subscription_type_id))
                    ->disabled(fn ($record) => !$this->isAppTypeSubscription($record->subscription_type_id)),
            ])
            ->filters([
                SelectFilter::make('subscription_type_id')
                    ->label('Subscription Type')
                    ->relationship('subscriptionType', 'name')
                    ->options(SubscriptionType::pluck('name', 'id')),
            ])
            ->headerActions([
                Tables\Actions\Action::make('create')
                    ->label('Create Subscription')
                    ->icon('heroicon-o-plus')
                    ->action(function () {
                        return redirect()->route('filament.admin.resources.customer-subscriptions.create', [
                            'customer' => $this->ownerRecord->id,
                        ]);
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                \Filament\Tables\Actions\Action::make('navigate')
                    ->label('Navigate')
                    ->icon('heroicon-o-arrow-right')
                    ->action(function ($record) {
                        return redirect()->route('filament.admin.resources.customer-subscriptions.edit', $record);
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    private function isAppTypeSubscription($subscriptionTypeId): bool
    {
        // App type subscription IDs: 3 (responder), 4 (reporter), 6 (driver)
        return in_array($subscriptionTypeId, [3, 4, 6]);
    }
}
