<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attempt extends Model
{
    use HasFactory;

    protected $table = 'attempts';

    /** The table only has `created_at` — no `updated_at`. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'exam_id',
        'started_at',
        'finished_at',
        'time_spent_seconds',
        'total_score',
        'max_score',
        'band_score',
        'section_scores',
        'status',
    ];

    protected $casts = [
        'section_scores'     => 'array',
        'started_at'         => 'datetime',
        'finished_at'        => 'datetime',
        'time_spent_seconds' => 'integer',
        'total_score'        => 'float',
        'max_score'          => 'float',
        'band_score'         => 'float',
        'created_at'         => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function answers()
    {
        return $this->hasMany(Answer::class);
    }
}
