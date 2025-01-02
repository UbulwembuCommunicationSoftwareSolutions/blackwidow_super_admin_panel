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
            ])
            ->filters([
                SelectFilter::make('subscription_type_id')
                    ->label('Subscription Type')
                    ->relationship('subscriptionType', 'name') // Assuming 'subscriptionType' is the relationship method name
                    ->options(SubscriptionType::pluck('name', 'id')),
            ])
            ->headerActions([
                Tables\Actions\Action::make('create')
                    ->label('Create Subscription')
                    ->icon('heroicon-o-plus')
                    ->action(function () {
                        // Redirect to the custom create page
                        return redirect()->route('filament.resources.customer-subscriptions.create', [
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
}
