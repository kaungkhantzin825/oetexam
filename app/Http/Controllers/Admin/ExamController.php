<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\Question;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin exam CRUD + the audio upload used by Listening questions.
 * Port of controllers/examAdminController.js.
 */
class ExamController extends Controller
{
    /** GET /admin/exams */
    public function index(Request $request): Response
    {
        $exams = Exam::query()
            ->withCount(['activeQuestions as question_count'])
            ->when($request->filled('section'), fn ($q) => $q->where('section', $request->string('section')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($inner) => $inner->where('title', 'like', $term)->orWhere('description', 'like', $term));
            })
            ->orderByRaw(Exam::sectionOrder())
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('Admin/Exams', [
            'exams'   => $exams,
            'filters' => $request->only(['section', 'q']),
        ]);
    }

    /** POST /admin/exams */
    public function store(Request $request)
    {
        $data = $request->validate([
            'title'            => ['required', 'string', 'min:3', 'max:200'],
            'description'      => ['nullable', 'string'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'section'          => ['required', Rule::in(Exam::SECTIONS)],
            'difficulty'       => ['nullable', Rule::in(Exam::DIFFICULTIES)],
            'is_published'     => ['nullable', 'boolean'],
        ], ['section.in' => 'Invalid section.']);

        $exam = Exam::create([
            'title'            => $data['title'],
            'description'      => $data['description'] ?? null,
            'duration_minutes' => $data['duration_minutes'] ?? 60,
            'section'          => $data['section'],
            'difficulty'       => $data['difficulty'] ?? 'medium',
            'is_published'     => (bool) ($data['is_published'] ?? false),
            'created_by'       => $request->user()->id,
        ]);

        return back()->with('success', 'Exam created.')->setStatusCode(201);
    }

    /** PUT /admin/exams/{exam} */
    public function update(Request $request, Exam $exam)
    {
        $data = $request->validate([
            'title'            => ['sometimes', 'nullable', 'string', 'min:3', 'max:200'],
            'description'      => ['sometimes', 'nullable', 'string'],
            'duration_minutes' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:600'],
            'section'          => ['sometimes', 'nullable', Rule::in(Exam::SECTIONS)],
            'difficulty'       => ['sometimes', 'nullable', Rule::in(Exam::DIFFICULTIES)],
            'is_published'     => ['sometimes', 'nullable', 'boolean'],
        ]);

        $updates = [];
        foreach (['title', 'description', 'duration_minutes', 'section', 'difficulty'] as $field) {
            if ($request->has($field)) {
                $updates[$field] = $data[$field] ?? null;
            }
        }
        if ($request->has('is_published')) {
            $updates['is_published'] = (bool) $request->input('is_published');
        }

        $exam->update($updates);

        return back()->with('success', 'Exam updated.');
    }

    /** PATCH /admin/exams/{exam}/publish */
    public function togglePublish(Request $request, Exam $exam)
    {
        $publish = $request->has('is_published') ? $request->boolean('is_published') : true;

        if ($publish && $exam->activeQuestions()->count() === 0) {
            return back()->with('error', 'Add at least one question before publishing this exam.');
        }

        $exam->update(['is_published' => $publish]);

        return back()->with('success', $publish ? 'Exam published — students can now see it.' : 'Exam unpublished.');
    }

    /** DELETE /admin/exams/{exam} */
    public function destroy(Exam $exam)
    {
        // Cascades to questions/attempts via foreign keys.
        $exam->delete();

        return back()->with('success', 'Exam deleted.');
    }

    /**
     * POST /admin/uploads/audio  (multipart, field name "audio")
     * Returns the relative path to store in questions.audio_url.
     */
    public function uploadAudio(Request $request): JsonResponse
    {
        $request->validate([
            'audio' => ['required', 'file', 'max:'.((int) env('OET_UPLOADS_MAX_MB', 25) * 1024)],
        ], ['audio.required' => 'No audio file received. Use field name "audio".']);

        $file = $request->file('audio');
        $name = 'q'.now()->format('YmdHis').'-'.substr(sha1(uniqid('', true)), 0, 8).'.'.$file->getClientOriginalExtension();
        $file->move(public_path('uploads/listening'), $name);

        $relative = 'listening/'.$name;

        return response()->json([
            'success' => true,
            'message' => 'Audio uploaded.',
            'data'    => [
                'audio_url'     => $relative,
                'url'           => '/uploads/'.$relative,
                'original_name' => $file->getClientOriginalName(),
                'size'          => $file->getSize(),
                'mimetype'      => $file->getClientMimeType(),
            ],
        ], 201);
    }

    /** GET /admin/exams/{exam}/stats */
    public function stats(Exam $exam): JsonResponse
    {
        $stats = $exam->attempts()
            ->where('status', 'completed')
            ->selectRaw('COUNT(*) as attempts, ROUND(AVG(band_score),1) as avg_band, MAX(band_score) as best_band, MIN(band_score) as worst_band')
            ->first();

        $byType = Question::query()
            ->where('exam_id', $exam->id)
            ->where('is_active', 1)
            ->groupBy('question_type')
            ->selectRaw('question_type, COUNT(*) as n')
            ->get();

        return response()->json(['success' => true, 'data' => ['stats' => $stats, 'by_type' => $byType]]);
    }

    /** Recalculate and cache an exam's total marks from its active questions. */
    public static function refreshTotals(Exam $exam): int
    {
        $total = (int) $exam->activeQuestions()->sum('marks');
        $exam->update(['total_marks' => $total, 'pass_marks' => (int) round($total * 0.5)]);

        return $total;
    }
}
