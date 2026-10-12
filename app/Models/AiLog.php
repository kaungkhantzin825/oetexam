<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiLog extends Model
{
    protected $table = 'ai_logs';

    /** Only `created_at` exists on this table. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'feature',
        'model',
        'prompt',
        'response',
        'tokens',
        'latency_ms',
        'success',
        'error',
    ];

    protected $casts = [
        'success'    => 'boolean',
        'tokens'     => 'integer',
        'latency_ms' => 'integer',
        'created_at' => 'datetime',
    ];
}
