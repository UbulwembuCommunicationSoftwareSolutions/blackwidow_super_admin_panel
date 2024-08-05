<?php

namespace App\Filament\Resources\RequiredEnvVariablesResource\Pages;

use App\Filament\Resources\RequiredEnvVariablesResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRequiredEnvVariables extends ListRecords
{
    protected static string $resource = RequiredEnvVariablesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
