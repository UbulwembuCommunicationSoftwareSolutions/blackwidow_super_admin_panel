<?php

namespace App\Filament\Resources\EnvVariablesResource\Pages;

use App\Filament\Resources\EnvVariablesResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEnvVariables extends CreateRecord
{
    protected static string $resource = EnvVariablesResource::class;

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
