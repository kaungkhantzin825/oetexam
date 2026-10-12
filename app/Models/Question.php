<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    use HasFactory;

    protected $table = 'questions';

    public const TYPES = ['MCQ', 'ShortAnswer', 'Writing', 'RolePlay'];

    protected $fillable = [
        'exam_id',
        'section',
        'question_type',
        'part',
        'order_index',
        'question_text',
        'options',
        'correct_answer',
        'audio_url',
        'audio_script',
        'model_answer',
        'model_answer_my',
        'role_play_card',
        'marks',
        'explanation',
        'explanation_my',
        'is_active',
    ];

    /**
     * `options` and `role_play_card` are JSON columns. Casting them to arrays
     * means Eloquent encodes on write and decodes on read, so controllers never
     * have to call json_decode()/json_encode() by hand.
     */
    protected $casts = [
        'options'        => 'array',
        'role_play_card' => 'array',
        'order_index'    => 'integer',
        'marks'          => 'integer',
        'is_active'      => 'boolean',
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
    ];

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function answers()
    {
        return $this->hasMany(Answer::class);
    }

    /**
     * The safe projection for a student sitting the exam.
     *
     * Deliberately omits `correct_answer`, `explanation`, `explanation_my`,
     * `model_answer`, `model_answer_my` and `audio_script` — the browser must
     * never be able to reveal the marking scheme.
     */
    public function toPlayerArray(): array
    {
        return [
            'id'             => $this->id,
            'exam_id'        => $this->exam_id,
            'section'        => $this->section,
            'question_type'  => $this->question_type,
            'part'           => $this->part,
            'order_index'    => $this->order_index,
            'question_text'  => $this->question_text,
            'options'        => $this->options,
            'audio_url'      => $this->audio_url,
            'role_play_card' => $this->role_play_card,
            'marks'          => $this->marks,
        ];
    }
}
