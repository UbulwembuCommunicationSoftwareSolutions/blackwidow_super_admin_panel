<?php

namespace App\Http\Controllers\Api;

/**
 * Whitelisted attributes for MCP create/update of Customer (no API keys, S3, or token).
 */
final class McpMutationHelper
{
    /** @var list<string> */
    public const CUSTOMER_SAFE = [
        'company_name',
        'max_users',
        'docket_description',
        'task_description',
        'level_one_description',
        'level_two_description',
        'level_three_description',
        'level_four_description',
        'level_five_description',
        'level_one_in_use',
        'level_two_in_use',
        'level_three_in_use',
    ];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function onlyCustomerSafe(array $data): array
    {
        return array_intersect_key($data, array_flip(self::CUSTOMER_SAFE));
    }
}
