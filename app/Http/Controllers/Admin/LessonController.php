<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin tutorial management — port of the admin half of controllers/lessonController.js.
 */
class LessonController extends Controller
{
    /** GET /admin/tutorials */
    public function index(): Response
    {
        $lessons = Lesson::query()
            ->orderBy('order_index')
            ->orderBy('id')
            ->get()
            ->map(fn (Lesson $l) => [
                'id'                   => $l->id,
                'slug'                 => $l->slug,
                'category'             => $l->category,
                'track'                => $l->track,
                'level'                => $l->level,
                'title'                => $l->title,
                'title_my'             => $l->title_my,
                'summary'              => $l->summary,
                'summary_my'           => $l->summary_my,
                'content'              => $l->content,
                'content_my'           => $l->content_my,
                'key_points'           => $l->key_points ?? [],
                'key_points_my'        => $l->key_points_my ?? [],
                'duration_minutes'     => $l->duration_minutes,
                'order_index'          => $l->order_index,
                'is_published'         => (bool) $l->is_published,
                'has_myanmar'          => $l->hasMyanmar(),
                'translation_complete' => filled($l->content_my),
                'created_at'           => $l->created_at,
            ]);

        return Inertia::render('Admin/Tutorials', [
            'lessons' => $lessons,
            'total'   => $lessons->count(),
        ]);
    }

    /** POST /admin/tutorials */
    public function store(Request $request)
    {
        $data = $this->normaliseInput($request->all());

        // Generate a unique slug from the title when one was not supplied.
        $slug = $data['slug'] ?? Lesson::makeSlug($data['title']);
        if ($slug === '') {
            $slug = 'lesson-'.now()->timestamp;
        }

        $suffix = 1;
        $base = Lesson::makeSlug($data['title']);
        while (Lesson::where('slug', $slug)->exists()) {
            $suffix++;
            $slug = mb_substr($base, 0, 140).'-'.$suffix;
        }

        $data['slug'] = $slug;
        $data['created_by'] = $request->user()->id;

        Lesson::create($data);

        return back()->with('success', 'Lesson created.');
    }

    /** PUT /admin/tutorials/{lesson} */
    public function update(Request $request, Lesson $lesson)
    {
        $data = $this->normaliseInput($request->all(), partial: true);

        if (empty($data)) {
            throw ValidationException::withMessages(['title' => 'Nothing to update.']);
        }

        if (! empty($data['slug'])) {
            $clash = Lesson::where('slug', $data['slug'])->where('id', '!=', $lesson->id)->exists();
            if ($clash) {
                throw ValidationException::withMessages(['slug' => 'Another lesson already uses that slug.']);
            }
        }

        $lesson->update($data);

        return back()->with('success', 'Lesson updated.');
    }

    /** PATCH /admin/tutorials/{lesson}/publish */
    public function togglePublish(Request $request, Lesson $lesson)
    {
        $next = $request->has('is_published')
            ? $request->boolean('is_published')
            : ! $lesson->is_published;

        $lesson->update(['is_published' => $next]);

        return back()->with('success', $next ? 'Lesson published.' : 'Lesson unpublished.');
    }

    /** DELETE /admin/tutorials/{lesson} */
    public function destroy(Lesson $lesson)
    {
        // lesson_progress rows cascade via the foreign key.
        $lesson->delete();

        return back()->with('success', 'Lesson deleted.');
    }

    /** GET /admin/tutorials/stats — translation coverage, used by the admin header. */
    public function stats(): array
    {
        return [
            'total'        => Lesson::count(),
            'published'    => Lesson::where('is_published', 1)->count(),
            'translated'   => Lesson::whereNotNull('content_my')->where('content_my', '!=', '')->count(),
            'by_category'  => DB::table('lessons')
                ->groupBy('category')
                ->selectRaw('category, COUNT(*) as count')
                ->pluck('count', 'category'),
        ];
    }

    // -----------------------------------------------------------------------

    /** Pick the allowed fields from a request body, ignoring anything else. */
    private function normaliseInput(array $body, bool $partial = false): array
    {
        $out = [];
        $set = function (string $key, $value) use (&$out) {
            if ($value !== null) {
                $out[$key] = $value;
            }
        };

        if (! $partial || array_key_exists('title', $body)) {
            $title = trim((string) ($body['title'] ?? ''));
            if ($title === '') {
                throw ValidationException::withMessages(['title' => 'A lesson title is required.']);
            }
            $out['title'] = mb_substr($title, 0, 200);
        }

        if (array_key_exists('title_my', $body)) {
            $set('title_my', mb_substr(trim((string) $body['title_my']), 0, 255) ?: null);
        }
        if (array_key_exists('summary', $body)) {
            $set('summary', trim((string) $body['summary']) ?: null);
        }
        if (array_key_exists('summary_my', $body)) {
            $set('summary_my', trim((string) $body['summary_my']) ?: null);
        }

        if (! $partial || array_key_exists('content', $body)) {
            $content = trim((string) ($body['content'] ?? ''));
            if ($content === '') {
                throw ValidationException::withMessages(['content' => 'Lesson content cannot be empty.']);
            }
            $out['content'] = $content;
        }
        if (array_key_exists('content_my', $body)) {
            $set('content_my', ((string) $body['content_my']) ?: null);
        }

        if (in_array($body['category'] ?? null, Lesson::CATEGORIES, true)) {
            $out['category'] = $body['category'];
        } elseif (! $partial) {
            $out['category'] = 'ExamStrategy';
        }

        if (in_array($body['track'] ?? null, Lesson::TRACKS, true)) {
            $out['track'] = $body['track'];
        } elseif (! $partial) {
            $out['track'] = Lesson::DEFAULT_TRACK;
        }

        if (in_array($body['level'] ?? null, Lesson::LEVELS, true)) {
            $out['level'] = $body['level'];
        } elseif (! $partial) {
            $out['level'] = 'beginner';
        }

        if (array_key_exists('duration_minutes', $body)) {
            $out['duration_minutes'] = max(1, min((int) $body['duration_minutes'] ?: 10, 600));
        }
        if (array_key_exists('order_index', $body)) {
            $out['order_index'] = max(0, (int) $body['order_index']);
        }
        if (array_key_exists('is_published', $body)) {
            $out['is_published'] = (bool) $body['is_published'];
        }
        if (isset($body['key_points']) && is_array($body['key_points'])) {
            $out['key_points'] = array_slice($body['key_points'], 0, 30);
        }
        if (isset($body['key_points_my']) && is_array($body['key_points_my'])) {
            $out['key_points_my'] = array_slice($body['key_points_my'], 0, 30);
        }
        if (! empty($body['slug'])) {
            $out['slug'] = Lesson::makeSlug($body['slug']);
        }

        return $out;
    }
}
