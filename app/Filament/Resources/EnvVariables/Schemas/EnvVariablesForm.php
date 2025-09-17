<?php

namespace App\Filament\Resources\EnvVariables\Schemas;

use App\Models\EnvVariables;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class EnvVariablesForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('key')
                    ->required(),
                TextInput::make('value')
                    ->required(),
                TextInput::make('customer_subscription_id')
                    ->required()
                    ->integer(),
                Placeholder::make('created_at')
                    ->label('Created Date')
                    ->content(fn(?EnvVariables $record): string => $record?->created_at?->diffForHumans() ?? '-'),
                Placeholder::make('updated_at')
                    ->label('Last Modified Date')
                    ->content(fn(?EnvVariables $record): string => $record?->updated_at?->diffForHumans() ?? '-'),
            ]);
    }
}
