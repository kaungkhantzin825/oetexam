<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attempt;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Student management — port of the student half of controllers/adminController.js.
 */
class StudentController extends Controller
{
    /** GET /admin/students */
    public function index(Request $request): Response
    {
        $students = User::query()
            ->where('role', 'student')
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($inner) => $inner->where('name', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->withCount('attempts')
            ->withAvg(['attempts as avg_band' => fn ($q) => $q->where('status', 'completed')], 'band_score')
            ->withMax(['attempts as best_band' => fn ($q) => $q->where('status', 'completed')], 'band_score')
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (User $u) => [
                'id'            => $u->id,
                'name'          => $u->name,
                'email'         => $u->email,
                'country'       => $u->country,
                'target_band'   => $u->target_band,
                'language'      => $u->language,
                'is_active'     => (bool) $u->is_active,
                'created_at'    => $u->created_at,
                'last_login_at' => $u->last_login_at,
                'attempts'      => $u->attempts_count,
                'avg_band'      => $u->avg_band !== null ? round((float) $u->avg_band, 1) : null,
                'best_band'     => $u->best_band !== null ? (float) $u->best_band : null,
            ]);

        return Inertia::render('Admin/Students', [
            'students' => $students,
            'filters'  => $request->only('q'),
        ]);
    }

    /** POST /admin/students */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'min:2', 'max:120'],
            'email'       => ['required', 'email', 'max:180'],
            'password'    => ['required', 'string', 'min:6'],
            'country'     => ['nullable', 'string', 'max:80'],
            'target_band' => ['nullable', 'numeric', 'min:0', 'max:6'],
            'role'        => ['nullable', Rule::in(['student', 'admin'])],
        ], ['email.email' => 'Valid email required.']);

        if (User::where('email', $data['email'])->exists()) {
            throw ValidationException::withMessages(['email' => 'An account with that email already exists.']);
        }

        User::create([
            'name'        => $data['name'],
            'email'       => $data['email'],
            'password'    => Hash::make($data['password'], ['rounds' => 12]),
            'role'        => ($data['role'] ?? 'student') === 'admin' ? 'admin' : 'student',
            'country'     => $data['country'] ?? null,
            'target_band' => $data['target_band'] ?? null,
        ]);

        return back()->with('success', 'Account created.');
    }

    /** PATCH /admin/students/{user} */
    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'is_active'   => ['sometimes', 'nullable', 'boolean'],
            'role'        => ['sometimes', 'nullable', Rule::in(['student', 'admin'])],
            'name'        => ['sometimes', 'nullable', 'string', 'min:2', 'max:120'],
            'target_band' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:6'],
        ], ['role.in' => 'role must be student or admin.']);

        // Guard: an admin must never demote or deactivate themselves.
        if ($user->id === $request->user()->id && $request->filled('role') && $request->input('role') !== 'admin') {
            throw ValidationException::withMessages(['role' => 'You cannot change your own admin role.']);
        }
        if ($user->id === $request->user()->id && $request->has('is_active') && ! $request->boolean('is_active')) {
            throw ValidationException::withMessages(['is_active' => 'You cannot deactivate your own account.']);
        }

        $updates = [];
        foreach (['role', 'name', 'target_band'] as $field) {
            if ($request->has($field)) {
                $updates[$field] = $data[$field] ?? null;
            }
        }
        if ($request->has('is_active')) {
            $updates['is_active'] = $request->boolean('is_active');
        }

        $user->update($updates);

        return back()->with('success', 'Student updated.');
    }

    /** DELETE /admin/students/{user} */
    public function destroy(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            throw ValidationException::withMessages(['id' => 'You cannot delete your own account.']);
        }

        $user->delete();

        return back()->with('success', 'Account deleted.');
    }

    /** GET /admin/students/{user} — the student detail drawer. */
    public function show(User $user)
    {
        $attempts = Attempt::query()
            ->join('exams', 'exams.id', '=', 'attempts.exam_id')
            ->where('attempts.user_id', $user->id)
            ->orderByDesc('attempts.started_at')
            ->limit(50)
            ->get([
                'attempts.id', 'attempts.exam_id', 'exams.title', 'exams.section',
                'attempts.band_score', 'attempts.total_score', 'attempts.max_score',
                'attempts.status', 'attempts.started_at', 'attempts.finished_at',
                'attempts.time_spent_seconds',
            ]);

        return response()->json([
            'success' => true,
            'data'    => ['student' => $user->toPublicArray(), 'attempts' => $attempts],
        ]);
    }
}
