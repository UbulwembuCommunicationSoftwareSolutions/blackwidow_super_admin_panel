<?php

namespace App\Filament\Resources\NginxTemplateResource\Pages;

use App\Filament\Resources\NginxTemplateResource;
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
