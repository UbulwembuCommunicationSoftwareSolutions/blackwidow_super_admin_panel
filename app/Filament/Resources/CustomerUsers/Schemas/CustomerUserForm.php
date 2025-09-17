<?php

namespace App\Filament\Resources\CustomerUsers\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CustomerUserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('customer_id')
                    ->relationship('customer', 'id')
                    ->required(),
                TextInput::make('email_address')
                    ->email()
                    ->required(),
                TextInput::make('password')
                    ->password()
                    ->required(),
                TextInput::make('first_name')
                    ->required(),
                TextInput::make('last_name')
                    ->required(),
                Toggle::make('console_access')
                    ->required(),
                Toggle::make('firearm_access')
                    ->required(),
                Toggle::make('responder_access')
                    ->required(),
                Toggle::make('reporter_access')
                    ->required(),
                Toggle::make('security_access')
                    ->required(),
                Toggle::make('driver_access')
                    ->required(),
                Toggle::make('survey_access')
                    ->required(),
                Toggle::make('time_and_attendance_access')
                    ->required(),
                Toggle::make('stock_access')
                    ->required(),
                TextInput::make('cellphone')
                    ->tel(),
                Toggle::make('is_system_admin')
                    ->required(),
            ]);
    }
}
