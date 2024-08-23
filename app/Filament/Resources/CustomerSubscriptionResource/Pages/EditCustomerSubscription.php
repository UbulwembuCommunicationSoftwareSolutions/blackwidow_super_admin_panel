<?php

namespace App\Filament\Resources\CustomerSubscriptionResource\Pages;

use App\Filament\Resources\CustomerSubscriptionResource;
use App\Jobs\DeploySite;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCustomerSubscription extends EditRecord
{
    protected static string $resource = CustomerSubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            Action::make('deploySite')
                ->label('Deploy Site')
                ->message('Are you sure you want to deploy this site?')
                ->confirm('Yes, deploy it')
                ->danger()
                ->beforePerform(function () {
                    DeploySite::dispatch($this->record->id);
                }),
        ];
    }
}
