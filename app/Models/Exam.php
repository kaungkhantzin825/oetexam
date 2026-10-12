<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Exam extends Model
{
    use HasFactory;

    protected $table = 'exams';

    public const SECTIONS = ['Listening', 'Reading', 'Writing', 'Speaking', 'Full'];
    public const DIFFICULTIES = ['easy', 'medium', 'hard'];

    protected $fillable = [
        'title',
        'description',
        'duration_minutes',
        'section',
        'difficulty',
        'pass_marks',
        'total_marks',
        'is_published',
        'created_by',
    ];

    protected $casts = [
        'duration_minutes' => 'integer',
        'pass_marks'       => 'integer',
        'total_marks'      => 'integer',
        'is_published'     => 'boolean',
        'created_at'       => 'datetime',
        'updated_at'       => 'datetime',
    ];

    public function questions()
    {
        return $this->hasMany(Question::class);
    }

    public function activeQuestions()
    {
        return $this->hasMany(Question::class)->where('is_active', 1);
    }

    public function attempts()
    {
        return $this->hasMany(Attempt::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Ordered exactly like the Node version: Listening → Reading → Writing → Speaking → Full. */
    public static function sectionOrder(): string
    {
        return "FIELD(section, 'Listening','Reading','Writing','Speaking','Full')";
    }
}
