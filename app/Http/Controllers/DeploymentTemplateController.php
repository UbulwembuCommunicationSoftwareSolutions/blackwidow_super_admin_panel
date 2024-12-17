<?php

namespace App\Http\Controllers;

use App\Http\Resources\DeploymentTemplateResource;
use App\Models\DeploymentTemplate;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class DeploymentTemplateController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $this->authorize('viewAny', DeploymentTemplate::class);

        return DeploymentTemplateResource::collection(DeploymentTemplate::all());
    }

    public function store(Request $request)
    {
        $this->authorize('create', DeploymentTemplate::class);

        $data = $request->validate([
            'script' => ['required'],
            'subscription_type_id' => ['required', 'exists:subscription_types'],
        ]);

        return new DeploymentTemplateResource(DeploymentTemplate::create($data));
    }

    public function show(DeploymentTemplate $deploymentTemplate)
    {
        $this->authorize('view', $deploymentTemplate);

        return new DeploymentTemplateResource($deploymentTemplate);
    }

    public function update(Request $request, DeploymentTemplate $deploymentTemplate)
    {
        $this->authorize('update', $deploymentTemplate);

        $data = $request->validate([
            'script' => ['required'],
            'subscription_type_id' => ['required', 'exists:subscription_types'],
        ]);

        $deploymentTemplate->update($data);

        return new DeploymentTemplateResource($deploymentTemplate);
    }

    public function destroy(DeploymentTemplate $deploymentTemplate)
    {
        $this->authorize('delete', $deploymentTemplate);

        $deploymentTemplate->delete();

        return response()->json();
    }
}
