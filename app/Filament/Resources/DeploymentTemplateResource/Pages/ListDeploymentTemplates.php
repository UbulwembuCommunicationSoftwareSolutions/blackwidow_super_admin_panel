<?php

namespace App\Filament\Resources\DeploymentTemplateResource\Pages;

use App\Filament\Resources\DeploymentTemplateResource;
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
