<?php

namespace App\Filament\Resources\SubscriptionTypeResource\Pages;

use App\Filament\Resources\SubscriptionTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSubscriptionTypes extends ListRecords
{
    protected static string $resource = SubscriptionTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
