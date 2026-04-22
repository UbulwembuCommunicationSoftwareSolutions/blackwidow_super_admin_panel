<?php

namespace App\Filament\Resources\ForgeServers\Pages;

use App\Filament\Resources\ForgeServers\ForgeServerResource;
use App\Services\ForgeServerSyncService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Log;
use Throwable;

class ListForgeServers extends ListRecords
{
    protected static string $resource = ForgeServerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncFromForge')
                ->label('Sync from Forge')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Sync servers from Laravel Forge')
                ->modalDescription('Fetches all servers your Forge API key can access and creates or updates rows in the database. Existing rows are matched by Forge server ID.')
                ->modalSubmitActionLabel('Sync now')
                ->action(function () {
                    try {
                        $count = ForgeServerSyncService::syncFromApi();
                        Notification::make()
                            ->title('Forge servers synced')
                            ->body($count === 1 ? '1 server was saved.' : "{$count} servers were saved.")
                            ->success()
                            ->send();
                    } catch (Throwable $e) {
                        Log::error('Failed to sync Forge servers from API', ['exception' => $e]);
                        Notification::make()
                            ->title('Could not sync servers')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            CreateAction::make(),
        ];
    }
}
