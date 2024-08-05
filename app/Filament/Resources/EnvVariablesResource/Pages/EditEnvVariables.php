<?php

namespace App\Filament\Resources\EnvVariablesResource\Pages;

use App\Filament\Resources\EnvVariablesResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEnvVariables extends EditRecord
{
    protected static string $resource = EnvVariablesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
