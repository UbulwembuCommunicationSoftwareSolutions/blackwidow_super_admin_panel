<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserCustomerResource;
use App\Models\UserCustomer;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class UserCustomerController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $this->authorize('viewAny', UserCustomer::class);

        return UserCustomerResource::collection(UserCustomer::all());
    }

    public function store(Request $request)
    {
        $this->authorize('create', UserCustomer::class);

        $data = $request->validate([
            'user_id' => ['required', 'exists:users'],
            'customer_id' => ['required', 'exists:customers'],
        ]);

        return new UserCustomerResource(UserCustomer::create($data));
    }

    public function show(UserCustomer $userCustomer)
    {
        $this->authorize('view', $userCustomer);

        return new UserCustomerResource($userCustomer);
    }

    public function update(Request $request, UserCustomer $userCustomer)
    {
        $this->authorize('update', $userCustomer);

        $data = $request->validate([
            'user_id' => ['required', 'exists:users'],
            'customer_id' => ['required', 'exists:customers'],
        ]);

        $userCustomer->update($data);

        return new UserCustomerResource($userCustomer);
    }

    public function destroy(UserCustomer $userCustomer)
    {
        $this->authorize('delete', $userCustomer);

        $userCustomer->delete();

        return response()->json();
    }
}
