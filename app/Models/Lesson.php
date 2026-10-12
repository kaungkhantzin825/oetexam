<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Lesson extends Model
{
    use HasFactory;

    protected $table = 'lessons';

    public const CATEGORIES = [
        'Listening',
        'Reading',
        'Writing',
        'Speaking',
        'Grammar',
        'Vocabulary',
        'ExamStrategy',
    ];

    public const LEVELS = ['beginner', 'intermediate', 'advanced'];

    /**
     * The two tutorial catalogues.
     *
     * `Radiography` is profession-specific practice; `General` is the shared
     * medical paper that every OET candidate sits, regardless of profession.
     * They are separate because the exam content genuinely differs — but a
     * candidate benefits from both.
     */
    public const TRACKS = ['Radiography', 'General'];

    public const DEFAULT_TRACK = 'Radiography';

    protected $fillable = [
        'slug',
        'category',
        'track',
        'level',
        'title',
        'title_my',
        'summary',
        'summary_my',
        'content',
        'content_my',
        'key_points',
        'key_points_my',
        'duration_minutes',
        'order_index',
        'is_published',
        'created_by',
    ];

    protected $casts = [
        'key_points'       => 'array',
        'key_points_my'    => 'array',
        'duration_minutes' => 'integer',
        'order_index'      => 'integer',
        'is_published'     => 'boolean',
        'created_at'       => 'datetime',
        'updated_at'       => 'datetime',
    ];

    public function progress()
    {
        return $this->hasMany(LessonProgress::class);
    }

    /** URL-safe slug, mirroring the Node implementation. */
    public static function makeSlug(?string $input): string
    {
        return Str::limit(
            Str::slug((string) $input),
            150,
            ''
        );
    }

    /** True when either Myanmar column carries content. */
    public function hasMyanmar(): bool
    {
        return filled($this->title_my) || filled($this->content_my);
    }
}
