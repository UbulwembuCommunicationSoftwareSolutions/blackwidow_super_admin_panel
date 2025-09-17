<?php

namespace App\Filament\Resources\CustomerSubscriptionResource\Pages;

use App\Filament\Resources\CustomerSubscriptionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use pxlrbt\FilamentExcel\Actions\Tables\ExportAction;
class ListCustomerSubscriptions extends ListRecords
{
    protected static string $resource = CustomerSubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            ExportAction::make('table')->fromTable(),
        ];
    }


}
