<?php

namespace App\Filament\Resources\RequiredEnvVariables\Schemas;

use App\Models\TemplateEnvVariables;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class RequiredEnvVariablesForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('key')
                    ->required(),
                Toggle::make('requires_manual_fill')
                    ->label('Requires manual fill per subscription')
                    ->helperText('When enabled, the value is left empty on each customer subscription until an operator fills it (shown under Customer Subscription → Manual environment variables).')
                    ->default(false)
                    ->live(),
                TextInput::make('value')
                    ->label('Default template value')
                    ->helperText('Not used when "Requires manual fill" is on; optional in that case.')
                    ->required(fn ($get) => ! $get('requires_manual_fill')),
                TextInput::make('admin_label')
                    ->label('Admin label')
                    ->maxLength(255)
                    ->nullable()
                    ->helperText('Friendly label in the subscription manual-env table; defaults to the key.'),
                Textarea::make('help_text')
                    ->label('Help text')
                    ->rows(2)
                    ->nullable()
                    ->columnSpanFull(),
                Select::make('subscription_type_id')
                    ->relationship('subscriptionType', 'name')
                    ->required(),
                Placeholder::make('created_at')
                    ->label('Created Date')
                    ->content(fn (?TemplateEnvVariables $record): string => $record?->created_at?->diffForHumans() ?? '-'),
                Placeholder::make('updated_at')
                    ->label('Last Modified Date')
                    ->content(fn (?TemplateEnvVariables $record): string => $record?->updated_at?->diffForHumans() ?? '-'),
            ]);
    }
}
