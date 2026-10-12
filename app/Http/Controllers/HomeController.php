<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\Lesson;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

/** Public marketing pages. */
class HomeController extends Controller
{
    /** GET / */
    public function index(): Response
    {
        return Inertia::render('Home', [
            'stats' => [
                'exams'     => Exam::where('is_published', 1)->count(),
                'questions' => \App\Models\Question::where('is_active', 1)->count(),
                'lessons'   => Lesson::where('is_published', 1)->count(),
                'students'  => User::where('role', 'student')->count(),
            ],
            'sections' => collect(Exam::SECTIONS)->map(fn (string $section) => [
                'section' => $section,
                'count'   => Exam::where('section', $section)->where('is_published', 1)->count(),
            ]),
        ]);
    }

    /** GET /about */
    public function about(): Response
    {
        return Inertia::render('About');
    }
}
