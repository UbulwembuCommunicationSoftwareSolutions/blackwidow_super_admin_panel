<?php

namespace App\Filament\Resources\NginxTemplates\Pages;

use App\Filament\Resources\NginxTemplates\NginxTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListNginxTemplates extends ListRecords
{
    protected static string $resource = NginxTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
