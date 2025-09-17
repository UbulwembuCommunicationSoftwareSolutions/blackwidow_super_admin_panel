<?php

namespace App\Filament\Resources\NginxTemplates\Schemas;

use App\Models\ForgeServer;
use App\Models\NginxTemplate;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class NginxTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                Select::make('server_id')
                    ->required()
                    ->options(fn() => ForgeServer::pluck('name', 'forge_server_id')),
                TextInput::make('template_id')
                    ->required()
                    ->integer(),
                Placeholder::make('created_at')
                    ->label('Created Date')
                    ->content(fn(?NginxTemplate $record): string => $record?->created_at?->diffForHumans() ?? '-'),
                Placeholder::make('updated_at')
                    ->label('Last Modified Date')
                    ->content(fn(?NginxTemplate $record): string => $record?->updated_at?->diffForHumans() ?? '-'),
            ]);
    }
}
