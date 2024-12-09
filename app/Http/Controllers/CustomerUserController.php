<?php

namespace App\Http\Controllers;

use App\Http\Resources\CustomerUserResource;
use App\Models\CustomerUser;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class CustomerUserController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $this->authorize('viewAny', CustomerUser::class);

        return CustomerUserResource::collection(CustomerUser::all());
    }

    public function login(Request $request){
        $input = $request->all();
        dd($input);
    }

    public function store(Request $request)
    {
        $this->authorize('create', CustomerUser::class);

        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers'],
            'email_address' => ['required'],
            'password' => ['required'],
            'first_name' => ['required'],
            'last_name' => ['required'],
        ]);

        return new CustomerUserResource(CustomerUser::create($data));
    }

    public function show(CustomerUser $customerUser)
    {
        $this->authorize('view', $customerUser);

        return new CustomerUserResource($customerUser);
    }

    public function update(Request $request, CustomerUser $customerUser)
    {
        $this->authorize('update', $customerUser);

        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers'],
            'email_address' => ['required'],
            'password' => ['required'],
            'first_name' => ['required'],
            'last_name' => ['required'],
        ]);

        $customerUser->update($data);

        return new CustomerUserResource($customerUser);
    }

    public function destroy(CustomerUser $customerUser)
    {
        $this->authorize('delete', $customerUser);

        $customerUser->delete();

        return response()->json();
    }
}
