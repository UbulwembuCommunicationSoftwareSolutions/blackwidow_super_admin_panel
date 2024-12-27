<?php

namespace App\Http\Controllers;

use App\Http\Resources\NginxTemplateResource;
use App\Models\NginxTemplate;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class NginxTemplateController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $this->authorize('viewAny', NginxTemplate::class);

        return NginxTemplateResource::collection(NginxTemplate::all());
    }

    public function store(Request $request)
    {
        $this->authorize('create', NginxTemplate::class);

        $data = $request->validate([
            'name' => ['required'],
            'template_id' => ['required', 'integer'],
        ]);

        return new NginxTemplateResource(NginxTemplate::create($data));
    }

    public function show(NginxTemplate $nginxTemplate)
    {
        $this->authorize('view', $nginxTemplate);

        return new NginxTemplateResource($nginxTemplate);
    }

    public function update(Request $request, NginxTemplate $nginxTemplate)
    {
        $this->authorize('update', $nginxTemplate);

        $data = $request->validate([
            'name' => ['required'],
            'template_id' => ['required', 'integer'],
        ]);

        $nginxTemplate->update($data);

        return new NginxTemplateResource($nginxTemplate);
    }

    public function destroy(NginxTemplate $nginxTemplate)
    {
        $this->authorize('delete', $nginxTemplate);

        $nginxTemplate->delete();

        return response()->json();
    }
}
