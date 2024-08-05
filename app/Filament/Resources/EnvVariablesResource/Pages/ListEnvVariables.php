<?php

namespace App\Filament\Resources\EnvVariablesResource\Pages;

use App\Filament\Resources\EnvVariablesResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEnvVariables extends ListRecords
{
    protected static string $resource = EnvVariablesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
