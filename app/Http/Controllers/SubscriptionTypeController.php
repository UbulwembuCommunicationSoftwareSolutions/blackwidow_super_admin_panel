<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubscriptionTypeRequest;
use App\Http\Resources\SubscriptionTypeResource;
use App\Models\SubscriptionType;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class SubscriptionTypeController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $this->authorize('viewAny', SubscriptionType::class);

        return SubscriptionTypeResource::collection(SubscriptionType::all());
    }

    public function store(SubscriptionTypeRequest $request)
    {
        $this->authorize('create', SubscriptionType::class);

        return new SubscriptionTypeResource(SubscriptionType::create($request->validated()));
    }

    public function show(SubscriptionType $subscriptionType)
    {
        $this->authorize('view', $subscriptionType);

        return new SubscriptionTypeResource($subscriptionType);
    }

    public function update(SubscriptionTypeRequest $request, SubscriptionType $subscriptionType)
    {
        $this->authorize('update', $subscriptionType);

        $subscriptionType->update($request->validated());

        return new SubscriptionTypeResource($subscriptionType);
    }

    public function destroy(SubscriptionType $subscriptionType)
    {
        $this->authorize('delete', $subscriptionType);

        $subscriptionType->delete();

        return response()->json();
    }
}
