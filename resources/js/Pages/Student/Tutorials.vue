<!--
  Pages/Student/Tutorials.vue
  ---------------------------------------------------------------------------
  The Tutorial library: every published lesson, filterable by track (Radiography
  or General medical), by topic, and searchable, with the student's own read
  progress on each card.

  The track filter is the outer axis: the OET paper is the same for every
  healthcare profession, so a candidate may want profession-specific practice,
  the shared medical paper, or both.

  The list is small (tens of lessons), so filtering happens client-side — one
  request, instant results, no spinner between keystrokes.
-->
<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    BookOpenCheck,
    CheckCircle2,
    Clock,
    Layers,
    Play,
    RotateCcw,
    Search,
    SlidersHorizontal,
} from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import LessonCategoryIcon from '@/components/LessonCategoryIcon.vue';
import { EmptyState, ProgressBar } from '@/components/ui';
import { useTranslation } from '@/lib/i18n';
import { langMode } from '@/lib/langMode';
import {
    LESSON_CATEGORIES,
    LESSON_TRACKS,
    LEVEL_TONE,
    lessonCategoryMeta,
    lessonTrackMeta,
} from '@/lib/lessonCategories';

const props = defineProps({
    lessons: { type: Array, default: () => [] },
    byCategory: { type: Object, default: () => ({}) },
    byTrack: { type: Object, default: () => ({}) },
    total: { type: Number, default: 0 },
    completed: { type: Number, default: 0 },
});

const { t, lang } = useTranslation();

const category = ref('All');
const track = ref('All');
const query = ref('');

/**
 * In Myanmar mode, prefer the Burmese title/summary on the card and in the
 * search index — otherwise searching in Burmese would never match.
 */
const preferMy = computed(() => langMode.value === 'my' || lang.value === 'my');

/** Pick the display string for the active language preference. */
function display(en, my) {
    return preferMy.value && my ? my : en || my;
}

const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();
    return props.lessons.filter((l) => {
        if (track.value !== 'All' && l.track !== track.value) return false;
        if (category.value !== 'All' && l.category !== category.value) return false;
        if (!q) return true;
        return [l.title, l.title_my, l.summary, l.summary_my, l.category]
            .filter(Boolean)
            .join(' ')
            .toLowerCase()
            .includes(q);
    });
});

/**
 * Counts for the track switch come from the server over the WHOLE catalogue, so
 * switching track never makes the other track's count collapse to zero.
 */
const trackOptions = computed(() => {
    const all = LESSON_TRACKS.map((name) => ({ name, count: props.byTrack?.[name] || 0 }));
    const total = all.reduce((sum, option) => sum + option.count, 0);
    return [{ name: 'All', count: total }, ...all.filter((option) => option.count > 0)];
});

/** Categories are counted within the selected track, so the counts stay honest. */
const visibleCategories = computed(() => {
    const scoped = track.value === 'All'
        ? props.lessons
        : props.lessons.filter((l) => l.track === track.value);

    return LESSON_CATEGORIES
        .map((c) => ({ name: c, count: scoped.filter((l) => l.category === c).length }))
        .filter((c) => c.count > 0);
});

const scopedTotal = computed(() =>
    track.value === 'All'
        ? props.total
        : props.lessons.filter((l) => l.track === track.value).length,
);

function clearFilters() {
    query.value = '';
    category.value = 'All';
    track.value = 'All';
}

function levelLabel(level) {
    const key = `tutorials.${level}`;
    const translated = t(key);
    return translated === key ? level : translated;
}

function categoryLabel(c) {
    const key = `lessonCategory.${c}`;
    const translated = t(key);
    return translated === key ? c : translated;
}

function trackLabel(name) {
    if (name === 'All') return t('tutorials.allTracks');
    const key = `tutorials.track${name}`;
    const translated = t(key);
    return translated === key ? name : translated;
}

function actionLabel(lesson) {
    if (lesson.completed) return t('tutorials.reviewLesson');
    if (lesson.seconds_spent > 0) return t('tutorials.continueLesson');
    return t('tutorials.startLesson');
}
</script>

<template>
    <Head :title="t('tutorials.title')" />

    <AppLayout>
        <div class="container-page py-8">
            <!-- ---- Header ---- -->
            <header class="flex flex-wrap items-end justify-between gap-4">
                <div class="max-w-2xl">
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ t('tutorials.title') }}</h1>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ t('tutorials.subtitle') }}</p>
                </div>
            </header>

            <!-- ---- Progress summary ---- -->
            <div v-if="total > 0" class="card mt-6 px-5 py-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="inline-flex items-center gap-2.5">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-teal-50 text-teal-600">
                            <BookOpenCheck :size="18" />
                        </span>
                        <div>
                            <p class="text-sm font-bold text-slate-900">{{ t('tutorials.yourProgress') }}</p>
                            <p class="text-xs text-slate-500">{{ completed }} / {{ total }} {{ t('tutorials.completed') }}</p>
                        </div>
                    </div>
                    <div class="min-w-[180px] flex-1 sm:max-w-xs">
                        <ProgressBar :value="completed" :max="total || 1" show-label />
                    </div>
                </div>
            </div>

            <!-- ---- Track switch ---- -->
            <div class="mt-6">
                <p class="mb-2 inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <Layers :size="13" /> {{ t('tutorials.track') }}
                </p>

                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="option in trackOptions"
                        :key="option.name"
                        type="button"
                        :class="[
                            'inline-flex items-center gap-2 rounded-lg border px-3.5 py-2 text-sm font-semibold transition',
                            track === option.name
                                ? 'border-primary-500 bg-primary-600 text-white shadow-sm'
                                : 'border-slate-300 bg-white text-slate-600 hover:border-primary-300 hover:bg-primary-50/40',
                        ]"
                        @click="track = option.name; category = 'All'"
                    >
                        <component
                            v-if="option.name !== 'All'"
                            :is="lessonTrackMeta(option.name).icon"
                            :size="15"
                        />
                        {{ trackLabel(option.name) }}
                        <span :class="['rounded px-1.5 text-[11px] font-bold', track === option.name ? 'bg-white/20' : 'bg-slate-100 text-slate-500']">
                            {{ option.count }}
                        </span>
                    </button>
                </div>

                <p v-if="track !== 'All'" class="mt-2 max-w-3xl text-xs leading-relaxed text-slate-500">
                    {{ t(`tutorials.track${track}Hint`) }}
                </p>
            </div>

            <!-- ---- Category filter ---- -->
            <div class="mt-5 flex flex-wrap gap-2">
                <button
                    type="button"
                    :class="[
                        'inline-flex items-center gap-2 rounded-lg border px-3.5 py-2 text-sm font-semibold transition',
                        category === 'All'
                            ? 'border-primary-500 bg-primary-600 text-white shadow-sm'
                            : 'border-slate-300 bg-white text-slate-600 hover:border-primary-300 hover:bg-primary-50/40',
                    ]"
                    @click="category = 'All'"
                >
                    {{ t('tutorials.allCategories') }}
                    <span :class="['rounded px-1.5 text-[11px] font-bold', category === 'All' ? 'bg-white/20' : 'bg-slate-100 text-slate-500']">
                        {{ scopedTotal }}
                    </span>
                </button>

                <button
                    v-for="c in visibleCategories"
                    :key="c.name"
                    type="button"
                    :class="[
                        'inline-flex items-center gap-2 rounded-lg border px-3.5 py-2 text-sm font-semibold transition',
                        category === c.name
                            ? 'border-primary-500 bg-primary-600 text-white shadow-sm'
                            : 'border-slate-300 bg-white text-slate-600 hover:border-primary-300 hover:bg-primary-50/40',
                    ]"
                    @click="category = c.name"
                >
                    <LessonCategoryIcon :category="c.name" :size="15" variant="bare" />
                    {{ categoryLabel(c.name) }}
                    <span :class="['rounded px-1.5 text-[11px] font-bold', category === c.name ? 'bg-white/20' : 'bg-slate-100 text-slate-500']">
                        {{ c.count }}
                    </span>
                </button>
            </div>

            <!-- ---- Search ---- -->
            <div class="mt-5 flex flex-wrap items-center gap-3">
                <div class="relative min-w-[240px] flex-1">
                    <Search :size="16" class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" />
                    <input
                        v-model="query"
                        type="search"
                        class="input pl-10"
                        :placeholder="t('tutorials.searchPlaceholder')"
                    />
                </div>
                <button
                    v-if="query || category !== 'All' || track !== 'All'"
                    type="button"
                    class="btn-ghost btn-sm gap-1.5"
                    @click="clearFilters"
                >
                    <RotateCcw :size="14" /> {{ t('tutorials.clearFilters') }}
                </button>
            </div>

            <!-- ---- Lesson grid ---- -->
            <div class="mt-7">
                <EmptyState v-if="!total" :icon="SlidersHorizontal" :title="t('tutorials.noLessons')" />

                <EmptyState
                    v-else-if="filtered.length === 0"
                    :icon="SlidersHorizontal"
                    :title="t('tutorials.noResults')"
                    :message="t('tutorials.noResultsHint')"
                />

                <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <article v-for="lesson in filtered" :key="lesson.id" class="card-hover card-pad flex flex-col">
                        <div class="flex items-start justify-between gap-3">
                            <LessonCategoryIcon :category="lesson.category" :size="20" />

                            <div class="flex flex-col items-end gap-1.5">
                                <span v-if="lesson.completed" class="badge-green inline-flex items-center gap-1">
                                    <CheckCircle2 :size="12" /> {{ t('tutorials.completedBadge') }}
                                </span>
                                <span :class="LEVEL_TONE[lesson.level] || 'badge-slate'">{{ levelLabel(lesson.level) }}</span>
                            </div>
                        </div>

                        <div class="mt-3 flex flex-wrap items-center gap-x-2 gap-y-1">
                            <p :class="['text-[11px] font-bold uppercase tracking-wide', lessonCategoryMeta(lesson.category).accent]">
                                {{ categoryLabel(lesson.category) }}
                            </p>
                            <!--
                              The track chip only earns its space while browsing
                              "All tracks"; once a single track is selected every
                              card would repeat the same word.
                            -->
                            <span
                                v-if="track === 'All'"
                                class="inline-flex items-center gap-1 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-slate-500"
                            >
                                <component :is="lessonTrackMeta(lesson.track).icon" :size="10" />
                                {{ trackLabel(lesson.track) }}
                            </span>
                        </div>
                        <h2 class="mt-1 text-base font-bold leading-snug text-slate-900">
                            {{ display(lesson.title, lesson.title_my) }}
                        </h2>
                        <p class="mt-1.5 line-clamp-3 flex-1 text-sm leading-relaxed text-slate-600">
                            {{ display(lesson.summary, lesson.summary_my) || '—' }}
                        </p>

                        <dl class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-slate-100 pt-3.5 text-xs text-slate-500">
                            <div class="inline-flex items-center gap-1.5">
                                <Clock :size="13" />
                                <dd>{{ t('tutorials.minutes', { count: lesson.duration_minutes }) }}</dd>
                            </div>
                            <div v-if="lesson.has_myanmar" class="inline-flex items-center gap-1.5">
                                <span class="rounded bg-primary-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-primary-700">
                                    {{ t('result.langMy') }}
                                </span>
                            </div>
                        </dl>

                        <Link :href="`/tutorials/${lesson.slug}`" class="btn-primary mt-4 w-full">
                            <Play :size="15" /> {{ actionLabel(lesson) }}
                        </Link>
                    </article>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
