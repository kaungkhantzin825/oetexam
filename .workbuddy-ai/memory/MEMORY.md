# OET Radiographer — Project Memory

## Project Overview
OET (Occupational English Test) exam prep web app for Myanmar radiography/medical imaging students.
Ported from a Node.js/Express backend to **Laravel 10 + Inertia.js + Vue 3 + TailwindCSS + Pinia + Chart.js**.
Bilingual: English (primary) + Burmese/Myanmar (မြန်မာစာ). All content has `x` + `x_my` pairs.

## Tech Stack
- **Backend:** Laravel 10 (PHP 8.1+), MariaDB via XAMPP, Sanctum (session-based auth, httpOnly cookie)
- **Frontend:** Vue 3 SFCs via Inertia (SPA, no separate API client needed for page loads), Vite, TailwindCSS
- **AI:** Google Gemini (primary, supports vision) → Mistral AI (text-only fallback), with shared wall-clock Budget
- **Icons:** lucide-vue-next | **Charts:** Chart.js + vue-chartjs

## Key Architecture Decisions
- Inertia renders pages server-side → Vue (no JSON API for page data; XHR only for exam save/submit + AI endpoints)
- `Question::toPlayerArray()` omits correct_answer/explanation — browser never sees marking scheme
- `Marking` service shared between submit() and result() — marking and review never disagree
- MCQ correct_answer stored as 0-based index string ("0"=A), resolved via `Marking::resolveMcqIndex()`
- OET band: 0.0-6.0 in 0.5 steps, mapped from percentage via `Marking::percentageToBand()`
- AI fallback chain: Gemini → Mistral → deterministic heuristic (writing/speaking) — never blocks exam
- `Budget` class bounds total AI request time (default 45s) to stay under PHP max_execution_time (60s)
- `SafeRedirect` prevents open-redirect / mixed-content (http→https) issues behind TLS-terminating proxy
- Writing answers can be typed OR photographed (handwritten) → Gemini vision transcribes + marks in one call
- Lessons have two tracks: `Radiography` (profession-specific) and `General` (shared medical paper)

## Database Tables
users, exams, questions, attempts, answers, ai_logs, lessons, lesson_progress, v_student_leaderboard (VIEW)

## Routes (web.php)
- Public: /, /about
- Auth: /login, /register, /logout
- Student: /dashboard, /history, /profile, /practice, /practice/{exam}, /attempts/{id}/save|submit|result|writing-image, /tutorials, /tutorials/{key}, /ai/status|explain|score-writing|score-speaking
- Admin (/admin): dashboard, exams (CRUD + stats + audio upload), questions (CRUD + bulk), students (CRUD), tutorials (CRUD), results, ai-logs, ai/generate-questions|generate-mock|generate-lesson|translate

## Middleware
- `auth` + `active` (EnsureAccountIsActive — logs out deactivated users)
- `admin` (EnsureUserIsAdmin)
- HandleInertiaRequests shares auth user, flash messages, app name + AI flag

## Config
- `config/ai.php` — Gemini/Mistral keys, model chains, budget, writing image max_kb
- Models: gemini-3.5-flash (primary), fallback chain; Mistral open-mistral-nero + fallbacks

## Important Files
- `app/Services/Marking.php` — MCQ resolution, short-answer comparison, band conversion
- `app/Services/Ai/AiManager.php` — all AI domain prompts (question gen, writing/speaking marking, lessons, translate)
- `app/Services/Ai/Budget.php` — shared wall-clock budget for AI chain
- `app/Services/Ai/Json.php` — tolerant JSON parsing for LLM output (strips fences, repairs truncation)
- `app/Support/SafeRedirect.php` — open-redirect guard

## Frontend Structure
- `resources/js/Pages/` — Home, About, NotFound, Auth/{Login,Register}, Student/{Dashboard,Practice,ExamPlayer,Result,History,Profile,Tutorials,TutorialDetail}, Admin/{Dashboard,Exams,Questions,Students,Tutorials,Results,AiLogs}
- `resources/js/components/` — Navbar, Footer, QuestionCard, QuestionReview, AudioPlayer, WritingPhotoUpload, Markdown, BilingualText/Markdown, charts, ui/ (modal, pagination, badges, etc.)
- `resources/js/lib/` — i18n (hand-rolled, EN+MY), http, auth, toast, format, langMode, lessonCategories
- `resources/js/locales/` — en.js, my.js
