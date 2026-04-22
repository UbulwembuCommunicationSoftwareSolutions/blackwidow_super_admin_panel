<?php

namespace App\Models;

use Database\Factories\TemplateEnvVariablesFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplateEnvVariables extends Model
{
    use HasFactory;

    protected $table = 'required_env_variables';

    protected static function newFactory(): TemplateEnvVariablesFactory
    {
        return TemplateEnvVariablesFactory::new();
    }

    public $fillable = [
        'subscription_type_id',
        'key',
        'value',
        'requires_manual_fill',
        'admin_label',
        'help_text',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'requires_manual_fill' => 'boolean',
        ];
    }

    /**
     * Value stored on the per-subscription EnvVariables row when the row is created from this template.
     * Manual keys start unset until an operator fills them in Filament.
     */
    public function initialEnvValue(): ?string
    {
        return $this->requires_manual_fill ? null : $this->value;
    }

    public function subscriptionType(): BelongsTo
    {
        return $this->belongsTo(SubscriptionType::class);
    }
}
