<?php

namespace App\Filament\Resources\SubscriptionTypes\Schemas;

use App\Models\SubscriptionType;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
                Select::make('current_release_id')
                    ->label('Current release')
                    ->relationship(
                        name: 'currentRelease',
                        titleAttribute: 'tag',
                        modifyQueryUsing: fn ($query) => $query->published()->orderByDesc('published_at'),
                    )
                    ->searchable()
                    ->preload()
                    ->nullable(),
                TextInput::make('master_version')
                    ->label('Master version (legacy mirror)')
                    ->maxLength(64)
                    ->disabled()
                    ->dehydrated(false)
                    ->nullable(),
                Toggle::make('auto_promote_stable')
                    ->label('Auto-promote stable releases')
                    ->helperText('When enabled, syncing a new non-prerelease release sets it as current automatically.')
                    ->default(false),
                Placeholder::make('created_at')
                    ->label('Created Date')
                    ->content(fn (?SubscriptionType $record): string => $record?->created_at?->diffForHumans() ?? '-'),
                Placeholder::make('updated_at')
                    ->label('Last Modified Date')
                    ->content(fn (?SubscriptionType $record): string => $record?->updated_at?->diffForHumans() ?? '-'),
            ]);
    }
}
