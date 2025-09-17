<?php

namespace App\Filament\Resources\DeploymentScripts\Schemas;

use App\Models\DeploymentScript;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DeploymentScriptForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('script')
                    ->required(),
                TextInput::make('customer_subscription_id')
                    ->required()
                    ->integer(),
                Placeholder::make('created_at')
                    ->label('Created Date')
                    ->content(fn(?DeploymentScript $record): string => $record?->created_at?->diffForHumans() ?? '-'),
                Placeholder::make('updated_at')
                    ->label('Last Modified Date')
                    ->content(fn(?DeploymentScript $record): string => $record?->updated_at?->diffForHumans() ?? '-'),
            ]);
    }
}
