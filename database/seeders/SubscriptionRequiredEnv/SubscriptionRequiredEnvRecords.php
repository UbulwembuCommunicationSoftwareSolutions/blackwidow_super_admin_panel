<?php

namespace Database\Seeders\SubscriptionRequiredEnv;

use App\Models\TemplateEnvVariables;

final class SubscriptionRequiredEnvRecords
{
    /**
     * @param  list<array{key: string, value: string}>  $rows
     */
    public static function sync(int $subscriptionTypeId, array $rows): void
    {
        foreach ($rows as $row) {
            TemplateEnvVariables::updateOrCreate(
                [
                    'subscription_type_id' => $subscriptionTypeId,
                    'key' => $row['key'],
                ],
                [
                    'value' => $row['value'],
                    'requires_manual_fill' => false,
                ]
            );
        }
    }
}
