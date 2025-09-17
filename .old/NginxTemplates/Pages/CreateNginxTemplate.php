<?php

namespace App\Filament\Resources\NginxTemplates\Pages;

use App\Filament\Resources\NginxTemplates\NginxTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateNginxTemplate extends CreateRecord
{
    protected static string $resource = NginxTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
