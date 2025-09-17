<?php

namespace App\Filament\Resources\ForgeServers\Pages;

use App\Filament\Resources\ForgeServers\ForgeServerResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditForgeServer extends EditRecord
{
    protected static string $resource = ForgeServerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
