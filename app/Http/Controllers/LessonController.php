<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Models\LessonProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tutorials / lessons — port of controllers/lessonController.js.
 *
 * Every learner-facing field is bilingual (`x` + `x_my`), and the list view
 * attaches the signed-in student's own progress.
 */
class LessonController extends Controller
{
    /** GET /tutorials */
    public function index(Request $request): Response
    {
        $userId = $request->user()->id;
        $category = $request->string('category')->toString();
        $level = $request->string('level')->toString();
        $track = $request->string('track')->toString();
        $search = trim($request->string('search')->toString());

        $lessons = Lesson::query()
            ->leftJoin('lesson_progress as p', function ($join) use ($userId) {
                $join->on('p.lesson_id', '=', 'lessons.id')
                    ->where('p.user_id', '=', $userId);
            })
            ->where('lessons.is_published', 1)
            ->when(in_array($category, Lesson::CATEGORIES, true), fn ($q) => $q->where('lessons.category', $category))
            ->when(in_array($level, Lesson::LEVELS, true), fn ($q) => $q->where('lessons.level', $level))
            ->when(in_array($track, Lesson::TRACKS, true), fn ($q) => $q->where('lessons.track', $track))
            ->when($search !== '', function ($q) use ($search) {
                $term = '%'.$search.'%';
                // Search BOTH languages: a student typing in Burmese must find a
                // lesson whose Myanmar body mentions the term even when the
                // English title does not.
                $q->where(function ($inner) use ($term) {
                    $inner->where('lessons.title', 'like', $term)
                        ->orWhere('lessons.title_my', 'like', $term)
                        ->orWhere('lessons.summary', 'like', $term)
                        ->orWhere('lessons.summary_my', 'like', $term)
                        ->orWhere('lessons.content', 'like', $term)
                        ->orWhere('lessons.content_my', 'like', $term);
                });
            })
            ->orderBy('lessons.order_index')
            ->orderBy('lessons.id')
            ->get([
                'lessons.id', 'lessons.slug', 'lessons.category', 'lessons.track', 'lessons.level',
                'lessons.title', 'lessons.title_my', 'lessons.summary', 'lessons.summary_my',
                'lessons.key_points', 'lessons.key_points_my', 'lessons.duration_minutes',
                'lessons.order_index', 'lessons.is_published', 'lessons.created_at',
                'p.completed', 'p.seconds_spent', 'p.last_viewed_at',
            ])
            ->map(fn ($r) => [
                'id'               => $r->id,
                'slug'             => $r->slug,
                'category'         => $r->category,
                'track'            => $r->track,
                'level'            => $r->level,
                'title'            => $r->title,
                'title_my'         => $r->title_my,
                'summary'          => $r->summary,
                'summary_my'       => $r->summary_my,
                'key_points'       => $this->json($r->key_points, []),
                'key_points_my'    => $this->json($r->key_points_my, []),
                'duration_minutes' => (int) $r->duration_minutes,
                'order_index'      => (int) $r->order_index,
                'has_myanmar'      => filled($r->title_my) || filled($r->content_my ?? null),
                'completed'        => (bool) $r->completed,
                'seconds_spent'    => (int) ($r->seconds_spent ?? 0),
                'last_viewed_at'   => $r->last_viewed_at,
            ]);

        $byCategory = collect(Lesson::CATEGORIES)->mapWithKeys(fn ($c) => [$c => 0])->all();
        foreach ($lessons as $lesson) {
            $byCategory[$lesson['category']] = ($byCategory[$lesson['category']] ?? 0) + 1;
        }

        // Counted over the whole published catalogue, not the filtered slice, so
        // the track switch keeps showing how much sits in the other track.
        $byTrack = Lesson::query()
            ->where('is_published', 1)
            ->select('track')
            ->selectRaw('count(*) as c')
            ->groupBy('track')
            ->pluck('c', 'track')
            ->all();

        return Inertia::render('Student/Tutorials', [
            'lessons'    => $lessons,
            'byCategory' => $byCategory,
            'byTrack'    => $byTrack,
            'total'      => $lessons->count(),
            'completed'  => $lessons->where('completed', true)->count(),
            'filters'    => ['category' => $category, 'level' => $level, 'track' => $track, 'search' => $search],
        ]);
    }

    /** GET /tutorials/{key} — key is a slug, or a numeric id. */
    public function show(Request $request, string $key): Response
    {
        $query = Lesson::query();
        if (ctype_digit($key)) {
            $query->where('id', (int) $key);
        } else {
            $query->where('slug', $key);
        }

        $lesson = $query->first();
        if (! $lesson) {
            abort(404, 'Lesson not found.');
        }

        if (! $lesson->is_published && ! $request->user()->isAdmin()) {
            abort(404, 'Lesson not found.');
        }

        // Neighbours so the reader can move through the course without going back.
        // Scoped to the same track: reading "next" from a General lesson must not
        // drop the student into a radiography lesson they did not choose.
        // NOTE: no row-constructor comparison `(a,b) < (c,d)` — MariaDB 10.4 does
        // not support it, and this project runs on MariaDB via XAMPP.
        $prev = Lesson::query()
            ->where('is_published', 1)
            ->where('track', $lesson->track)
            ->where(function ($q) use ($lesson) {
                $q->where('order_index', '<', $lesson->order_index)
                    ->orWhere(function ($inner) use ($lesson) {
                        $inner->where('order_index', $lesson->order_index)->where('id', '<', $lesson->id);
                    });
            })
            ->orderByDesc('order_index')->orderByDesc('id')
            ->first(['slug', 'title']);

        $next = Lesson::query()
            ->where('is_published', 1)
            ->where('track', $lesson->track)
            ->where(function ($q) use ($lesson) {
                $q->where('order_index', '>', $lesson->order_index)
                    ->orWhere(function ($inner) use ($lesson) {
                        $inner->where('order_index', $lesson->order_index)->where('id', '>', $lesson->id);
                    });
            })
            ->orderBy('order_index')->orderBy('id')
            ->first(['slug', 'title']);

        $progress = LessonProgress::query()
            ->where('user_id', $request->user()->id)
            ->where('lesson_id', $lesson->id)
            ->first();

        return Inertia::render('Student/TutorialDetail', [
            'lesson' => [
                'id'               => $lesson->id,
                'slug'             => $lesson->slug,
                'category'         => $lesson->category,
                'track'            => $lesson->track,
                'level'            => $lesson->level,
                'title'            => $lesson->title,
                'title_my'         => $lesson->title_my,
                'summary'          => $lesson->summary,
                'summary_my'       => $lesson->summary_my,
                'content'          => $lesson->content,
                'content_my'       => $lesson->content_my,
                'key_points'       => $lesson->key_points ?? [],
                'key_points_my'    => $lesson->key_points_my ?? [],
                'duration_minutes' => $lesson->duration_minutes,
                'has_myanmar'      => $lesson->hasMyanmar(),
            ],
            'navigation' => [
                'prev_slug'  => $prev?->slug,
                'prev_title' => $prev?->title,
                'next_slug'  => $next?->slug,
                'next_title' => $next?->title,
            ],
            'progress' => [
                'completed'      => (bool) ($progress->completed ?? false),
                'seconds_spent'  => (int) ($progress->seconds_spent ?? 0),
                'last_viewed_at' => $progress?->last_viewed_at,
            ],
        ]);
    }

    /** POST /lessons/{lesson}/progress — upsert as the student reads. */
    public function saveProgress(Request $request, Lesson $lesson): JsonResponse
    {
        $seconds = max(0, min((int) $request->input('seconds_spent', 0), 86400));
        $completed = $request->boolean('completed');

        $progress = LessonProgress::firstOrNew([
            'user_id'   => $request->user()->id,
            'lesson_id' => $lesson->id,
        ]);

        // GREATEST keeps the longest read time rather than overwriting with a
        // smaller one, and `completed` never flips back to false.
        $progress->completed = $progress->completed || $completed;
        $progress->seconds_spent = max((int) $progress->seconds_spent, $seconds);
        $progress->last_viewed_at = now();
        $progress->save();

        return response()->json([
            'success' => true,
            'data'    => [
                'completed'      => (bool) $progress->completed,
                'seconds_spent'  => (int) $progress->seconds_spent,
                'last_viewed_at' => $progress->last_viewed_at,
            ],
        ]);
    }

    private function json($value, $fallback)
    {
        if ($value === null) {
            return $fallback;
        }
        if (is_array($value)) {
            return $value;
        }

        return json_decode((string) $value, true) ?? $fallback;
    }
}
