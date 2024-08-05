<?php

namespace App\Filament\Resources\RequiredEnvVariablesResource\Pages;

use App\Filament\Resources\RequiredEnvVariablesResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRequiredEnvVariables extends CreateRecord
{
    protected static string $resource = RequiredEnvVariablesResource::class;

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
