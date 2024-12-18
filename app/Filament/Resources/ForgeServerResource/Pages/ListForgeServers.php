<?php

namespace App\Filament\Resources\ForgeServerResource\Pages;

use App\Filament\Resources\ForgeServerResource;
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
