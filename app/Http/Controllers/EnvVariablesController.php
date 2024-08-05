<?php

namespace App\Http\Controllers;

use App\Http\Requests\EnvVariablesRequest;
use App\Http\Resources\EnvVariablesResource;
use App\Models\EnvVariables;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class EnvVariablesController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $this->authorize('viewAny', EnvVariables::class);

        return EnvVariablesResource::collection(EnvVariables::all());
    }

    public function store(EnvVariablesRequest $request)
    {
        $this->authorize('create', EnvVariables::class);

        return new EnvVariablesResource(EnvVariables::create($request->validated()));
    }

    public function show(EnvVariables $envVariables)
    {
        $this->authorize('view', $envVariables);

        return new EnvVariablesResource($envVariables);
    }

    public function update(EnvVariablesRequest $request, EnvVariables $envVariables)
    {
        $this->authorize('update', $envVariables);

        $envVariables->update($request->validated());

        return new EnvVariablesResource($envVariables);
    }

    public function destroy(EnvVariables $envVariables)
    {
        $this->authorize('delete', $envVariables);

        $envVariables->delete();

        return response()->json();
    }
}
