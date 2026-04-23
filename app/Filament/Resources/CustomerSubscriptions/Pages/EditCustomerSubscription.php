<?php

namespace App\Filament\Resources\CustomerSubscriptions\Pages;

use App\Filament\Resources\CustomerSubscriptions\CustomerSubscriptionResource;
use App\Jobs\SiteDeployment\CreateSiteOnForgeJob;
use App\Jobs\SiteDeployment\DeploySite;
use App\Models\CustomerSubscription;
use App\Models\ForgeServer;
use App\Services\CustomerSubscriptionService;
use App\Services\ForgeService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Log;

class EditCustomerSubscription extends EditRecord
{
    protected static string $resource = CustomerSubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('recreateSiteOnForge')
                ->label('Re-create site on Forge')
                ->icon('heroicon-o-plus-circle')
                ->visible(
                    fn (CustomerSubscription $record): bool => blank($record->forge_site_id) && filled($record->server_id)
                )
                ->requiresConfirmation()
                ->modalHeading('Create site on Forge')
                ->modalDescription('This will queue a job to create the site on your Forge server. Ensure the server is correct. Continue?')
                ->modalSubmitActionLabel('Queue create site job')
                ->action(function (CustomerSubscription $record) {
                    try {
                        CreateSiteOnForgeJob::dispatch($record->id);
                        Notification::make()
                            ->title('Create site job queued')
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Log::error('Failed to queue create site on Forge', [
                            'customer_subscription_id' => $record->id,
                            'exception' => $e,
                        ]);
                        Notification::make()
                            ->title('Could not queue create site job')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Action::make('generateAppLogos')
                ->label('Generate App Logos')
                ->icon('heroicon-o-photo')
                ->action(fn ($record) => CustomerSubscriptionService::generatePWALogos($record->id))
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
            Action::make('pullEnvFromServer')
                ->label('Pull env from server')
                ->icon('heroicon-o-arrow-down-tray')
                ->visible(fn (CustomerSubscription $record): bool => filled($record->server_id) && filled($record->forge_site_id))
                ->requiresConfirmation()
                ->modalHeading('Pull environment from Forge')
                ->modalDescription('This fetches the site .env from Laravel Forge and updates the stored env copy and env variable rows for this subscription. Existing keys will be overwritten with server values (FORGE_API_KEY is skipped). Continue?')
                ->modalSubmitActionLabel('Pull env')
                ->action(function (CustomerSubscription $record) {
                    try {
                        ForgeService::getSiteEnvironment($record);
                        Notification::make()
                            ->title('Environment pulled from server')
                            ->success()
                            ->send();

                        return redirect()->to(CustomerSubscriptionResource::getUrl('edit', ['record' => $record]));
                    } catch (\Throwable $e) {
                        Log::error('Failed to pull env from Forge', [
                            'customer_subscription_id' => $record->id,
                            'exception' => $e,
                        ]);
                        Notification::make()
                            ->title('Could not pull environment')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
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
                        ->options(fn () => ForgeServer::pluck('name', 'forge_server_id'))
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
