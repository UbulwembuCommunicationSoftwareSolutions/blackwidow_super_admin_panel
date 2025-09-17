<?php

namespace App\Filament\Resources\RequiredEnvVariables\Schemas;

use App\Models\RequiredEnvVariables;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class RequiredEnvVariablesForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('key')
                    ->required(),
                TextInput::make('value')
                    ->required(),
                Select::make('subscription_type_id')
                    ->relationship('subscriptionType', 'name')
                    ->required(),
                Placeholder::make('created_at')
                    ->label('Created Date')
                    ->content(fn(?RequiredEnvVariables $record): string => $record?->created_at?->diffForHumans() ?? '-'),
                Placeholder::make('updated_at')
                    ->label('Last Modified Date')
                    ->content(fn(?RequiredEnvVariables $record): string => $record?->updated_at?->diffForHumans() ?? '-'),
            ]);
    }
}
