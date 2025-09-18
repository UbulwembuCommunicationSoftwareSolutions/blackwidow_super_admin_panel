<?php

namespace App\Filament\Resources\Customers\Schemas;

use App\Models\Customer;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class CustomerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('company_name'),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (Customer $record): bool => $record->trashed()),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('token')
                    ->placeholder('-'),
                TextEntry::make('docket_description'),
                TextEntry::make('task_description'),
                TextEntry::make('level_one_description'),
                TextEntry::make('level_two_description'),
                TextEntry::make('level_three_description'),
                TextEntry::make('level_four_description'),
                TextEntry::make('level_five_description'),
                IconEntry::make('level_one_in_use')
                    ->boolean(),
                IconEntry::make('level_two_in_use')
                    ->boolean(),
                IconEntry::make('level_three_in_use')
                    ->boolean(),
                TextEntry::make('max_users')
                    ->numeric(),
                TextEntry::make('uuid')
                    ->label('UUID')
                    ->placeholder('-'),
            ]);
    }
}
