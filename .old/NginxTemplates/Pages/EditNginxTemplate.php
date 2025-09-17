<?php

namespace App\Filament\Resources\NginxTemplates\Pages;

use App\Filament\Resources\NginxTemplates\NginxTemplateResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditNginxTemplate extends EditRecord
{
    protected static string $resource = NginxTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
