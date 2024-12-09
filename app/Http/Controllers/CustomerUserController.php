<?php

namespace App\Http\Controllers;

use App\Http\Resources\CustomerUserResource;
use App\Models\CustomerSubscription;
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
        $email = $request->get('email');
        $password = $request->get('password');
        $url = $request->get('app_url');
        $customerSubscription = CustomerSubscription::where('app_url', $url)->first();
        $customerUser = CustomerUser::where('customer_id',$customerSubscription->customer_id)->where('email_address', $email)->first();
        if (!$customerUser || !\Hash::check($request->password, $customerUser->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        // Create a new Sanctum token
        $token = $customerUser->createToken('customer-user-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
        ]);

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
