<?php

namespace App\Filament\Resources\DeploymentScriptResource\Pages;

use App\Filament\Resources\DeploymentScriptResource;
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
