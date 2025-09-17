<?php

namespace App\Filament\Resources\RequiredEnvVariables\Pages;

use App\Filament\Exports\EnvVariableExporter;
use App\Filament\Resources\RequiredEnvVariables\RequiredEnvVariablesResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Actions\ExportAction;

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
