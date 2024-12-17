<?php

namespace App\Filament\Resources\DeploymentTemplateResource\Pages;

use App\Filament\Resources\DeploymentTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDeploymentTemplate extends CreateRecord
{
    protected static string $resource = DeploymentTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
