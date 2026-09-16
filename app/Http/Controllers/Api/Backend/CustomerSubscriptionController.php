<?php

namespace App\Http\Controllers\Api\Backend;

use App\Jobs\PushBrandingToTenantsJob;
use App\Jobs\SiteDeployment\DeploySite;
use App\Models\BrandingSyncLog;
use App\Models\CustomerSubscription;
use App\Models\CustomerSubscriptionDeploymentJob;
use App\Services\CustomerSubscriptionService;
use App\Services\DomainDnsService;
use App\Services\ForgeService;
use App\Services\LogoSyncService;
use App\Services\SiteDeploymentScheduler;
use App\Support\BrandingSync\BrandingSyncPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

class CustomerSubscriptionController extends Controller
{
    /** @var list<string> */
    private const HIDDEN = ['env', 'database_password'];

    /** @var list<string> */
    private const SEARCHABLE = [
        'url',
        'domain',
        'app_name',
        'database_name',
        'database_user',
        'forge_site_id',
        'deployed_version',
        'subscriptionType.name',
        'customer.company_name',
    ];

    /** @var list<string> */
    private const SORTABLE = [
        'id', 'url', 'domain', 'app_name', 'database_name', 'server_id',
        'subscription_type_id', 'customer_id', 'deployed_version',
        'site_created_at', 'github_sent_at', 'env_sent_at',
        'deployment_script_sent_at', 'ssl_deployed_at', 'deployed_at',
        'created_at', 'updated_at',
    ];

    /** @var list<string> */
    private const JOBS_SEARCHABLE = ['batch_id', 'job_name', 'status', 'error_message', 'forge_status'];

    /** @var list<string> */
    private const JOBS_SORTABLE = [
        'id', 'batch_id', 'position', 'job_name', 'status', 'forge_status',
        'started_at', 'finished_at', 'created_at',
    ];

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CustomerSubscription::class);

        $validated = $this->listFilters($request, [
            'customer_id' => ['sometimes', 'integer', 'exists:customers,id'],
            'subscription_type_id' => ['sometimes', 'integer', 'exists:subscription_types,id'],
        ], self::SORTABLE);

        $query = CustomerSubscription::query()
            ->with([
                'subscriptionType:id,name',
                'customer:id,company_name',
            ]);

        if (array_key_exists('customer_id', $validated)) {
            $query->where('customer_id', $validated['customer_id']);
        }
        if (array_key_exists('subscription_type_id', $validated)) {
            $query->where('subscription_type_id', $validated['subscription_type_id']);
        }

        $this->applySearch($query, $validated['search'] ?? null, self::SEARCHABLE);
        $this->applySort($query, $validated, fn ($q) => $q->orderBy('id'));

        $paginator = $query->paginate($validated['per_page']);
        $paginator->getCollection()->each(
            fn (CustomerSubscription $row) => $row->makeHidden(self::HIDDEN)
        );

        return response()->json($paginator);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $row = CustomerSubscription::query()
            ->with(['subscriptionType:id,name', 'customer:id,company_name'])
            ->findOrFail($id);
        $this->authorize('view', $row);

        return response()->json(['data' => $this->present($row, $request)]);
    }

    public function verifyDomain(Request $request, DomainDnsService $dns): JsonResponse
    {
        $this->authorize('create', CustomerSubscription::class);

        $validated = $request->validate([
            'domain' => ['required', 'string', 'max:255'],
        ]);

        $key = 'verify-domain:'.($request->user()?->id ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, 20)) {
            abort(429, 'Too many DNS verification attempts. Try again shortly.');
        }
        RateLimiter::hit($key, 60);

        $result = $dns->lookup($validated['domain']);

        return response()->json([
            'data' => [
                'domain' => $validated['domain'],
                'resolves' => $result['resolves'],
                'ips' => $result['ips'],
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', CustomerSubscription::class);

        $validated = $request->validate($this->storeRules());
        $triggerSiteDeployment = (bool) ($validated['trigger_site_deployment'] ?? false);
        $forceSiteDeployment = (bool) ($validated['force_site_deployment'] ?? false);
        unset($validated['trigger_site_deployment'], $validated['force_site_deployment']);

        $row = CustomerSubscription::query()->create($validated);
        $row->load(['subscriptionType:id,name', 'customer:id,company_name']);

        if ($triggerSiteDeployment) {
            try {
                app(SiteDeploymentScheduler::class)->schedule($row, $forceSiteDeployment);
            } catch (\RuntimeException $e) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'data' => $this->present($row, $request),
                ], 409);
            }
        }

        $data = $row->fresh()->load(['subscriptionType:id,name', 'customer:id,company_name']);

        return response()->json(['data' => $this->present($data, $request)], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $row = CustomerSubscription::query()->findOrFail($id);
        $this->authorize('update', $row);

        $validated = $request->validate($this->updateRules());
        if ($validated !== []) {
            $row->update($validated);
        }

        $data = $row->fresh()->load(['subscriptionType:id,name', 'customer:id,company_name']);

        return response()->json(['data' => $this->present($data, $request)]);
    }

    public function destroy(int $id): JsonResponse
    {
        $row = CustomerSubscription::query()->findOrFail($id);
        $this->authorize('delete', $row);
        $row->delete();

        return response()->json(['ok' => true, 'id' => $id]);
    }

    public function recreateSite(int $id): JsonResponse
    {
        $row = CustomerSubscription::query()->findOrFail($id);
        $this->authorize('update', $row);

        if (filled($row->forge_site_id)) {
            return response()->json(['message' => 'Site already has a Forge site ID.'], 422);
        }
        if (blank($row->server_id)) {
            return response()->json(['message' => 'A Forge server must be set before creating a site.'], 422);
        }

        try {
            $batchId = app(SiteDeploymentScheduler::class)->scheduleSiteCreationOnly($row, true);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json([
            'ok' => true,
            'data' => ['batch_id' => $batchId],
        ]);
    }

    /**
     * Replace or clear individual logo slots. Uploads have to be their own
     * endpoint because update() takes JSON, and a slot can only be filled once
     * the record exists and we know which subscription to attach the file to.
     */
    public function uploadLogos(Request $request, int $id): JsonResponse
    {
        $row = CustomerSubscription::query()->findOrFail($id);
        $this->authorize('update', $row);

        $slots = CustomerSubscription::LOGO_SLOTS;

        $rules = [
            'clear' => ['sometimes', 'array'],
            'clear.*' => ['string', Rule::in($slots)],
        ];
        foreach ($slots as $slot) {
            // Matches the Filament form: public disk, 10 MB ceiling.
            $rules[$slot] = ['sometimes', 'file', 'mimes:jpg,jpeg,png,gif,webp,svg', 'max:10240'];
        }

        $validated = $request->validate($rules);
        $clear = array_values(array_unique($validated['clear'] ?? []));
        $uploaded = array_values(array_filter($slots, fn (string $slot) => $request->hasFile($slot)));

        if ($uploaded === [] && $clear === []) {
            return response()->json(['message' => 'Provide at least one logo file or slot to clear.'], 422);
        }

        $conflicts = array_intersect($uploaded, $clear);
        if ($conflicts !== []) {
            throw ValidationException::withMessages([
                'clear' => 'Cannot upload and clear the same slot: '.implode(', ', $conflicts).'.',
            ]);
        }

        $disk = Storage::disk('public');
        $changes = [];

        foreach ($uploaded as $slot) {
            $file = $request->file($slot);
            $changes[$slot] = $file->store('/', 'public');
            $changes[LogoSyncService::timestampColumn($slot)] = now();
            if (array_key_exists($slot, LogoSyncService::SLOT_TO_CMS)) {
                $changes[BrandingSyncPayload::checksumColumn($slot)] = BrandingSyncPayload::computeChecksum(
                    (string) file_get_contents($file->getRealPath())
                );
            }
        }
        foreach ($clear as $slot) {
            $changes[$slot] = null;
            $changes[LogoSyncService::timestampColumn($slot)] = now();
            if (array_key_exists($slot, LogoSyncService::SLOT_TO_CMS)) {
                $changes[BrandingSyncPayload::checksumColumn($slot)] = null;
            }
        }

        // Only drop the previous file once the replacement is safely on disk.
        $replaced = array_filter(array_map(
            fn (string $slot) => $row->getAttribute($slot),
            array_values(array_unique([...$uploaded, ...$clear])),
        ), 'filled');

        // Model hook queues PushBrandingToTenantsJob for CMS slots when skipSync is false.
        $row->update($changes);

        foreach ($replaced as $path) {
            $disk->delete($path);
        }

        $data = $row->fresh()->load(['subscriptionType:id,name', 'customer:id,company_name']);

        return response()->json(['data' => $this->present($data, $request)]);
    }

    /**
     * Per-slot branding status for the SPA Branding tab (url, timestamp, checksum, last sync).
     */
    public function branding(int $id): JsonResponse
    {
        $row = CustomerSubscription::query()->findOrFail($id);
        $this->authorize('view', $row);

        $slots = [];
        foreach (BrandingSyncPayload::SLOTS as $cmsSlot) {
            $saSlot = BrandingSyncPayload::CMS_TO_SA_SLOT[$cmsSlot];
            $path = $row->getAttribute($saSlot);
            $lastLog = BrandingSyncLog::query()
                ->where('customer_subscription_id', $row->id)
                ->where('slot', $cmsSlot)
                ->where('direction', 'outbound')
                ->latest('synced_at')
                ->first();

            $slots[$cmsSlot] = [
                'sa_slot' => $saSlot,
                'url' => filled($path) ? LogoSyncService::absolutePublicUrl((string) $path) : null,
                'updated_at' => optional($row->getAttribute(LogoSyncService::timestampColumn($saSlot)))?->toIso8601String(),
                'checksum' => $row->getAttribute(BrandingSyncPayload::checksumColumn($saSlot)),
                'sync_status' => $lastLog?->status,
                'sync_error' => $lastLog?->error_message,
                'synced_at' => optional($lastLog?->synced_at)?->toIso8601String(),
            ];
        }

        return response()->json([
            'data' => [
                'logo_urls' => $row->logo_urls,
                'slots' => $slots,
            ],
        ]);
    }

    /**
     * Manually re-push one or all CMS branding slots to the tenant.
     */
    public function resyncBranding(Request $request, int $id): JsonResponse
    {
        $row = CustomerSubscription::query()->findOrFail($id);
        $this->authorize('update', $row);

        $validated = $request->validate([
            'slot' => ['sometimes', 'nullable', 'string', Rule::in(BrandingSyncPayload::SLOTS)],
        ]);

        $cmsSlots = filled($validated['slot'] ?? null)
            ? [$validated['slot']]
            : BrandingSyncPayload::SLOTS;

        if ((int) $row->subscription_type_id !== 1) {
            return response()->json(['message' => 'Branding sync is only available for CMS subscriptions.'], 422);
        }

        PushBrandingToTenantsJob::dispatch($row->id, $cmsSlots);

        return response()->json([
            'ok' => true,
            'queued' => $cmsSlots,
        ]);
    }

    public function generateLogos(int $id): JsonResponse
    {
        $row = CustomerSubscription::query()->findOrFail($id);
        $this->authorize('update', $row);

        try {
            CustomerSubscriptionService::generatePWALogos($row->id);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'data' => $this->present($row->fresh())]);
    }

    public function deploy(int $id): JsonResponse
    {
        $row = CustomerSubscription::query()->findOrFail($id);
        $this->authorize('update', $row);

        DeploySite::dispatch($row->id);

        return response()->json(['ok' => true, 'data' => $this->present($row)]);
    }

    public function pullEnv(int $id): JsonResponse
    {
        $row = CustomerSubscription::query()->findOrFail($id);
        $this->authorize('update', $row);

        if (blank($row->server_id) || blank($row->forge_site_id)) {
            return response()->json(['message' => 'server_id and forge_site_id are required to pull env.'], 422);
        }

        try {
            ForgeService::getSiteEnvironment($row);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        return response()->json(['ok' => true, 'data' => $this->present($row->fresh())]);
    }

    public function updateServer(Request $request, int $id): JsonResponse
    {
        $row = CustomerSubscription::query()->findOrFail($id);
        $this->authorize('update', $row);

        $validated = $request->validate([
            'server_id' => ['required', 'integer', 'exists:my_forge_servers,forge_server_id'],
        ]);

        $row->server_id = $validated['server_id'];
        $row->save();

        return response()->json(['data' => $this->present($row->fresh())]);
    }

    public function pipelineSteps(int $id): JsonResponse
    {
        $row = CustomerSubscription::query()->findOrFail($id);
        $this->authorize('view', $row);

        $template = app(SiteDeploymentScheduler::class)->getCompleteCreationPipelineTemplate($row);
        $steps = [];
        foreach ($template as $index => $item) {
            [$jobName, $params] = $item;
            $steps[] = [
                'index' => $index,
                'job_name' => $jobName,
                'parameters' => $params,
            ];
        }

        return response()->json(['data' => $steps]);
    }

    public function queuePipelineStep(int $id, int $index): JsonResponse
    {
        $row = CustomerSubscription::query()->findOrFail($id);
        $this->authorize('update', $row);

        try {
            $batchId = app(SiteDeploymentScheduler::class)->queueSingleTemplateStep($row, $index);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'data' => ['batch_id' => $batchId, 'index' => $index],
        ]);
    }

    public function deploymentJobs(Request $request, int $id): JsonResponse
    {
        $row = CustomerSubscription::query()->findOrFail($id);
        $this->authorize('view', $row);

        $validated = $this->listFilters($request, [], self::JOBS_SORTABLE);

        $query = $row->deploymentJobs();
        $this->applySearch($query, $validated['search'] ?? null, self::JOBS_SEARCHABLE);
        $this->applySort($query, $validated, fn ($q) => $q->orderBy('id'));

        $paginator = $query->paginate($validated['per_page']);

        return response()->json($paginator);
    }

    public function retryDeploymentJob(int $id, int $jobId): JsonResponse
    {
        $row = CustomerSubscription::query()->findOrFail($id);
        $this->authorize('update', $row);

        $job = $row->deploymentJobs()->findOrFail($jobId);

        if ($job->status === CustomerSubscriptionDeploymentJob::STATUS_RUNNING) {
            return response()->json(['message' => 'Cannot retry a running deployment job.'], 422);
        }

        try {
            $batchId = app(SiteDeploymentScheduler::class)->retryDeploymentJob($job);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'data' => ['batch_id' => $batchId, 'deployment_job_id' => $job->id],
        ]);
    }

    public function runDeploymentJobAlone(int $id, int $jobId): JsonResponse
    {
        $row = CustomerSubscription::query()->findOrFail($id);
        $this->authorize('update', $row);

        $job = $row->deploymentJobs()->findOrFail($jobId);

        if ($job->status === CustomerSubscriptionDeploymentJob::STATUS_RUNNING) {
            return response()->json(['message' => 'Cannot run a running deployment job alone.'], 422);
        }

        try {
            $batchId = app(SiteDeploymentScheduler::class)->requeueDeploymentJobAlone($job);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'data' => ['batch_id' => $batchId, 'deployment_job_id' => $job->id],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function storeRules(): array
    {
        return [
            'url' => ['required', 'string', 'max:2048'],
            'domain' => ['required', 'string', 'max:255'],
            'app_name' => ['required', 'string', 'max:255'],
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'subscription_type_id' => ['required', 'integer', 'exists:subscription_types,id'],
            'database_name' => ['nullable', 'string', 'max:255'],
            'database_user' => ['nullable', 'string', 'max:32'],
            'server_id' => ['nullable', 'integer'],
            'logo_1' => ['nullable', 'string', 'max:500'],
            'logo_2' => ['nullable', 'string', 'max:500'],
            'logo_3' => ['nullable', 'string', 'max:500'],
            'logo_4' => ['nullable', 'string', 'max:500'],
            'logo_5' => ['nullable', 'string', 'max:500'],
            'env' => ['nullable', 'string'],
            'uuid' => ['nullable', 'string', 'max:36'],
            'forge_site_id' => ['nullable', 'string', 'max:100'],
            'site_created_at' => ['nullable', 'date'],
            'github_sent_at' => ['nullable', 'date'],
            'env_sent_at' => ['nullable', 'date'],
            'deployment_script_sent_at' => ['nullable', 'date'],
            'ssl_deployed_at' => ['nullable', 'date'],
            'deployed_at' => ['nullable', 'date'],
            'panic_button_enabled' => ['nullable', 'boolean'],
            'deployed_version' => ['nullable', 'string', 'max:8'],
            'trigger_site_deployment' => ['sometimes', 'boolean'],
            'force_site_deployment' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function updateRules(): array
    {
        $out = [];
        foreach ($this->storeRules() as $key => $rule) {
            if (in_array($key, ['trigger_site_deployment', 'force_site_deployment'], true)) {
                continue;
            }
            $out[$key] = array_merge(['sometimes'], array_slice($rule, 1));
        }

        return $out;
    }

    private function present(CustomerSubscription $row, ?Request $request = null): CustomerSubscription
    {
        $row->makeHidden('database_password');
        if (! $request?->boolean('include_env')) {
            $row->makeHidden('env');
        }

        return $row;
    }
}
