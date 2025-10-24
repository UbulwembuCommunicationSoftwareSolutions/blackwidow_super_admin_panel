<?php

namespace App\Http\Controllers;

use App\Http\Resources\CustomerUserResource;
use App\Jobs\StartUserSyncJob;
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

    /**
     * Clean app_url by removing protocol and normalize for comparison
     */
    private function cleanAppUrl(string $appUrl): string
    {
        // Remove http:// and https:// protocols
        $cleaned = preg_replace('/^https?:\/\//', '', $appUrl);
        $cleaned = preg_replace('/^http?:\/\//', '', $appUrl);
        
        // Remove trailing slash if present
        $cleaned = rtrim($cleaned, '/');
        
        return $cleaned;
    }

    /**
     * Find customer subscription by cleaned app_url using LIKE comparison
     */
    private function findCustomerSubscriptionByUrl(string $appUrl): ?CustomerSubscription
    {
        $cleanedUrl = $this->cleanAppUrl($appUrl);
        
        return CustomerSubscription::where('url', 'LIKE', '%' . $cleanedUrl . '%')->first();
    }

    public function index(Request $request)
    {
        $url = $request->get('app_url');
        $customerSubscription = $this->findCustomerSubscriptionByUrl($url);
        
        if (!$customerSubscription) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid app URL',
            ], 400);
        }
        
        $users = CustomerUser::where('customer_id', $customerSubscription->customer_id)->get();
        
        // Return in the format expected by CMS
        $userData = $users->map(function ($user) {
            return [
                'id' => $user->id, // SuperAdmin user ID
                'email_address' => $user->email_address,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'cellphone' => $user->cellphone,
                'password' => $user->password, // Hashed password
                'console_access' => $user->console_access ? 1 : 0,
                'firearm_access' => $user->firearm_access ? 1 : 0,
                'responder_access' => $user->responder_access ? 1 : 0,
                'reporter_access' => $user->reporter_access ? 1 : 0,
                'security_access' => $user->security_access ? 1 : 0,
                'driver_access' => $user->driver_access ? 1 : 0,
                'survey_access' => $user->survey_access ? 1 : 0,
                'time_and_attendance_access' => $user->time_and_attendance_access ? 1 : 0,
                'stock_access' => $user->stock_access ? 1 : 0,
                'is_system_admin' => $user->is_system_admin ? 1 : 0,
                'created_at' => $user->created_at->toISOString(),
                'updated_at' => $user->updated_at->toISOString(), // Critical for conflict resolution
            ];
        });
        
        return response()->json([
            'success' => true,
            'data' => $userData->toArray()
        ]);
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
        //IF URL IS HTTP, REPLACE WITH HTTPS
        if(strpos($url, 'http://') === 0){
            $url = str_replace('http://', 'https://', $url);
        }
        $customerSubscription = CustomerSubscription::where('url', $url)->first();
        if(!$customerSubscription){
            Log::info("Customer Subscription not found");
            return response()->json(['message' => 'Invalid credentials'], 401);
        }
        Log::info('Customer Subscription Found: ' . $customerSubscription->url);
        $customerUser = null;
        if($email){
            $customerUser = CustomerUser::where('customer_id',$customerSubscription->customer_id)->where('email_address', $email)->first();
        }
        if($cellphone){
            $customerUser = CustomerUser::where('customer_id',$customerSubscription->customer_id)->where('cellphone', $cellphone)->first();
        }
        if (!$customerUser){
            Log::info("Customer User not found");
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
        Log::info('Stored hash: ' . $customerUser->password);
        Log::info('Entered password: ' . $request->password);
        Log::info('Hash Check: ' . (Hash::check($request->password, $customerUser->password) ? 'Match' : 'No Match'));
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
        Log::info("Checking if user ".$user->cellphone." has access to subscription ".$subscription->url);
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

    public function setAccess(CustomerUser $user, CustomerSubscription $subscription){
        Log::info("Setting access for user ".$user->cellphone." to subscription ".$subscription->url);

        switch((int)$subscription->subscription_type_id) {
            case 1:
                $user->console_access = true;
                break;
            case 2:
                $user->firearm_access = true;
                break;
            case 3:
                $user->responder_access = true;
                break;
            case 4:
                $user->reporter_access = true;
                break;
            case 5:
                $user->security_access = true;
                break;
            case 6:
                $user->driver_access = true;
                break;
            case 7:
                $user->survey_access = true;
                break;
            case 9:
                $user->time_and_attendance_access = true;
                break;
            case 10:
                $user->stock_access = true;
                break;
            default:
                Log::warning("Unknown subscription type ID: " . $subscription->subscription_type_id);
                return false;
        }

        $user->save();
        Log::info("Access granted for user " . $user->email_address . " to subscription type " . $subscription->subscription_type_id);
        return true;
    }

    public function store(Request $request)
    {
        Log::info(json_encode($request->all()));
        
        // Validate the request
        $validated = $request->validate([
            'app_url' => 'required|string',
            'subscription_id' => 'required|string',
            'password' => 'required|string',
            'user' => 'required|array',
            'user.first_name' => 'required|string',
            'user.last_name' => 'nullable|string',
            'user.email' => 'required|email',
            'user.cellphone' => 'nullable|string',
            'user.active' => 'nullable|boolean',
            'user.console_access' => 'nullable|boolean',
            'user.firearm_access' => 'nullable|boolean',
            'user.responder_access' => 'nullable|boolean',
            'user.reporter_access' => 'nullable|boolean',
            'user.security_access' => 'nullable|boolean',
            'user.driver_access' => 'nullable|boolean',
            'user.survey_access' => 'nullable|boolean',
            'user.time_and_attendance_access' => 'nullable|boolean',
            'user.stock_access' => 'nullable|boolean',
            'user.is_system_admin' => 'nullable|boolean',
        ]);

        $customerSub = CustomerSubscription::where('uuid', $validated['subscription_id'])->first();
        if (!$customerSub) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid subscription ID',
            ], 400);
        }

        $customer = Customer::find($customerSub->customer_id);
        $data = $validated;
        
        $name = $data['user']['first_name'];
        $surname = $data['user']['last_name'] ?? null;
        $email = $data['user']['email'];
        $cellphone = $data['user']['cellphone'] ?? null;
        $password = $data['password'];
        
        // Check if user already exists
        $existingUser = CustomerUser::where('customer_id', $customer->id)
            ->where('email_address', $email)
            ->first();
            
        if ($existingUser) {
            return response()->json([
                'success' => false,
                'message' => 'Email already exists',
                'errors' => [
                    'email' => ['The email has already been taken.']
                ]
            ], 422);
        }

        $user = CustomerUser::create([
            'customer_id' => $customer->id,
            'first_name' => $name,
            'last_name' => $surname,
            'email_address' => $email,
            'cellphone' => $cellphone,
            'password' => $password, // Cleartext - model will hash it automatically
            'console_access' => $data['user']['console_access'] ?? ($data['user']['active'] ?? true),
            'firearm_access' => $data['user']['firearm_access'] ?? false,
            'responder_access' => $data['user']['responder_access'] ?? false,
            'reporter_access' => $data['user']['reporter_access'] ?? false,
            'security_access' => $data['user']['security_access'] ?? false,
            'driver_access' => $data['user']['driver_access'] ?? false,
            'survey_access' => $data['user']['survey_access'] ?? false,
            'time_and_attendance_access' => $data['user']['time_and_attendance_access'] ?? false,
            'stock_access' => $data['user']['stock_access'] ?? false,
            'is_system_admin' => $data['user']['is_system_admin'] ?? false,
        ]);
        
        $this->setAccess($user, $customerSub);
        $user->save();
        
        // Trigger user import to customer subscriptions
        StartUserSyncJob::dispatch($customer->id);
        
        return response()->json([
            'success' => true,
            'message' => 'User created successfully',
            'user' => [
                'id' => $user->id, // This is the super_admin_user_id
                'email_address' => $user->email_address,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'cellphone' => $user->cellphone,
                'password' => $user->password, // CRITICAL: Return hashed password
                'console_access' => $user->console_access ? 1 : 0,
                'firearm_access' => $user->firearm_access ? 1 : 0,
                'responder_access' => $user->responder_access ? 1 : 0,
                'reporter_access' => $user->reporter_access ? 1 : 0,
                'security_access' => $user->security_access ? 1 : 0,
                'driver_access' => $user->driver_access ? 1 : 0,
                'survey_access' => $user->survey_access ? 1 : 0,
                'time_and_attendance_access' => $user->time_and_attendance_access ? 1 : 0,
                'stock_access' => $user->stock_access ? 1 : 0,
                'is_system_admin' => $user->is_system_admin ? 1 : 0,
                'created_at' => $user->created_at->toISOString(),
                'updated_at' => $user->updated_at->toISOString(),
            ]
        ]);
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
        $id = $request->super_admin_user_id;
        if($request->has('cellphone')){
            $cellphone = $request->cellphone;
        }else{
            $cellphone = null;
        }
        if($request->has('email')){
            $email = $request->email;
        }else{
            $email = null;
        }
        $customerSub = $this->findCustomerSubscriptionByUrl($request->app_url);
        Log::info('Password update for Customer: ' . $request->app_url);
        $customer = $customerSub->customer;
        if($customer){
            Log::info('Customer Found: ' . $customer->company_name);
        }
        Log::info("Received ".$request->password);
        $customerUser = CustomerUser::where('id', $id)
            ->where('customer_id', $customer->id)
            ->first();
        Log::info('User found: '.$customerUser->id);
        Log::info('Old password hash: ' . $customerUser->password);
        $password = $request->password;
        if($password){
            $customerUser->password = $password;
        }
        if($email){
            $customerUser->email_address = $email;
        }
        if($cellphone){
            $customerUser->cellphone = $cellphone;
        }
        $customerUser->save();
        Log::info('New password hash: ' . $customerUser->password);
        Log::info('Password updated for user: ' . $email);
        return response()->json(['message' => 'Password updated successfully to '.$request->password]);
    }

    public function deactivateUser(Request $request){
        $email = $request->email;
        $customerSub = $this->findCustomerSubscriptionByUrl($request->app_url);
        $customer = $customerSub->customer;
        if($customer){
            Log::info('Customer Found: ' . $customer->company_name);
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
        $customerSub = $this->findCustomerSubscriptionByUrl($request->app_url);
        $customer = $customerSub->customer;
        if($customer){
            Log::info('Customer Found: ' . $customer->company_name);
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

    /**
     * Update user from CMS with conflict resolution
     */
    public function updateFromCMS(Request $request)
    {
        Log::info('Update user from CMS: ' . json_encode($request->all()));
        
        // Validate the request
        $validated = $request->validate([
            'app_url' => 'required|string',
            'super_admin_user_id' => 'required|integer',
            'email' => 'required|email',
            'first_name' => 'required|string',
            'last_name' => 'nullable|string',
            'cellphone' => 'nullable|string',
            'password' => 'nullable|string|min:6', // Optional cleartext password
            'console_access' => 'nullable|boolean',
            'firearm_access' => 'nullable|boolean',
            'responder_access' => 'nullable|boolean',
            'reporter_access' => 'nullable|boolean',
            'security_access' => 'nullable|boolean',
            'driver_access' => 'nullable|boolean',
            'survey_access' => 'nullable|boolean',
            'time_and_attendance_access' => 'nullable|boolean',
            'stock_access' => 'nullable|boolean',
            'is_system_admin' => 'nullable|boolean',
            'active' => 'nullable|boolean',
            'cms_updated_at' => 'nullable|date', // For conflict resolution
        ]);

        // Find the customer subscription
        $customerSub = $this->findCustomerSubscriptionByUrl($validated['app_url']);
        if (!$customerSub) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid app URL',
            ], 400);
        }

        // Find the user by super_admin_user_id
        $user = CustomerUser::where('id', $validated['super_admin_user_id'])
            ->where('customer_id', $customerSub->customer_id)
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
                'error' => 'No user found with super_admin_user_id: ' . $validated['super_admin_user_id']
            ], 404);
        }

        // Conflict resolution: Check if CMS version is newer
        if (isset($validated['cms_updated_at'])) {
            $cmsUpdatedAt = \Carbon\Carbon::parse($validated['cms_updated_at']);
            $superAdminUpdatedAt = $user->updated_at;
            
            // If SuperAdmin version is newer, return SuperAdmin's data
            if ($superAdminUpdatedAt->gt($cmsUpdatedAt)) {
                Log::info('Conflict detected: SuperAdmin version is newer. Returning SuperAdmin data.');
                return response()->json([
                    'success' => true,
                    'message' => 'User updated successfully (conflict resolved - SuperAdmin version is newer)',
                    'user' => [
                        'id' => $user->id,
                        'email_address' => $user->email_address,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'cellphone' => $user->cellphone,
                        'password' => $user->password, // CRITICAL: Return hashed password
                        'console_access' => $user->console_access ? 1 : 0,
                        'firearm_access' => $user->firearm_access ? 1 : 0,
                        'responder_access' => $user->responder_access ? 1 : 0,
                        'reporter_access' => $user->reporter_access ? 1 : 0,
                        'security_access' => $user->security_access ? 1 : 0,
                        'driver_access' => $user->driver_access ? 1 : 0,
                        'survey_access' => $user->survey_access ? 1 : 0,
                        'time_and_attendance_access' => $user->time_and_attendance_access ? 1 : 0,
                        'stock_access' => $user->stock_access ? 1 : 0,
                        'is_system_admin' => $user->is_system_admin ? 1 : 0,
                        'updated_at' => $user->updated_at->toISOString(),
                    ]
                ]);
            }
        }

        // Update the user with CMS data
        $updateData = [
            'email_address' => $validated['email'],
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'] ?? $user->last_name,
            'cellphone' => $validated['cellphone'] ?? $user->cellphone,
            'console_access' => $validated['console_access'] ?? ($validated['active'] ?? $user->console_access),
            'firearm_access' => $validated['firearm_access'] ?? $user->firearm_access,
            'responder_access' => $validated['responder_access'] ?? $user->responder_access,
            'reporter_access' => $validated['reporter_access'] ?? $user->reporter_access,
            'security_access' => $validated['security_access'] ?? $user->security_access,
            'driver_access' => $validated['driver_access'] ?? $user->driver_access,
            'survey_access' => $validated['survey_access'] ?? $user->survey_access,
            'time_and_attendance_access' => $validated['time_and_attendance_access'] ?? $user->time_and_attendance_access,
            'stock_access' => $validated['stock_access'] ?? $user->stock_access,
            'is_system_admin' => $validated['is_system_admin'] ?? $user->is_system_admin,
        ];

        // Handle password update if provided (cleartext - model will hash it)
        if (isset($validated['password'])) {
            $updateData['password'] = $validated['password']; // Cleartext - model will hash it
        }

        $user->update($updateData);

        // Trigger user import to customer subscriptions
        StartUserSyncJob::dispatch($user->customer_id);

        // Refresh the user to get updated timestamps
        $user->refresh();

        Log::info('User updated successfully: ' . $user->email_address);

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'user' => [
                'id' => $user->id,
                'email_address' => $user->email_address,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'cellphone' => $user->cellphone,
                'password' => $user->password, // CRITICAL: Return hashed password
                'console_access' => $user->console_access ? 1 : 0,
                'firearm_access' => $user->firearm_access ? 1 : 0,
                'responder_access' => $user->responder_access ? 1 : 0,
                'reporter_access' => $user->reporter_access ? 1 : 0,
                'security_access' => $user->security_access ? 1 : 0,
                'driver_access' => $user->driver_access ? 1 : 0,
                'survey_access' => $user->survey_access ? 1 : 0,
                'time_and_attendance_access' => $user->time_and_attendance_access ? 1 : 0,
                'stock_access' => $user->stock_access ? 1 : 0,
                'is_system_admin' => $user->is_system_admin ? 1 : 0,
                'updated_at' => $user->updated_at->toISOString(),
            ]
        ]);
    }

    /**
     * Get single user by super_admin_user_id
     */
    public function getSingleUser(Request $request)
    {
        Log::info('Get single user: ' . json_encode($request->all()));
        
        // Validate the request
        $validated = $request->validate([
            'app_url' => 'required|string',
            'super_admin_user_id' => 'required|integer',
        ]);

        // Find the customer subscription
        $customerSub = $this->findCustomerSubscriptionByUrl($validated['app_url']);
        if (!$customerSub) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid app URL',
            ], 400);
        }

        // Find the user by super_admin_user_id
        $user = CustomerUser::where('id', $validated['super_admin_user_id'])
            ->where('customer_id', $customerSub->customer_id)
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
                'error' => 'No user found with super_admin_user_id: ' . $validated['super_admin_user_id']
            ], 404);
        }

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'email_address' => $user->email_address,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'cellphone' => $user->cellphone,
                'password' => $user->password, // Hashed password
                'console_access' => $user->console_access ? 1 : 0,
                'firearm_access' => $user->firearm_access ? 1 : 0,
                'responder_access' => $user->responder_access ? 1 : 0,
                'reporter_access' => $user->reporter_access ? 1 : 0,
                'security_access' => $user->security_access ? 1 : 0,
                'driver_access' => $user->driver_access ? 1 : 0,
                'survey_access' => $user->survey_access ? 1 : 0,
                'time_and_attendance_access' => $user->time_and_attendance_access ? 1 : 0,
                'stock_access' => $user->stock_access ? 1 : 0,
                'is_system_admin' => $user->is_system_admin ? 1 : 0,
                'created_at' => $user->created_at->toISOString(),
                'updated_at' => $user->updated_at->toISOString(),
            ]
        ]);
    }

    /**
     * Update user password from CMS (receives plain text password and hashes it)
     */
    public function updatePasswordFromCMS(Request $request)
    {
        Log::info('Update password from CMS: ' . json_encode($request->all()));
        
        // Validate the request
        $validated = $request->validate([
            'app_url' => 'required|string',
            'super_admin_user_id' => 'required|integer',
            'password' => 'required|string|min:6',
            'email' => 'nullable|email',
            'cellphone' => 'nullable|string',
        ]);

        // Find the customer subscription
        $customerSub = $this->findCustomerSubscriptionByUrl($validated['app_url']);
        if (!$customerSub) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid app URL',
            ], 400);
        }

        // Find the user by super_admin_user_id
        $user = CustomerUser::where('id', $validated['super_admin_user_id'])
            ->where('customer_id', $customerSub->customer_id)
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
                'error' => 'No user found with super_admin_user_id: ' . $validated['super_admin_user_id']
            ], 404);
        }

        // Update password (will be automatically hashed by the model's setPasswordAttribute)
        $user->password = $validated['password']; // Plain text - model will hash it
        
        // Update other fields if provided
        if (isset($validated['email'])) {
            $user->email_address = $validated['email'];
        }
        if (isset($validated['cellphone'])) {
            $user->cellphone = $validated['cellphone'];
        }
        
        $user->save();

        // Trigger user import to customer subscriptions
        StartUserSyncJob::dispatch($user->customer_id);

        Log::info('Password updated successfully for user: ' . $user->email_address);

        return response()->json([
            'success' => true,
            'message' => 'Password updated successfully',
            'user' => [
                'id' => $user->id,
                'email_address' => $user->email_address,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'cellphone' => $user->cellphone,
                'updated_at' => $user->updated_at->toISOString(),
            ]
        ]);
    }

}
