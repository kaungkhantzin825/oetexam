<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AiController;
use App\Http\Controllers\AttemptController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LessonController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes — Laravel 10 + Inertia + Vue 3
|--------------------------------------------------------------------------
|
| Every page is an Inertia response; the handful of JSON endpoints are the
| exam auto-save/submit calls and the AI features, which are XHR-only.
|
| Route ordering note (inherited from the Express version): literal paths are
| declared BEFORE their `{param}` siblings, or the param route swallows them.
|
*/

// ---- Public ---------------------------------------------------------------
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/about', [HomeController::class, 'about'])->name('about');

// ---- Guest only -----------------------------------------------------------
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

// ---- Signed in ------------------------------------------------------------
Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/history', [DashboardController::class, 'history'])->name('history');
    Route::get('/profile', [DashboardController::class, 'profile'])->name('profile');
    Route::put('/profile', [AuthController::class, 'updateProfile'])->name('profile.update');
    Route::put('/profile/password', [AuthController::class, 'changePassword'])->name('profile.password');

    // ---- Practice / exam player -------------------------------------------
    Route::get('/practice', [ExamController::class, 'index'])->name('practice');
    Route::get('/practice/{exam}', [ExamController::class, 'play'])->name('practice.play');

    // ---- Exam engine (JSON: auto-save, submit, then the review page) -------
    Route::post('/attempts/{attempt}/save', [AttemptController::class, 'save'])->name('attempts.save');
    // Photographed hand-written Writing answers. The literal "writing-image"
    // segment is declared before any {param} sibling would be able to swallow it.
    Route::post('/attempts/{attempt}/writing-image', [AttemptController::class, 'uploadWritingImage'])->name('attempts.writing-image');
    Route::delete('/attempts/{attempt}/writing-image/{question}', [AttemptController::class, 'destroyWritingImage'])->name('attempts.writing-image.destroy');
    Route::post('/attempts/{attempt}/submit', [AttemptController::class, 'submit'])->name('attempts.submit');
    Route::get('/attempts/{attempt}/result', [AttemptController::class, 'result'])->name('attempts.result');

    // ---- Tutorials ---------------------------------------------------------
    Route::get('/tutorials', [LessonController::class, 'index'])->name('tutorials');
    Route::post('/lessons/{lesson}/progress', [LessonController::class, 'saveProgress'])->name('lessons.progress');
    Route::get('/tutorials/{key}', [LessonController::class, 'show'])->name('tutorials.show');

    // ---- AI (student-facing) ----------------------------------------------
    Route::get('/ai/status', [AiController::class, 'status'])->name('ai.status');
    Route::post('/ai/explain', [AiController::class, 'explain'])->name('ai.explain');
    Route::post('/ai/score-writing', [AiController::class, 'scoreWriting'])->name('ai.score-writing');
    Route::post('/ai/score-speaking', [AiController::class, 'scoreSpeaking'])->name('ai.score-speaking');
});

// ---- Admin ----------------------------------------------------------------
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/ai-logs', [Admin\DashboardController::class, 'aiLogs'])->name('ai-logs');
    Route::get('/results', [Admin\DashboardController::class, 'results'])->name('results');

    // Exams
    Route::get('/exams', [Admin\ExamController::class, 'index'])->name('exams');
    Route::post('/exams', [Admin\ExamController::class, 'store'])->name('exams.store');
    Route::put('/exams/{exam}', [Admin\ExamController::class, 'update'])->name('exams.update');
    Route::patch('/exams/{exam}/publish', [Admin\ExamController::class, 'togglePublish'])->name('exams.publish');
    Route::delete('/exams/{exam}', [Admin\ExamController::class, 'destroy'])->name('exams.destroy');
    Route::get('/exams/{exam}/stats', [Admin\ExamController::class, 'stats'])->name('exams.stats');
    Route::post('/uploads/audio', [Admin\ExamController::class, 'uploadAudio'])->name('uploads.audio');

    // Questions
    Route::get('/questions', [Admin\QuestionController::class, 'index'])->name('questions');
    Route::post('/questions', [Admin\QuestionController::class, 'store'])->name('questions.store');
    Route::post('/questions/bulk', [Admin\QuestionController::class, 'bulk'])->name('questions.bulk');
    Route::get('/questions/{question}', [Admin\QuestionController::class, 'show'])->name('questions.show');
    Route::put('/questions/{question}', [Admin\QuestionController::class, 'update'])->name('questions.update');
    Route::delete('/questions/{question}', [Admin\QuestionController::class, 'destroy'])->name('questions.destroy');

    // Students
    Route::get('/students', [Admin\StudentController::class, 'index'])->name('students');
    Route::post('/students', [Admin\StudentController::class, 'store'])->name('students.store');
    Route::get('/students/{user}', [Admin\StudentController::class, 'show'])->name('students.show');
    Route::patch('/students/{user}', [Admin\StudentController::class, 'update'])->name('students.update');
    Route::delete('/students/{user}', [Admin\StudentController::class, 'destroy'])->name('students.destroy');

    // Tutorials
    Route::get('/tutorials', [Admin\LessonController::class, 'index'])->name('tutorials');
    Route::post('/tutorials', [Admin\LessonController::class, 'store'])->name('tutorials.store');
    Route::put('/tutorials/{lesson}', [Admin\LessonController::class, 'update'])->name('tutorials.update');
    Route::patch('/tutorials/{lesson}/publish', [Admin\LessonController::class, 'togglePublish'])->name('tutorials.publish');
    Route::delete('/tutorials/{lesson}', [Admin\LessonController::class, 'destroy'])->name('tutorials.destroy');

    // AI content generation
    Route::post('/ai/generate-questions', [Admin\AiController::class, 'generateQuestions'])->name('ai.generate-questions');
    Route::post('/ai/generate-mock', [Admin\AiController::class, 'generateMock'])->name('ai.generate-mock');
    Route::post('/ai/generate-lesson', [Admin\AiController::class, 'generateLesson'])->name('ai.generate-lesson');
    Route::post('/ai/translate', [Admin\AiController::class, 'translate'])->name('ai.translate');
});

// ---- 404 ------------------------------------------------------------------
Route::fallback(fn () => Inertia::render('NotFound'))->name('not-found');
