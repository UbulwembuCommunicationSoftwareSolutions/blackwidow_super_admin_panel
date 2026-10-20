<?php

declare(strict_types=1);

namespace App\Models\Reporting\Aims;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dashboard extends Model
{
    use HasFactory;

    public const HANDLER_AIMS_CASES = 'aims_cases';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'subscription_type_id',
        'handler',
    ];

    protected function casts(): array
    {
        return [
            'subscription_type_id' => 'integer',
        ];
    }

    public function customerDashboards(): HasMany
    {
        return $this->hasMany(CustomerDashboard::class);
    }
}
