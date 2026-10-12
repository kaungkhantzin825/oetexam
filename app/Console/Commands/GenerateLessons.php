<?php

namespace App\Console\Commands;

use App\Models\Lesson;
use App\Models\User;
use App\Services\Ai\AiException;
use App\Services\Ai\AiManager;
use App\Support\LessonPlan;
use Illuminate\Console\Command;
use Throwable;

/**
 * Generate the bilingual Tutorials catalogue with the AI facade.
 *
 * Ported from the pre-migration `npm run gen:lessons` workflow: it runs the AI
 * facade in-process (so there is no HTTP request timeout to fight) and is
 * **idempotent** — the slug is derived from the curated plan title, so a lesson
 * that already exists is skipped and re-running after a quota reset only fills
 * the gaps.
 *
 * It walks two independent tracks (see LessonPlan):
 *
 *   Radiography  profession-specific practice
 *   General      the shared OET paper, profession-neutral
 *
 * The track is passed to the AI facade, which swaps the examiner persona — a
 * General lesson must not drift back into imaging vocabulary.
 *
 * Quota exhaustion is treated as "stop, do not fail": the free Gemini tier is
 * metered per model per day, so the run ends with a clear report and you simply
 * run it again later.
 */
class GenerateLessons extends Command
{
    protected $signature = 'lessons:generate
        {--track= : Comma-separated tracks — Radiography, General (default: both)}
        {--category= : Comma-separated categories (default: the four sub-tests)}
        {--limit=0 : Maximum lessons to create per category (0 = the whole plan)}
        {--only= : Only topics whose title contains this text}
        {--dry : Print the plan and exit without calling the AI}
        {--force : Regenerate even when the slug already exists}
        {--short=0 : Also regenerate existing lessons whose English body is shorter than this many characters}
        {--budget=180 : Wall-clock seconds allowed per lesson (CLI runs have no execution limit)}
        {--author= : User id to record as created_by (default: the first admin)}
        {--sleep=1 : Seconds to pause between AI calls}';

    protected $description = 'Generate bilingual OET tutorial lessons (English + Myanmar) from the curriculum plan';

    public function handle(AiManager $ai): int
    {
        $categories = $this->resolveCategories();
        if ($categories === []) {
            $this->error('No known category matched --category. Valid: '.implode(', ', LessonPlan::CATEGORIES));

            return self::FAILURE;
        }

        $tracks = $this->resolveTracks();
        if ($tracks === []) {
            $this->error('No known track matched --track. Valid: '.implode(', ', LessonPlan::TRACKS));

            return self::FAILURE;
        }

        $limit = max(0, (int) $this->option('limit'));
        $only = trim((string) $this->option('only'));
        $force = (bool) $this->option('force');
        $shortThreshold = max(0, (int) $this->option('short'));
        $sleep = max(0, (int) $this->option('sleep'));
        $dry = (bool) $this->option('dry');
        $authorId = $this->resolveAuthorId();

        /*
         * A full bilingual lesson is a big completion — 6-13k characters across
         * two languages, which the model can take 20-40s to write. The default
         * 45s budget exists to protect a *web* request from outliving PHP's
         * execution limit; this command runs in the CLI where that limit is 0,
         * so it raises the ceiling rather than letting healthy lessons fail.
         */
        $budget = max(30, (int) $this->option('budget'));
        config([
            'ai.total_budget_seconds' => max((int) config('ai.total_budget_seconds', 45), $budget),
            // The per-call ceiling also has to move, or the budget is irrelevant:
            // the slice is min(provider timeout, remaining budget).
            'ai.gemini.timeout'  => $budget,
            'ai.mistral.timeout' => $budget,
        ]);

        if (! $dry && ! $ai->isConfigured()) {
            $this->error('No AI provider is configured. Set GEMINI_API_KEY (and optionally MISTRAL_API_KEY) in .env.');

            return self::FAILURE;
        }

        if (! $dry) {
            $status = $ai->providerStatus();
            $this->line(sprintf(
                'Providers: %s  (fallback armed: %s)',
                implode(', ', array_map(
                    fn ($p) => $p['name'].($p['configured'] ? '' : ' [not configured]'),
                    $status['providers']
                )),
                $status['fallback_armed'] ? 'yes' : 'no'
            ));
        }

        $created = 0;
        $skipped = 0;
        $failed = 0;
        $quotaHit = false;
        $incomplete = [];

        foreach ($tracks as $track) {
            $this->newLine();
            $this->info(sprintf('════ %s track ════', $track));

            foreach ($categories as $category) {
                $topics = LessonPlan::forCategory($category, $track);
                $base = LessonPlan::orderBase($category, $track);
                // Counts only lessons this run generates (or would, under --dry), so
                // `--limit` means "create N new lessons", not "examine N plan rows".
                $written = 0;

                // Scoped to the track: the two catalogues are independent, and a
                // radiography lesson must never suppress a general one.
                $existing = Lesson::where('category', $category)->where('track', $track)->pluck('slug')->all();
                $existingLookup = array_flip($existing);

                $this->newLine();
                $this->info(sprintf('── %s · %s ── %d in the plan, %d already in the database', $track, $category, count($topics), count($existing)));

                foreach ($topics as $index => $topic) {
                    if ($limit > 0 && $written >= $limit) {
                        break;
                    }

                    $slug = LessonPlan::slugFor($topic['title']);

                    if ($only !== '' && stripos($topic['title'], $only) === false) {
                        continue;
                    }

                    // Hand-authored lessons are never regenerated — not even with
                    // --force, which would replace curated content with machine output.
                    if (LessonPlan::isHandWritten($topic['title'])) {
                        $this->line(sprintf('  <fg=gray>keep</>   %s  <fg=gray>(hand-written)</>', $topic['title']));
                        $skipped++;

                        continue;
                    }

                    if (isset($existingLookup[$slug]) && ! $force) {
                        // A model that answers briefly leaves a technically valid but
                        // thin lesson. --short lets those be topped up without
                        // regenerating the whole catalogue.
                        $isThin = $shortThreshold > 0
                            && mb_strlen((string) Lesson::where('slug', $slug)->value('content')) < $shortThreshold;

                        if (! $isThin) {
                            $this->line(sprintf('  <fg=gray>skip</>   %s', $topic['title']));
                            $skipped++;

                            continue;
                        }

                        $this->line(sprintf('  <fg=yellow>thin</>   %s  <fg=gray>(under %d chars — regenerating)</>', $topic['title'], $shortThreshold));
                    }

                    if ($dry) {
                        $this->line(sprintf('  <fg=cyan>plan</>   %s  <fg=gray>[%s, %d min]</>', $topic['title'], $topic['level'], $topic['duration']));
                        $written++;

                        continue;
                    }

                    $this->line(sprintf('  <fg=yellow>gen</>    %s', $topic['title']));

                    try {
                        $result = $ai->generateLesson(
                            $topic['title'],
                            $category,
                            $topic['level'],
                            $topic['focus'],
                            $authorId,
                            $track
                        );
                    } catch (AiException $e) {
                        $failed++;

                        if ($e->kind === AiException::DAILY_QUOTA || $e->kind === AiException::TIMEOUT) {
                            $this->newLine();
                            $this->warn(sprintf('  %s — stopping here. Re-run `php artisan lessons:generate` later; finished lessons are kept.', $e->getMessage()));
                            $quotaHit = true;

                            break 3;
                        }

                        $this->line(sprintf('  <fg=red>fail</>  %s', $this->shorten($e->getMessage())));
                        continue;
                    } catch (Throwable $e) {
                        $failed++;
                        $this->line(sprintf('  <fg=red>fail</>  %s', $this->shorten($e->getMessage())));
                        continue;
                    }

                    // The plan title is the canonical English title so the slug stays
                    // stable across runs; the AI supplies the Myanmar title and body.
                    $attributes = [
                        'slug'             => $slug,
                        'category'         => $category,
                        'track'            => $track,
                        'level'            => $topic['level'],
                        'title'            => $topic['title'],
                        'title_my'         => $this->clean($result['title_my'] ?? null),
                        'summary'          => $this->clean($result['summary'] ?? null),
                        'summary_my'       => $this->clean($result['summary_my'] ?? null),
                        'content'          => (string) ($result['content'] ?? ''),
                        'content_my'       => $this->clean($result['content_my'] ?? null),
                        'key_points'       => $result['key_points'] ?? [],
                        'key_points_my'    => $result['key_points_my'] ?? [],
                        'duration_minutes' => $topic['duration'],
                        // Counted over lessons we actually write, so each category
                        // stays a contiguous block and never overlaps the next.
                        'order_index'      => $base + ($written * 10),
                        'is_published'     => true,
                        'created_by'       => $authorId,
                    ];

                    if (trim($attributes['content']) === '') {
                        $failed++;
                        $this->line('  <fg=red>fail</>  the AI returned no English content.');
                        continue;
                    }

                    if ($attributes['content_my'] === null) {
                        $incomplete[] = $track.' · '.$topic['title'];
                    }

                    Lesson::updateOrCreate(['slug' => $slug], $attributes);

                    $created++;
                    $written++;

                    $this->line(sprintf(
                        '  <fg=green>ok</>     %s  <fg=gray>[%s: %d chars, %s: %d chars]</>',
                        $result['source'] ?? 'ai',
                        'en',
                        mb_strlen($attributes['content']),
                        'my',
                        mb_strlen((string) $attributes['content_my'])
                    ));

                    if ($sleep > 0) {
                        sleep($sleep);
                    }
                }
            }
        }

        $this->newLine();
        $this->info(sprintf('Created/updated: %d · skipped: %d · failed: %d', $created, $skipped, $failed));

        if ($incomplete !== []) {
            $this->newLine();
            $this->warn('Saved WITHOUT Myanmar content (translation missing — review these):');
            foreach ($incomplete as $title) {
                $this->line('  - '.$title);
            }
        }

        foreach ($tracks as $track) {
            $this->newLine();
            $this->line(sprintf('<fg=gray>%s</>', $track));
            foreach ($categories as $category) {
                $count = Lesson::where('category', $category)->where('track', $track)->count();
                $plan = count(LessonPlan::forCategory($category, $track));
                $this->line(sprintf('  %-14s %d/%d lessons', $category, $count, $plan));
            }
        }

        if ($quotaHit) {
            $this->newLine();
            $this->warn('The run stopped early because the AI quota/time budget ran out. Progress was saved.');

            return self::SUCCESS;
        }

        return self::SUCCESS;
    }

    /** @return list<string> */
    private function resolveTracks(): array
    {
        $raw = trim((string) $this->option('track'));

        if ($raw === '') {
            return LessonPlan::TRACKS;
        }

        $wanted = array_map(fn ($t) => strtolower(trim($t)), explode(',', $raw));

        return array_values(array_filter(
            LessonPlan::TRACKS,
            fn ($t) => in_array(strtolower($t), $wanted, true)
        ));
    }

    /** @return list<string> */
    private function resolveCategories(): array
    {
        $raw = trim((string) $this->option('category'));

        if ($raw === '') {
            return LessonPlan::CATEGORIES;
        }

        $wanted = array_map(
            fn ($c) => strtolower(trim($c)),
            explode(',', $raw)
        );

        // Accept "Listening" or "listening", and tolerate a stray plural.
        return array_values(array_filter(
            LessonPlan::CATEGORIES,
            fn ($c) => in_array(strtolower($c), $wanted, true)
                || in_array(strtolower($c).'s', $wanted, true)
        ));
    }

    private function resolveAuthorId(): ?int
    {
        $option = $this->option('author');
        if ($option !== null && $option !== '') {
            return (int) $option;
        }

        return User::where('role', User::ROLE_ADMIN)->orderBy('id')->value('id');
    }

    private function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function shorten(string $message): string
    {
        return mb_strlen($message) > 160 ? mb_substr($message, 0, 157).'...' : $message;
    }
}
