<?php

namespace App\Filament\Resources\CustomerUsers\Pages;

use App\Filament\Resources\CustomerUsers\CustomerUserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCustomerUsers extends ListRecords
{
    protected static string $resource = CustomerUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
