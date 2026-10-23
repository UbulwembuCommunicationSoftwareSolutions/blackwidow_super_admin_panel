<?php

namespace App\Http\Requests\Api\V1;

class CompanyLevelSyncUpsertRequest extends TenantSyncRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'app_url' => ['required', 'string'],
            'origin' => ['sometimes', 'string', 'in:cms'],
            'level_one_in_use' => ['required', 'boolean'],
            'level_one_description' => ['required', 'string'],
            'level_two_in_use' => ['required', 'boolean'],
            'level_two_description' => ['required', 'string'],
            'level_three_in_use' => ['required', 'boolean'],
            'level_three_description' => ['required', 'string'],
            'level_four_in_use' => ['required', 'boolean'],
            'level_four_description' => ['required', 'string'],
            'level_five_description' => ['required', 'string'],
            'task_description' => ['required', 'string'],
            'docket_description' => ['required', 'string'],
        ];
    }

    /**
     * Fields stored on the customer. Origin and app_url identify the caller
     * and are not columns.
     *
     * @return array<string, mixed>
     */
    public function customerAttributes(): array
    {
        return $this->safe()->only([
            'level_one_in_use',
            'level_one_description',
            'level_two_in_use',
            'level_two_description',
            'level_three_in_use',
            'level_three_description',
            'level_four_in_use',
            'level_four_description',
            'level_five_description',
            'task_description',
            'docket_description',
        ]);
    }
}
