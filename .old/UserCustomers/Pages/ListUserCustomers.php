<?php

namespace App\Filament\Resources\UserCustomers\Pages;

use App\Filament\Resources\UserCustomers\UserCustomerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUserCustomers extends ListRecords
{
    protected static string $resource = UserCustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
