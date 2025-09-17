<?php

namespace App\Filament\Resources\UserCustomers\Pages;

use App\Filament\Resources\UserCustomers\UserCustomerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUserCustomer extends CreateRecord
{
    protected static string $resource = UserCustomerResource::class;
}
