<?php

namespace App\Filament\Resources\RequiredEnvVariables\Pages;

use App\Filament\Resources\RequiredEnvVariables\RequiredEnvVariablesResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRequiredEnvVariables extends EditRecord
{
    protected static string $resource = RequiredEnvVariablesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
