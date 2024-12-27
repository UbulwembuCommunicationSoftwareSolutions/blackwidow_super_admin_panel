<?php

namespace App\Filament\Resources\NginxTemplateResource\Pages;

use App\Filament\Resources\NginxTemplateResource;
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
