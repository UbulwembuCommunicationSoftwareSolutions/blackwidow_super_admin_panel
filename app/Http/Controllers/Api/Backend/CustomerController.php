<?php

namespace App\Http\Controllers\Api\Backend;

use App\Jobs\SyncCustomerEnvToSubscriptionsJob;
use App\Models\Customer;
use App\Support\CustomerAdminAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    /** @var list<string> */
    private const HIDDEN = [
        'token',
        'google_api_key',
        's3_endpoint',
        's3_key',
        's3_secret',
        's3_region',
        's3_bucket',
        's3_use_path_style_endpoint',
        'mail_password',
    ];

    /** @var list<string> */
    private const S3_REQUIRED = [
        's3_endpoint',
        's3_key',
        's3_secret',
        's3_bucket',
    ];

    /** @var list<string> */
    private const CREDENTIALS = [
        'token',
        'google_api_key',
        's3_endpoint',
        's3_key',
        's3_secret',
        's3_region',
        's3_bucket',
        's3_use_path_style_endpoint',
        'mail_mailer',
        'mail_transport',
        'mail_host',
        'mail_url',
        'mail_port',
        'mail_username',
        'mail_password',
        'mail_encryption',
        'mail_scheme',
        'mail_from_address',
        'mail_from_name',
        'mail_ehlo_domain',
    ];

    /**
     * Mailers Laravel can drive from MAIL_MAILER / MAIL_TRANSPORT. See config/mail.php.
     *
     * @var list<string>
     */
    private const MAILERS = [
        'smtp',
        'sendmail',
        'ses',
        'mailgun',
        'postmark',
        'resend',
        'log',
        'array',
        'failover',
        'roundrobin',
    ];

    /**
     * The API token is deliberately excluded — it is a secret and is hidden on read.
     *
     * @var list<string>
     */
    private const SEARCHABLE = [
        'company_name',
        'docket_description',
        'task_description',
        'level_one_description',
        'level_two_description',
        'level_three_description',
        'level_four_description',
        'level_five_description',
        'uuid',
    ];

    /** @var list<string> */
    private const SORTABLE = ['id', 'company_name', 'max_users', 'created_at', 'updated_at', 'deleted_at'];

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Customer::class);

        $validated = $this->listFilters($request, [
            'trashed' => ['sometimes', 'in:with,only'],
        ], self::SORTABLE);

        $query = Customer::query()
            ->withCount(['customerSubscriptions', 'customerUsers']);
        $this->applyTrashed($query, $validated['trashed'] ?? null);
        $this->applySearch($query, $validated['search'] ?? null, self::SEARCHABLE);
        $this->applySort($query, $validated, fn ($q) => $q->orderBy('id'));

        $paginator = $query->paginate($validated['per_page']);
        $paginator->getCollection()->transform(
            fn (Customer $customer) => $this->present($customer)
        );

        return response()->json($paginator);
    }

    public function show(int $id): JsonResponse
    {
        $row = Customer::query()->findOrFail($id);
        $this->authorize('view', $row);

        return response()->json(['data' => $this->present($row)]);
    }

    public function credentials(int $id): JsonResponse
    {
        $row = Customer::query()->findOrFail($id);
        $this->authorize('viewCredentials', $row);

        return response()->json(['data' => $row->only(self::CREDENTIALS)]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Customer::class);

        $row = Customer::query()->create($request->validate($this->storeRules()));

        return response()->json(['data' => $this->present($row->fresh())], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $row = Customer::query()->findOrFail($id);
        $this->authorize('update', $row);

        $validated = $request->validate($this->updateRules());
        if ($validated !== []) {
            $row->update($validated);
        }

        return response()->json(['data' => $this->present($row->fresh())]);
    }

    /**
     * Re-apply the customer's Google Maps key and SMTP credentials to every subscription's env and
     * push the changed envs to Forge. Runs automatically whenever those fields are updated; this
     * endpoint is for replaying it (e.g. after a site was recreated).
     */
    public function syncEnv(int $id): JsonResponse
    {
        $row = Customer::query()->findOrFail($id);
        $this->authorize('update', $row);

        if ($row->subscriptionEnvOverrides() === []) {
            return response()->json([
                'message' => 'Nothing to sync: this customer has no Google API key or mail settings configured.',
            ], 422);
        }

        SyncCustomerEnvToSubscriptionsJob::dispatch($row->id);

        return response()->json([
            'ok' => true,
            'customer_subscriptions_count' => $row->customerSubscriptions()->count(),
            'keys' => array_keys($row->subscriptionEnvOverrides()),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $row = Customer::query()->findOrFail($id);
        $this->authorize('delete', $row);
        $row->delete();

        return response()->json(['ok' => true, 'id' => $id]);
    }

    public function restore(int $id): JsonResponse
    {
        $row = Customer::withTrashed()->findOrFail($id);
        $this->authorize('restore', $row);
        $row->restore();

        return response()->json(['data' => $this->present($row->fresh())]);
    }

    public function forceDestroy(int $id): JsonResponse
    {
        $row = Customer::withTrashed()->findOrFail($id);
        $this->authorize('forceDelete', $row);
        $row->forceDelete();

        return response()->json(['ok' => true, 'id' => $id]);
    }

    /**
     * @return array<string, mixed>
     */
    private function storeRules(): array
    {
        return array_merge([
            'company_name' => ['required', 'string', 'max:255'],
        ], $this->fieldRules('nullable'));
    }

    /**
     * @return array<string, mixed>
     */
    private function updateRules(): array
    {
        $rules = $this->fieldRules('sometimes');
        $rules['company_name'] = ['sometimes', 'string', 'max:255'];

        return $rules;
    }

    /**
     * @return array<string, mixed>
     */
    private function fieldRules(string $presence): array
    {
        return [
            'google_api_key' => [$presence, 'nullable', 'string'],
            'mail_mailer' => [$presence, 'nullable', 'string', Rule::in(self::MAILERS)],
            'mail_transport' => [$presence, 'nullable', 'string', Rule::in(self::MAILERS)],
            'mail_host' => [$presence, 'nullable', 'string', 'max:255'],
            'mail_url' => [$presence, 'nullable', 'string', 'max:512'],
            'mail_port' => [$presence, 'nullable', 'integer', 'min:1', 'max:65535'],
            'mail_username' => [$presence, 'nullable', 'string', 'max:255'],
            'mail_password' => [$presence, 'nullable', 'string', 'max:1024'],
            'mail_encryption' => [$presence, 'nullable', 'string', 'max:32'],
            'mail_scheme' => [$presence, 'nullable', 'string', 'max:32'],
            'mail_from_address' => [$presence, 'nullable', 'email', 'max:255'],
            'mail_from_name' => [$presence, 'nullable', 'string', 'max:255'],
            'mail_ehlo_domain' => [$presence, 'nullable', 'string', 'max:255'],
            's3_endpoint' => [$presence, 'string', 'max:2048'],
            's3_key' => [$presence, 'string', 'max:255'],
            's3_secret' => [$presence, 'string', 'max:255'],
            's3_region' => [$presence, 'string', 'max:255'],
            's3_bucket' => [$presence, 'string', 'max:255'],
            's3_use_path_style_endpoint' => [$presence, 'boolean'],
            'token' => [$presence, 'string', 'max:255'],
            'docket_description' => [$presence, 'string'],
            'task_description' => [$presence, 'string'],
            'level_one_description' => [$presence, 'string'],
            'level_two_description' => [$presence, 'string'],
            'level_three_description' => [$presence, 'string'],
            'level_four_description' => [$presence, 'string'],
            'level_five_description' => [$presence, 'string'],
            'level_one_in_use' => [$presence, 'boolean'],
            'level_two_in_use' => [$presence, 'boolean'],
            'level_three_in_use' => [$presence, 'boolean'],
            'max_users' => [$presence, 'integer', 'min:0'],
        ];
    }

    private function present(Customer $customer): Customer
    {
        if (
            ! array_key_exists('customer_subscriptions_count', $customer->getAttributes())
            || ! array_key_exists('customer_users_count', $customer->getAttributes())
        ) {
            $customer->loadCount(['customerSubscriptions', 'customerUsers']);
        }

        $s3Filled = collect(self::S3_REQUIRED)
            ->filter(fn (string $field) => filled($customer->getAttribute($field)))
            ->count();

        $customer->setAttribute('google_api_key_set', filled($customer->google_api_key));
        $customer->setAttribute('s3_configured', $s3Filled === 4);
        $customer->setAttribute('s3_partial', $s3Filled > 0 && $s3Filled < 4);
        $customer->setAttribute('mail_configured', $customer->hasMailConfiguration());
        $customer->setAttribute('mail_password_set', filled($customer->mail_password));

        $hidden = self::HIDDEN;
        if (CustomerAdminAccess::isCustomerAdmin(auth()->user())) {
            $hidden = array_values(array_unique([...$hidden, ...self::CREDENTIALS]));
        }

        return $customer->makeHidden($hidden);
    }
}
