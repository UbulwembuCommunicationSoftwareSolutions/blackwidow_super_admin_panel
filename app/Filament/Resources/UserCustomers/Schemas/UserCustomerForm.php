<?php

namespace App\Filament\Resources\UserCustomers\Schemas;

use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class UserCustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required(),
                Select::make('customer_id')
                    ->relationship('customer', 'id')
                    ->required(),
            ]);
    }
}
