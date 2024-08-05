<?php

namespace App\Filament\Resources\CustomerSubscriptionResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EnvVariablesRelationManager extends RelationManager
{
    protected static string $relationship = 'envVariables';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('key')
                    ->required(),

                TextInput::make('value')
                    ->required(),

                Select::make('customer_subscription_id')
                    ->label('Subscription')
                    ->searchable()
                    ->relationship('customerSubscription', 'url') // Specify the relationship and the display column
                    ->required()
                    ->default(fn () => $this->ownerRecord->id),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('key')
            ->columns([
                Tables\Columns\TextColumn::make('key')
                ->sortable()
                ->searchable(),
                Tables\Columns\TextColumn::make('value')
            ])
            ->filters([
                Tables\Filters\Filter::make('is_null')
                    ->query(fn (Builder $query): Builder => $query->where('value', null))
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
