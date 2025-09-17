<?php

namespace App\Filament\Resources\DeploymentTemplates\Pages;

use App\Filament\Resources\DeploymentTemplates\DeploymentTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDeploymentTemplate extends CreateRecord
{
    protected static string $resource = DeploymentTemplateResource::class;
}
