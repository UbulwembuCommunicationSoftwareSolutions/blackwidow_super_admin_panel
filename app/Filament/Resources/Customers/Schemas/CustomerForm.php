<?php

namespace App\Filament\Resources\Customers\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('company_name')
                    ->required(),
                TextInput::make('token'),
                TextInput::make('docket_description')
                    ->required()
                    ->default('Docket'),
                TextInput::make('task_description')
                    ->required()
                    ->default('Task'),
                TextInput::make('level_one_description')
                    ->required()
                    ->default('Level 1'),
                TextInput::make('level_two_description')
                    ->required()
                    ->default('Level 2'),
                TextInput::make('level_three_description')
                    ->required()
                    ->default('Level 3'),
                TextInput::make('level_four_description')
                    ->required()
                    ->default('Level 4'),
                TextInput::make('level_five_description')
                    ->required()
                    ->default('Level 5'),
                Toggle::make('level_one_in_use')
                    ->required(),
                Toggle::make('level_two_in_use')
                    ->required(),
                Toggle::make('level_three_in_use')
                    ->required(),
                TextInput::make('max_users')
                    ->required()
                    ->numeric()
                    ->default(1),
                TextInput::make('uuid')
                    ->label('UUID'),
            ]);
    }
}
