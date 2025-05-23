<?php

namespace App\Filament\Resources\CustomerUserResource\Pages;

use App\Filament\Resources\CustomerUserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditCustomerUser extends EditRecord
{
    protected static string $resource = CustomerUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
    }
}
