<?php

namespace App\Filament\Resources\CustomerUsers\Pages;

use App\Filament\Resources\CustomerUsers\CustomerUserResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditCustomerUser extends EditRecord
{
    protected static string $resource = CustomerUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
