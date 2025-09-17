<?php

namespace App\Filament\Resources\CustomerSubscriptions\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CustomerSubscriptionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('url')
                    ->required(),
                TextInput::make('domain')
                    ->required(),
                TextInput::make('app_name')
                    ->required(),
                Select::make('customer_id')
                    ->relationship('customer', 'company_name')
                    ->required(),
                Select::make('subscription_type_id')
                    ->relationship('subscriptionType', 'name')
                    ->required(),
                TextInput::make('deployed_version')
                    ->maxLength(8)
                    ->nullable(),
                FileUpload::make('logo_1')
                    ->image()
                    ->directory('logos'),
                FileUpload::make('logo_2')
                    ->image()
                    ->directory('logos'),
                FileUpload::make('logo_3')
                    ->image()
                    ->directory('logos'),
                FileUpload::make('logo_4')
                    ->image()
                    ->directory('logos'),
                FileUpload::make('logo_5')
                    ->image()
                    ->directory('logos'),
                TextInput::make('database_name'),
                TextInput::make('forge_site_id'),
                Toggle::make('panic_button_enabled')
                    ->label('Panic Button'),
                Placeholder::make('created_at')
                    ->label('Created Date')
                    ->content(fn($record): string => $record?->created_at?->diffForHumans() ?? '-'),
                Placeholder::make('updated_at')
                    ->label('Last Modified Date')
                    ->content(fn($record): string => $record?->updated_at?->diffForHumans() ?? '-'),
            ]);
    }
}
