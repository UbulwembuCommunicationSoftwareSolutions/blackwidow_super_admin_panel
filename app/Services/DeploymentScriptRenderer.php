<?php

namespace App\Services;

use App\Helpers\ForgeApi;
use App\Models\CustomerSubscription;
use App\Models\DeploymentScript;
use App\Models\DeploymentTemplate;
use RuntimeException;

class DeploymentScriptRenderer
{
    /**
     * Render the deployment script for a subscription from its template (or custom script),
     * substituting #WEBSITE_URL# and #RELEASE_TAG#, and optionally push it to Forge.
     *
     * @return array{0: DeploymentScript, 1: bool} Script and whether Forge was updated.
     */
    public function renderAndPush(CustomerSubscription $customerSubscription, bool $pushToForge = true): array
    {
        [$script, $changed] = $this->render($customerSubscription);

        if (! $pushToForge || ! $changed) {
            return [$script, false];
        }

        if (! $customerSubscription->server_id || ! $customerSubscription->forge_site_id) {
            return [$script, false];
        }

        $forgeApi = new ForgeApi;
        $forgeApi->sendDeploymentScript($customerSubscription);

        return [$script, true];
    }

    /**
     * @return array{0: DeploymentScript, 1: bool} Script and whether the stored script changed.
     */
    public function render(CustomerSubscription $customerSubscription): array
    {
        $target = $customerSubscription->targetRelease();
        $existing = $customerSubscription->deploymentScript()->first();

        if ($existing?->is_custom) {
            $source = (string) $existing->script;
        } else {
            $template = DeploymentTemplate::query()
                ->where('subscription_type_id', $customerSubscription->subscription_type_id)
                ->first();

            if (! $template) {
                throw new RuntimeException(
                    'No deployment template for subscription type '.$customerSubscription->subscription_type_id.
                    ' (customer subscription '.$customerSubscription->id.').'
                );
            }

            $source = (string) $template->script;
        }

        if (str_contains($source, '#RELEASE_TAG#') && $target === null) {
            throw new RuntimeException(
                "No target release for subscription {$customerSubscription->id}."
            );
        }

        if ($target !== null && $target->is_draft) {
            throw new RuntimeException(
                "Cannot deploy release {$target->tag} for subscription {$customerSubscription->id}: release is no longer on GitHub (marked draft)."
            );
        }

        $domain = $this->websitePlaceholder($customerSubscription);
        $tag = $target?->tag ?? '';

        $rendered = str_replace(
            ['#WEBSITE_URL#', '#RELEASE_TAG#'],
            [$domain, $tag],
            $source
        );

        $releaseId = $target?->id;

        if (
            $existing
            && $existing->script === $rendered
            && (int) $existing->rendered_release_id === (int) $releaseId
        ) {
            return [$existing, false];
        }

        $script = DeploymentScript::query()->updateOrCreate(
            ['customer_subscription_id' => $customerSubscription->id],
            [
                'script' => $rendered,
                'rendered_release_id' => $releaseId,
                'is_custom' => (bool) ($existing?->is_custom ?? false),
            ]
        );

        return [$script, true];
    }

    private function websitePlaceholder(CustomerSubscription $customerSubscription): string
    {
        if (filled($customerSubscription->domain)) {
            return (string) $customerSubscription->domain;
        }

        $baseUrl = str_replace(['https://', 'http://'], '', (string) $customerSubscription->url);

        return $baseUrl;
    }
}
