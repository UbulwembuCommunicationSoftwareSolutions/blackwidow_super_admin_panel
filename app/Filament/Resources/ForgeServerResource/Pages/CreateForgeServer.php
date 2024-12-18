<?php

namespace App\Filament\Resources\ForgeServerResource\Pages;

use App\Filament\Resources\ForgeServerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateForgeServer extends CreateRecord
{
    protected static string $resource = ForgeServerResource::class;

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
