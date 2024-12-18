<?php

namespace App\Http\Controllers;

use App\Http\Resources\ForgeServerResource;
use App\Models\ForgeServer;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class ForgeServerController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $this->authorize('viewAny', ForgeServer::class);

        return ForgeServerResource::collection(ForgeServer::all());
    }

    public function store(Request $request)
    {
        $this->authorize('create', ForgeServer::class);

        $data = $request->validate([
            'forge_server_id' => ['required', 'integer'],
            'name' => ['nullable'],
            'ip_address' => ['nullable'],
        ]);

        return new ForgeServerResource(ForgeServer::create($data));
    }

    public function show(ForgeServer $forgeServer)
    {
        $this->authorize('view', $forgeServer);

        return new ForgeServerResource($forgeServer);
    }

    public function update(Request $request, ForgeServer $forgeServer)
    {
        $this->authorize('update', $forgeServer);

        $data = $request->validate([
            'forge_server_id' => ['required', 'integer'],
            'name' => ['nullable'],
            'ip_address' => ['nullable'],
        ]);

        $forgeServer->update($data);

        return new ForgeServerResource($forgeServer);
    }

    public function destroy(ForgeServer $forgeServer)
    {
        $this->authorize('delete', $forgeServer);

        $forgeServer->delete();

        return response()->json();
    }
}
