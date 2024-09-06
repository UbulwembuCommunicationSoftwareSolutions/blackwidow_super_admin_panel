<?php

namespace App\Filament\Resources\DeploymentScriptResource\Pages;

use App\Filament\Resources\DeploymentScriptResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDeploymentScripts extends ListRecords
{
    protected static string $resource = DeploymentScriptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
