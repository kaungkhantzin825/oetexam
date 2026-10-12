<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Answer extends Model
{
    use HasFactory;

    protected $table = 'answers';

    protected $fillable = [
        'attempt_id',
        'question_id',
        'user_answer',
        'image_path',
        'image_transcript',
        'is_correct',
        'score',
        'max_score',
        'is_marked',
        'time_spent_seconds',
        'ai_feedback',
    ];

    /**
     * `is_correct` is tri-state: null = not auto-marked (Writing/Speaking),
     * true = correct, false = wrong. The boolean cast preserves null.
     */
    protected $casts = [
        'is_correct'         => 'boolean',
        'is_marked'          => 'boolean',
        'score'              => 'float',
        'max_score'          => 'float',
        'time_spent_seconds' => 'integer',
        'created_at'         => 'datetime',
        'updated_at'         => 'datetime',
    ];

    public function attempt()
    {
        return $this->belongsTo(Attempt::class);
    }

    public function question()
    {
        return $this->belongsTo(Question::class);
    }
}
