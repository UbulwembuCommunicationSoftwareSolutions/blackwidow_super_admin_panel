<?php

namespace App\Filament\Resources\ForgeServers\Pages;

use App\Filament\Resources\ForgeServers\ForgeServerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListForgeServers extends ListRecords
{
    protected static string $resource = ForgeServerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
