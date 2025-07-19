<?php

namespace App\Http\Controllers;

use App\Http\Resources\CustomerUserResource;
use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\CustomerUser;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\Fluent\Concerns\Has;
use Log;

class CustomerUserController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $url = $request->get('app_url');
        $customerSubscription = CustomerSubscription::where('url', $url)->first();
        $users = CustomerUser::where('customer_id',$customerSubscription->customer_id)->get();
        return CustomerUserResource::collection($users);
    }

    public function login(Request $request){
        $input = $request->all();
        Log::info("Login request: " . json_encode($input));
        if($request->has('email')){
            $email = $request->get('email');
        }else{
            $email = null;
        }
        if($request->has('cellphone')){
            $cellphone = $request->get('cellphone');
        }else{
            $cellphone = null;
        }
        $password = $request->get('password');
        $url = $request->get('app_url');
        $customerSubscription = CustomerSubscription::where('url', $url)->first();
        if(!$customerSubscription){
            \Log::info("Customer Subscription not found");
            return response()->json(['message' => 'Invalid credentials'], 401);
        }
        \Log::info('Customer Subscription Found: ' . $customerSubscription->url);
        $customerUser = null;
        if($email){
            $customerUser = CustomerUser::where('customer_id',$customerSubscription->customer_id)->where('email_address', $email)->first();
        }
        if($cellphone){
            $customerUser = CustomerUser::where('customer_id',$customerSubscription->customer_id)->where('cellphone', $cellphone)->first();
        }
        if (!$customerUser){
            \Log::info("Customer User not found");
            return response()->json(['message' => 'Invalid credentials'], 401);
        }
        if($customerUser){
            if(!$this->checkAccess($customerUser,$customerSubscription)){
                Log::info("Access Denied for user: ".$customerUser->email_address);
                return response()->json(
                    [
                        'message' => 'Access Denied',
                        'customer_user' => $customerUser,
                    ], 401
                );
            }
        }
        \Log::info('Stored hash: ' . $customerUser->password);
        \Log::info('Entered password: ' . $request->password);
        \Log::info('Hash Check: ' . (Hash::check($request->password, $customerUser->password) ? 'Match' : 'No Match'));
        if(!\Hash::check($request->password, $customerUser->password)) {
            return response()->json(
                [
                    'debug' => $request->password. ' is not equal to '.$customerUser->password,
                    'message' => 'Invalid credentials',
                    'customer_user' => $customerUser,
                ], 401
            );
        }


//        if(!$this->checkAccess($customerUser,$customerSubscription)){
//            return response()->json(
//                [
//                    'message' => 'Access Denied',
//                    'customer_user' => $customerUser,
//                ], 401
//            );
//        }

        // Create a new Sanctum token
        $token = $customerUser->createToken('customer-user-token')->plainTextToken;

        $customerSubscriptions = CustomerSubscription::where('customer_id', $customerUser->customer_id)->get();

        return response()->json([
            'message' => 'Login successful',
            'user' => $customerUser,
            'token' => $token,
        ]);

    }

    public function checkAccess(CustomerUser $user,CustomerSubscription $subscription){
        \Log::info("Checking if user ".$user->cellphone." has access to subscription ".$subscription->url);
        if((int)$subscription->subscription_type_id == 1){
            if($user->console_access){
                return true;
            }else{
                return false;
            }
        }
        if((int)$subscription->subscription_type_id == 2){
            if($user->firearm_access){
                return true;
            }else{
                return false;
            }
        }
        if((int)$subscription->subscription_type_id == 3){
            if($user->responder_access){
                return true;
            }else{
                return false;
            }
        }
        if((int)$subscription->subscription_type_id == 4){
            if($user->reporter_access){
                return true;
            }else{
                return false;
            }
        }
        if((int)$subscription->subscription_type_id == 5){
            if($user->security_access){
                return true;
            }else{
                return false;
            }
        }
        if((int)$subscription->subscription_type_id == 6){
            if($user->driver_access){
                return true;
            }else{
                return false;
            }
        }
        if((int)$subscription->subscription_type_id == 7){
            if($user->survey_access){
                return true;
            }else{
                return false;
            }
        }
        if((int)$subscription->subscription_type_id == 9){
            if($user->time_and_attendance_access){
                return true;
            }else{
                return false;
            }
        }
        if((int)$subscription->subscription_type_id == 10){
            if($user->stock_access){
                return true;
            }else{
                return false;
            }
        }
        return false;
    }

    public function store(Request $request)
    {
        \Log::info(json_encode($request->all()));
        $customerSub = CustomerSubscription::where('url', $request->app_url)->first();
        $customer = Customer::find($customerSub->customer_id);
        $data = $request->all();
        $name = $data['user']['name'];
        $surname = $data['user']['surname'];
        $email = $data['user']['email'];
        $cellphone = $data['user']['cellphone'];
        $password = $data['password'];
        $user = CustomerUser::firstOrCreate([
            'customer_id' => $customer->id,
            'email_address' => $email,
        ],
        [
            'first_name' => $name,
            'last_name' => $surname,
            'cellphone' => $cellphone,
            'password' => $password,
            'console_access' => true,
            'firearm_access' => false,
            'responder_access' => false,
            'reporter_access' => true,
        ]);
        $user->customer_id = $customer->id;
        $user->save();
        return new CustomerUserResource($user);
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

    public function updatePassword(Request $request){
        $email = $request->email;
        $customerSub = CustomerSubscription::where('url', $request->app_url)->first();
        \Log::info('Password update for Customer: ' . $request->app_url);
        $customer = $customerSub->customer;
        if($customer){
            \Log::info('Customer Found: ' . $customer->company_name);
        }
        \Log::info("Received ".$request->password);
        $customerUser = CustomerUser::where('email_address', $email)
            ->where('customer_id', $customer->id)
            ->first();
        \Log::info('User found: '.$customerUser->id);
        \Log::info('Old password hash: ' . $customerUser->password);
        $customerUser->password = $request->password;
        $customerUser->save();
        \Log::info('New password hash: ' . $customerUser->password);
        \Log::info('Password updated for user: ' . $email);
        return response()->json(['message' => 'Password updated successfully to '.$request->password]);
    }

    public function deactivateUser(Request $request){
        $email = $request->email;
        $customerSub = CustomerSubscription::where('url', $request->app_url)->first();
        $customer = $customerSub->customer;
        if($customer){
            \Log::info('Customer Found: ' . $customer->company_name);
        }
        $customerUser = CustomerUser::where('email_address', $email)
            ->where('customer_id', $customer->id)
            ->first();
        $customerUser->console_access = false;
        $customerUser->firearm_access = false;
        $customerUser->responder_access = false;
        $customerUser->reporter_access = false;
        $customerUser->save();
        return response()->json(['message' => 'User Deactivated Successfully']);
    }

    public function activateUser(Request $request){
        $email = $request->email;
        $customerSub = CustomerSubscription::where('url', $request->app_url)->first();
        $customer = $customerSub->customer;
        if($customer){
            \Log::info('Customer Found: ' . $customer->company_name);
        }
        $customerUser = CustomerUser::where('email_address', $email)
            ->where('customer_id', $customer->id)
            ->first();
        $customerUser->console_access = true;
        $customerUser->firearm_access = true;
        $customerUser->responder_access = true;
        $customerUser->reporter_access = true;
        $customerUser->save();
        return response()->json(['message' => 'User Activated Successfully']);
    }

}
