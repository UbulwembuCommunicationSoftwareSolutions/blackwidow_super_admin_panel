<?php

namespace App\Filament\Resources\CustomerSubscriptions\RelationManagers;

use App\Filament\Resources\CustomerSubscriptions\CustomerSubscriptionResource;
use App\Models\CustomerSubscription;
use App\Models\EnvVariables;
use App\Models\RequiredEnvVariables;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class AllEnvVariablesRelationManager extends RelationManager
{
    protected static string $relationship = 'envVariables';

    protected static ?string $title = 'All environment variables';

    protected static string $resource = CustomerSubscriptionResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('key')
                    ->disabled()
                    ->dehydrated(true),
                TextInput::make('value')
                    ->label('Value')
                    ->nullable()
                    ->maxLength(65535)
                    ->hint(function (?EnvVariables $record) {
                        if ($record === null || ! $this->ownerRecord instanceof CustomerSubscription || ! $this->ownerRecord->subscription_type_id) {
                            return null;
                        }
                        $req = RequiredEnvVariables::query()
                            ->where('subscription_type_id', $this->ownerRecord->subscription_type_id)
                            ->where('key', $record->key)
                            ->first();

                        return $req?->help_text;
                    }),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')
                    ->label('Key')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('value')
                    ->label('Value')
                    ->limit(60)
                    ->placeholder('—'),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('key')
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public function canCreate(): bool
    {
        return false;
    }

    public function canDelete(Model $record): bool
    {
        return false;
    }
}
