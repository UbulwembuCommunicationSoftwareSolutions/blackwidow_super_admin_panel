<?php

namespace App\Filament\Resources\CustomerSubscriptions\Pages;

use App\Filament\Resources\CustomerSubscriptions\CustomerSubscriptionResource;
use App\Jobs\SiteDeployment\DeploySite;
use App\Models\CustomerSubscription;
use App\Models\ForgeServer;
use App\Services\CustomerSubscriptionService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\EditRecord;

class EditCustomerSubscription extends EditRecord
{
    protected static string $resource = CustomerSubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateAppLogos')
                ->label('Generate App Logos')
                ->icon('heroicon-o-photo')
                ->action(fn($record) => CustomerSubscriptionService::generatePWALogos($record->id))
                ->requiresConfirmation()
                ->modalHeading('Generate PWA Logos')
                ->modalDescription('This will generate PWA icons from the uploaded logo. Continue?')
                ->modalSubmitActionLabel('Generate Logos'),
            Action::make('deploySite')
                ->label('Deploy Site')
                ->icon('heroicon-o-rocket-launch')
                ->action(fn ($record) => DeploySite::dispatch($record->id))
                ->requiresConfirmation()
                ->modalHeading('Deploy Site')
                ->modalDescription('This will trigger a site deployment. Continue?')
                ->modalSubmitActionLabel('Deploy'),
            Action::make('backToCustomer')
                ->label('Back to Customer')
                ->icon('heroicon-o-arrow-left')
                ->action(function ($record) {
                    return redirect()->route('filament.admin.resources.customers.edit', [
                        'record' => $record->customer_id,
                    ]);
                }),
            Action::make('EditServerDetails')
                ->label('Edit Server Details')
                ->icon('heroicon-o-server')
                ->form([
                    Select::make('server_id')
                        ->label('Forge Server')
                        ->options(fn() => ForgeServer::pluck('name','forge_server_id'))
                        ->required(),
                ])
                ->fillForm(fn (CustomerSubscription $record): array => [
                    'server_id' => $record->server_id,
                ])
                ->action(function (array $data, CustomerSubscription $record): void {
                    $record->server_id = $data['server_id'];
                    $record->save();
                }),
            DeleteAction::make(),
        ];
    }
}
