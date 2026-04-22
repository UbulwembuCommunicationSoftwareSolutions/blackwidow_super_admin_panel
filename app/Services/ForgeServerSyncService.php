<?php

namespace App\Services;

use App\Helpers\ForgeApi;
use App\Models\ForgeServer;
use Laravel\Forge\Resources\Server;
use Throwable;

class ForgeServerSyncService
{
    /**
     * Fetch all servers the Forge API key can access and upsert them into my_forge_servers.
     *
     * @return int Number of servers processed
     *
     * @throws Throwable
     */
    public static function syncFromApi(): int
    {
        $forgeApi = new ForgeApi();
        $servers = $forgeApi->getServers();

        $count = 0;
        foreach ($servers as $server) {
            if (! $server instanceof Server) {
                continue;
            }

            ForgeServer::query()->updateOrCreate(
                ['forge_server_id' => (int) $server->id],
                [
                    'name' => $server->name !== null && $server->name !== '' ? (string) $server->name : null,
                    'ip_address' => isset($server->ipAddress) && $server->ipAddress !== '' ? (string) $server->ipAddress : null,
                ],
            );
            $count++;
        }

        return $count;
    }
}
