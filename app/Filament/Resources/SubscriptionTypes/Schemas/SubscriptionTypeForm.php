<?php

namespace App\Filament\Resources\SubscriptionTypes\Schemas;

use App\Models\SubscriptionType;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SubscriptionTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('github_repo')
                    ->required(),
                TextInput::make('branch')
                    ->required(),
                TextInput::make('project_type')
                    ->required(),
                TextInput::make('master_version')
                    ->maxLength(8)
                    ->nullable(),
                Placeholder::make('created_at')
                    ->label('Created Date')
                    ->content(fn(?SubscriptionType $record): string => $record?->created_at?->diffForHumans() ?? '-'),
                Placeholder::make('updated_at')
                    ->label('Last Modified Date')
                    ->content(fn(?SubscriptionType $record): string => $record?->updated_at?->diffForHumans() ?? '-'),
            ]);
    }
}
