<?php

namespace App\Filament\Resources\UserCustomerResource\Pages;

use App\Filament\Resources\UserCustomerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUserCustomer extends CreateRecord
{
    protected static string $resource = UserCustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
