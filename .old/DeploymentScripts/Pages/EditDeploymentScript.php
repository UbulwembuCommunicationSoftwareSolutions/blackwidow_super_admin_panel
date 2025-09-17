<?php

namespace App\Filament\Resources\DeploymentScripts\Pages;

use App\Filament\Resources\DeploymentScripts\DeploymentScriptResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDeploymentScript extends EditRecord
{
    protected static string $resource = DeploymentScriptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
