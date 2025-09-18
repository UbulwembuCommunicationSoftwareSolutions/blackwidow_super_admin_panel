<?php

namespace App\Filament\Resources\Customers\Schemas;

use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Company Details')
                    ->schema([
                        TextInput::make('company_name')
                            ->required(),
                        TextInput::make('token'),
                        TextInput::make('docket_description')
                            ->required()
                            ->default('Docket'),
                        TextInput::make('task_description')
                            ->required()
                            ->default('Task'),
                        TextInput::make('max_users')
                            ->numeric()
                            ->minValue(1)
                            ->default(1),
                    ]),
                Section::make('Level Configuration')
                    ->schema([
                        Toggle::make('level_one_in_use')
                            ->live()
                            ->reactive(),
                        TextInput::make('level_one_description')
                            ->hidden(fn($get) => $get('level_one_in_use') === false)
                            ->default('Level One')
                            ->required(),
                        Toggle::make('level_two_in_use')
                            ->live()
                            ->reactive(),
                        TextInput::make('level_two_description')
                            ->hidden(fn($get) => $get('level_two_in_use') === false)
                            ->default('Level Two')
                            ->required(),
                        Toggle::make('level_three_in_use')
                            ->live()
                            ->reactive(),
                        TextInput::make('level_three_description')
                            ->hidden(fn($get) => $get('level_three_in_use') === false)
                            ->default('Level Three')
                            ->required(),
                        TextInput::make('level_four_description')
                            ->required()
                            ->default('Level Four'),
                        TextInput::make('level_five_description')
                            ->required()
                            ->default('Level Five'),
                    ])
                    ->columns(1),
            ]);
    }
}
