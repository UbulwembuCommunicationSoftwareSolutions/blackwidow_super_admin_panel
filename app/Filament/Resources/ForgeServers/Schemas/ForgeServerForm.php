<?php

namespace App\Filament\Resources\ForgeServers\Schemas;

use App\Models\ForgeServer;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ForgeServerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('forge_server_id')
                    ->required()
                    ->integer(),
                TextInput::make('name'),
                TextInput::make('ip_address'),
                Placeholder::make('created_at')
                    ->label('Created Date')
                    ->content(fn(?ForgeServer $record): string => $record?->created_at?->diffForHumans() ?? '-'),
                Placeholder::make('updated_at')
                    ->label('Last Modified Date')
                    ->content(fn(?ForgeServer $record): string => $record?->updated_at?->diffForHumans() ?? '-'),
            ]);
    }
}
