<?php

namespace App\Filament\Resources\EnvVariables\Pages;

use App\Filament\Resources\EnvVariables\EnvVariablesResource;
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
