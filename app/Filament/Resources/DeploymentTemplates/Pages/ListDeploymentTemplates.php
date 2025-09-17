<?php

namespace App\Filament\Resources\DeploymentTemplates\Pages;

use App\Filament\Resources\DeploymentTemplates\DeploymentTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDeploymentTemplates extends ListRecords
{
    protected static string $resource = DeploymentTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
