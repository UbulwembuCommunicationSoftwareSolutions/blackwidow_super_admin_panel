<?php

namespace App\Filament\Resources\DeploymentTemplateResource\Pages;

use App\Filament\Resources\DeploymentTemplateResource;
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
