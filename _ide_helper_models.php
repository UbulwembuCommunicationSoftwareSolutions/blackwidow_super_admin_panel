<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models{
/**
 * @property int $id
 * @property string $company_name
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $token
 * @property string $docket_description
 * @property string $task_description
 * @property string $level_one_description
 * @property string $level_two_description
 * @property string $level_three_description
 * @property string $level_four_description
 * @property string $level_five_description
 * @property int $level_one_in_use
 * @property int $level_two_in_use
 * @property int $level_three_in_use
 * @property int $max_users
 * @property string|null $uuid
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CustomerSubscription> $customerSubscriptions
 * @property-read int|null $customer_subscriptions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CustomerUser> $customerUsers
 * @property-read int|null $customer_users_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 * @method static \Database\Factories\CustomerFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereCompanyName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereDocketDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereLevelFiveDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereLevelFourDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereLevelOneDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereLevelOneInUse($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereLevelThreeDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereLevelThreeInUse($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereLevelTwoDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereLevelTwoInUse($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereMaxUsers($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereTaskDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer withoutTrashed()
 */
	class Customer extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $url
 * @property int|null $subscription_type_id
 * @property string|null $logo_1
 * @property string|null $logo_2
 * @property string|null $logo_3
 * @property string|null $logo_4
 * @property string|null $logo_5
 * @property int|null $customer_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $panic_button_enabled
 * @property string|null $forge_site_id
 * @property string|null $env
 * @property int|null $server_id
 * @property string|null $app_name
 * @property string $database_name
 * @property string|null $site_created_at
 * @property string|null $github_sent_at
 * @property string|null $env_sent_at
 * @property string|null $deployment_script_sent_at
 * @property string|null $ssl_deployed_at
 * @property string|null $deployed_at
 * @property string|null $domain
 * @property string|null $jobs
 * @property string|null $deployed_version
 * @property string|null $uuid
 * @property-read \App\Models\Customer|null $customer
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DeploymentScript> $deploymentScript
 * @property-read int|null $deployment_script_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\EnvVariables> $envVariables
 * @property-read int|null $env_variables_count
 * @property-read mixed $null_variable_count
 * @property-read \App\Models\SubscriptionType|null $subscriptionType
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerSubscription newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerSubscription newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerSubscription query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerSubscription whereAppName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerSubscription whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerSubscription whereCustomerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerSubscription whereDatabaseName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerSubscription whereDeployedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerSubscription whereDeployedVersion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerSubscription whereDeploymentScriptSentAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerSubscription whereDomain($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerSubscription whereEnv($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerSubscription whereEnvSentAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerSubscription whereForgeSiteId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerSubscription whereGithubSentAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerSubscription whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerSubscription whereJobs($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerSubscription whereLogo1($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerSubscription whereLogo2($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerSubscription whereLogo3($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerSubscription whereLogo4($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerSubscription whereLogo5($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerSubscription wherePanicButtonEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerSubscription whereServerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerSubscription whereSiteCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerSubscription whereSslDeployedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerSubscription whereSubscriptionTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerSubscription whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerSubscription whereUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerSubscription whereUuid($value)
 */
	class CustomerSubscription extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $customer_id
 * @property string $email_address
 * @property string $password
 * @property string|null $first_name
 * @property string|null $last_name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $console_access
 * @property int $firearm_access
 * @property int $responder_access
 * @property int $reporter_access
 * @property int $security_access
 * @property int $driver_access
 * @property int $survey_access
 * @property int $time_and_attendance_access
 * @property int $stock_access
 * @property string|null $cellphone
 * @property bool $is_system_admin
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\Customer|null $customer
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerUser newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerUser newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerUser onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerUser query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerUser whereCellphone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerUser whereConsoleAccess($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerUser whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerUser whereCustomerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerUser whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerUser whereDriverAccess($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerUser whereEmailAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerUser whereFirearmAccess($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerUser whereFirstName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerUser whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerUser whereIsSystemAdmin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerUser whereLastName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerUser wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerUser whereReporterAccess($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerUser whereResponderAccess($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerUser whereSecurityAccess($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerUser whereStockAccess($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerUser whereSurveyAccess($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerUser whereTimeAndAttendanceAccess($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerUser whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerUser withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomerUser withoutTrashed()
 */
	class CustomerUser extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $script
 * @property int $customer_subscription_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\CustomerSubscription|null $customerSubscription
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeploymentScript newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeploymentScript newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeploymentScript query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeploymentScript whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeploymentScript whereCustomerSubscriptionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeploymentScript whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeploymentScript whereScript($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeploymentScript whereUpdatedAt($value)
 */
	class DeploymentScript extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $script
 * @property int $subscription_type_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\SubscriptionType|null $subscriptionType
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeploymentTemplate newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeploymentTemplate newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeploymentTemplate query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeploymentTemplate whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeploymentTemplate whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeploymentTemplate whereScript($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeploymentTemplate whereSubscriptionTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeploymentTemplate whereUpdatedAt($value)
 */
	class DeploymentTemplate extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $key
 * @property string $value
 * @property int $customer_subscription_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\CustomerSubscription|null $customerSubscription
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EnvVariables newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EnvVariables newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EnvVariables query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EnvVariables whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EnvVariables whereCustomerSubscriptionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EnvVariables whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EnvVariables whereKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EnvVariables whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EnvVariables whereValue($value)
 */
	class EnvVariables extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $forge_server_id
 * @property string|null $name
 * @property string|null $ip_address
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ForgeServer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ForgeServer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ForgeServer query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ForgeServer whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ForgeServer whereForgeServerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ForgeServer whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ForgeServer whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ForgeServer whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ForgeServer whereUpdatedAt($value)
 */
	class ForgeServer extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string $server_id
 * @property int $template_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NginxTemplate newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NginxTemplate newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NginxTemplate query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NginxTemplate whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NginxTemplate whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NginxTemplate whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NginxTemplate whereServerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NginxTemplate whereTemplateId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NginxTemplate whereUpdatedAt($value)
 */
	class NginxTemplate extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $key
 * @property string $value
 * @property int $subscription_type_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\SubscriptionType|null $subscriptionType
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RequiredEnvVariables newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RequiredEnvVariables newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RequiredEnvVariables query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RequiredEnvVariables whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RequiredEnvVariables whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RequiredEnvVariables whereKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RequiredEnvVariables whereSubscriptionTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RequiredEnvVariables whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RequiredEnvVariables whereValue($value)
 */
	class RequiredEnvVariables extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $github_repo
 * @property string|null $branch
 * @property string|null $project_type
 * @property int|null $nginx_template_id
 * @property string $public_dir
 * @property string|null $master_version
 * @method static \Database\Factories\SubscriptionTypeFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionType newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionType newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionType onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionType query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionType whereBranch($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionType whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionType whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionType whereGithubRepo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionType whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionType whereMasterVersion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionType whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionType whereNginxTemplateId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionType whereProjectType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionType wherePublicDir($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionType whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionType withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionType withoutTrashed()
 */
	class SubscriptionType extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Customer> $customers
 * @property-read int|null $customers_count
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Role> $roles
 * @property-read int|null $roles_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User permission($permissions, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User role($roles, $guard = null, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutPermission($permissions)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutRole($roles, $guard = null)
 */
	class User extends \Eloquent implements \Filament\Models\Contracts\FilamentUser {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $user_id
 * @property int $customer_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\Customer|null $customer
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserCustomer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserCustomer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserCustomer onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserCustomer query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserCustomer whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserCustomer whereCustomerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserCustomer whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserCustomer whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserCustomer whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserCustomer whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserCustomer withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserCustomer withoutTrashed()
 */
	class UserCustomer extends \Eloquent {}
}

