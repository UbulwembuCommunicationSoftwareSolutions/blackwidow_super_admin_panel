<?php

namespace App\Filament\Resources\DeploymentScripts\Pages;

use App\Filament\Resources\DeploymentScripts\DeploymentScriptResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDeploymentScript extends CreateRecord
{
    protected static string $resource = DeploymentScriptResource::class;

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
