<?php

namespace App\Filament\Resources\DeploymentTemplates\Pages;

use App\Filament\Resources\DeploymentTemplates\DeploymentTemplateResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDeploymentTemplate extends EditRecord
{
    protected static string $resource = DeploymentTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
