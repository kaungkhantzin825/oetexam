<?php

namespace Tests\Unit;

use App\Models\Lesson;
use App\Support\LessonPlan;
use Tests\TestCase;

/**
 * The Tutorials catalogue is data, not code, so a typo in the plan would not
 * fail a type check — it would quietly create a duplicate lesson or overwrite a
 * hand-authored one on the next `lessons:generate` run. These tests pin the
 * invariants the generator relies on.
 *
 * Since the catalogue split into two tracks the stakes are higher: a General
 * entry that reuses a Radiography title, or an order block that drifts into the
 * other track's range, would corrupt both catalogues at once.
 */
class LessonPlanTest extends TestCase
{
    /** The radiography catalogue predates the tracks and stays at 20 per sub-test. */
    private const RADIOGRAPHY_PER_CATEGORY = 20;

    /** The General medical track is the deliberately smaller 15-per-sub-test set. */
    private const GENERAL_PER_CATEGORY = 15;

    /** @return array<string, int> */
    private function expectedCounts(): array
    {
        return [
            'Radiography' => self::RADIOGRAPHY_PER_CATEGORY,
            'General'     => self::GENERAL_PER_CATEGORY,
        ];
    }

    public function test_each_track_plans_the_expected_number_of_lessons_per_sub_test(): void
    {
        foreach ($this->expectedCounts() as $track => $expected) {
            foreach (LessonPlan::CATEGORIES as $category) {
                $this->assertCount(
                    $expected,
                    LessonPlan::forCategory($category, $track),
                    "{$track}/{$category} should plan exactly {$expected} lessons."
                );
            }
        }
    }

    public function test_the_track_list_matches_the_lesson_model(): void
    {
        // Two sources of truth for the same list; if they drift, a lesson can be
        // written with a track the model does not recognise.
        $this->assertSame(Lesson::TRACKS, LessonPlan::TRACKS);
        $this->assertSame(Lesson::DEFAULT_TRACK, LessonPlan::DEFAULT_TRACK);

        foreach (LessonPlan::TRACKS as $track) {
            $this->assertTrue(LessonPlan::isTrack($track));
        }

        $this->assertFalse(LessonPlan::isTrack('Nursing'));
        $this->assertFalse(LessonPlan::isTrack(''));
    }

    public function test_every_plan_entry_is_complete_and_well_formed(): void
    {
        foreach (LessonPlan::TRACKS as $track) {
            foreach (LessonPlan::CATEGORIES as $category) {
                foreach (LessonPlan::forCategory($category, $track) as $entry) {
                    $label = "{$track}/{$category}";

                    $this->assertNotSame('', trim($entry['title']), "A {$label} entry has no title.");
                    $this->assertContains($entry['level'], Lesson::LEVELS, "Bad level on '{$entry['title']}'.");
                    $this->assertGreaterThanOrEqual(10, $entry['duration'], "Bad duration on '{$entry['title']}'.");
                    $this->assertLessThanOrEqual(30, $entry['duration'], "Bad duration on '{$entry['title']}'.");

                    // `focus` is what stops the model writing the same generic
                    // lesson fifteen times, so it must be substantive.
                    $this->assertGreaterThan(
                        40,
                        mb_strlen($entry['focus']),
                        "The focus note for '{$entry['title']}' is too thin to steer the model."
                    );
                }
            }
        }
    }

    public function test_titles_are_unique_within_a_category_of_a_track(): void
    {
        foreach (LessonPlan::TRACKS as $track) {
            foreach (LessonPlan::CATEGORIES as $category) {
                $titles = array_column(LessonPlan::forCategory($category, $track), 'title');

                $this->assertSame(
                    $titles,
                    array_values(array_unique($titles)),
                    "{$track}/{$category} contains a duplicate title."
                );
            }
        }
    }

    public function test_every_planned_slug_is_unique_across_both_tracks(): void
    {
        $slugs = [];

        foreach (LessonPlan::TRACKS as $track) {
            foreach (LessonPlan::CATEGORIES as $category) {
                foreach (LessonPlan::forCategory($category, $track) as $entry) {
                    $slugs[] = LessonPlan::slugFor($entry['title']);
                }
            }
        }

        // 80 radiography + 60 general.
        $this->assertCount(140, $slugs);
        $this->assertSame(
            $slugs,
            array_values(array_unique($slugs)),
            'Two plan entries resolve to the same slug — one would overwrite the other.'
        );

        foreach ($slugs as $slug) {
            $this->assertNotSame('', $slug);
            $this->assertLessThanOrEqual(160, strlen($slug), "Slug too long for the column: {$slug}");
        }
    }

    /**
     * The whole point of the General track is that it is profession-neutral. If
     * an imaging term leaks into a General topic the lesson stops being usable
     * by the nurses, pharmacists and dentists it exists for — and nothing else
     * would catch it, because the text is generated at runtime.
     */
    public function test_general_topics_avoid_imaging_specific_vocabulary(): void
    {
        $imaging = '/\b(radiograph\w*|radiology|x-?ray|ct scan|mri|ultrasound|sonograph\w*|'
            .'mammograph\w*|fluoroscop\w*|ionis\w+|ioniz\w+|imaging|radiotherapy)\b/i';

        foreach (LessonPlan::CATEGORIES as $category) {
            foreach (LessonPlan::forCategory($category, 'General') as $entry) {
                $this->assertDoesNotMatchRegularExpression(
                    $imaging,
                    $entry['title'].' '.$entry['focus'],
                    "The General lesson '{$entry['title']}' leans on imaging vocabulary."
                );
            }
        }
    }

    /**
     * The opposite guard: the radiography track should actually use its own
     * domain, otherwise the two catalogues collapse into one.
     */
    public function test_radiography_topics_do_use_imaging_vocabulary(): void
    {
        $imaging = '/\b(radiograph\w*|radiology|x-?ray|ct\b|mri\b|ultrasound|imaging|radiation)\b/i';

        $hits = 0;
        foreach (LessonPlan::CATEGORIES as $category) {
            foreach (LessonPlan::forCategory($category, 'Radiography') as $entry) {
                if (preg_match($imaging, $entry['title'].' '.$entry['focus'])) {
                    $hits++;
                }
            }
        }

        $this->assertGreaterThanOrEqual(
            5,
            $hits,
            'The radiography catalogue no longer reads as radiography-specific.'
        );
    }

    public function test_hand_written_lessons_are_planned_and_use_their_real_stored_slug(): void
    {
        // Hand-authored lessons predate the tracks and live in Radiography.
        $planned = [];
        foreach (LessonPlan::CATEGORIES as $category) {
            foreach (LessonPlan::forCategory($category, 'Radiography') as $entry) {
                $planned[$entry['title']] = true;
            }
        }

        foreach (LessonPlan::HAND_WRITTEN as $title => $storedSlug) {
            $this->assertArrayHasKey($title, $planned, "Hand-written lesson '{$title}' is missing from the plan.");

            $this->assertTrue(LessonPlan::isHandWritten($title));
            $this->assertSame(
                $storedSlug,
                LessonPlan::slugFor($title),
                "'{$title}' must resolve to its real stored slug, or the generator creates a duplicate."
            );
        }

        // These are the slugs the pre-migration seed actually wrote. If the
        // database ever disagrees, the generator would duplicate them.
        $this->assertSame('listening-part-a-note-completion', LessonPlan::HAND_WRITTEN['Listening Part A: Note Completion Without Panic']);
        $this->assertSame('reading-part-a-expeditious', LessonPlan::HAND_WRITTEN['Reading Part A: 15 Minutes, 20 Gaps — The Scanning Method']);
        $this->assertSame('speaking-roleplay-framework', LessonPlan::HAND_WRITTEN['Speaking: A Framework for Every Role-Play']);
    }

    /**
     * Every block of order indices must be disjoint from every other block —
     * across BOTH tracks. Overlap means two lessons interleave in the library
     * listing and the admin reorder control becomes meaningless.
     */
    public function test_order_index_blocks_never_overlap_across_all_tracks(): void
    {
        $ranges = [];

        foreach (LessonPlan::TRACKS as $track) {
            $perCategory = $this->expectedCounts()[$track];

            foreach (LessonPlan::CATEGORIES as $category) {
                $base = LessonPlan::orderBase($category, $track);
                // N lessons at a step of 10, counting from the base.
                $ranges["{$track}/{$category}"] = [$base, $base + (($perCategory - 1) * 10)];
            }
        }

        $this->assertCount(8, $ranges);

        foreach ($ranges as $label => [$start, $end]) {
            foreach ($ranges as $other => [$otherStart, $otherEnd]) {
                if ($label === $other) {
                    continue;
                }

                $this->assertTrue(
                    $end < $otherStart || $start > $otherEnd,
                    "The {$label} order-index block ({$start}-{$end}) overlaps {$other} ({$otherStart}-{$otherEnd})."
                );
            }
        }
    }

    /**
     * The General blocks sit above everything already stored, so adding the
     * track cannot renumber an existing radiography lesson.
     */
    public function test_general_blocks_start_above_every_radiography_block(): void
    {
        $radiographyMax = 0;
        foreach (LessonPlan::CATEGORIES as $category) {
            $radiographyMax = max(
                $radiographyMax,
                LessonPlan::orderBase($category, 'Radiography') + ((self::RADIOGRAPHY_PER_CATEGORY - 1) * 10)
            );
        }

        foreach (LessonPlan::CATEGORIES as $category) {
            $this->assertGreaterThan(
                $radiographyMax,
                LessonPlan::orderBase($category, 'General'),
                "General/{$category} must start above the radiography range ({$radiographyMax})."
            );
        }
    }

    public function test_all_returns_both_tracks_nested_and_one_track_flat(): void
    {
        $all = LessonPlan::all();

        $this->assertSame(LessonPlan::TRACKS, array_keys($all));
        foreach (LessonPlan::TRACKS as $track) {
            $this->assertSame(LessonPlan::CATEGORIES, array_keys($all[$track]));
        }

        $general = LessonPlan::all('General');
        $this->assertSame(LessonPlan::CATEGORIES, array_keys($general));
        $this->assertArrayNotHasKey('Radiography', $general);

        // forCategory() defaults to the radiography track so every pre-existing
        // call site keeps its meaning.
        $this->assertSame(
            LessonPlan::forCategory('Listening', 'Radiography'),
            LessonPlan::forCategory('Listening')
        );
    }

    public function test_unknown_categories_and_tracks_are_handled_gracefully(): void
    {
        $this->assertSame([], LessonPlan::forCategory('NotACategory'));
        $this->assertSame([], LessonPlan::forCategory('NotACategory', 'General'));
        $this->assertSame([], LessonPlan::forCategory('Listening', 'NotATrack'));
        $this->assertSame([], LessonPlan::all('NotATrack'));

        $this->assertSame(2000, LessonPlan::orderBase('NotACategory'));
        $this->assertSame(2000, LessonPlan::orderBase('NotACategory', 'General'));
        $this->assertSame(2000, LessonPlan::orderBase('Listening', 'NotATrack'));
    }
}
