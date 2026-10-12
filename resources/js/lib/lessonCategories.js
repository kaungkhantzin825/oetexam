/**
 * lib/lessonCategories.js
 * ---------------------------------------------------------------------------
 * Presentation metadata for the tutorial topics and tracks. Kept out of the
 * components so the library page and the admin editor agree on the ordering and
 * the colours.
 */

import {
    BookOpen,
    Ear,
    GraduationCap,
    Lightbulb,
    Mic,
    PenLine,
    ScanLine,
    SpellCheck,
    Stethoscope,
} from 'lucide-vue-next';

/** Must match App\Models\Lesson::CATEGORIES on the server. */
export const LESSON_CATEGORIES = [
    'Listening',
    'Reading',
    'Writing',
    'Speaking',
    'Grammar',
    'Vocabulary',
    'ExamStrategy',
];

/**
 * The two catalogues. Must match App\Models\Lesson::TRACKS.
 *
 * `Radiography` is profession-specific practice; `General` is the shared OET
 * paper that every healthcare profession sits. Students choose the one they
 * need — or browse both.
 */
export const LESSON_TRACKS = ['Radiography', 'General'];

const META = {
    Listening: { icon: Ear, accent: 'text-primary-600', tile: 'bg-primary-50 text-primary-600' },
    Reading: { icon: BookOpen, accent: 'text-teal-600', tile: 'bg-teal-50 text-teal-600' },
    Writing: { icon: PenLine, accent: 'text-amber-600', tile: 'bg-amber-50 text-amber-600' },
    Speaking: { icon: Mic, accent: 'text-purple-600', tile: 'bg-purple-50 text-purple-600' },
    Grammar: { icon: SpellCheck, accent: 'text-rose-600', tile: 'bg-rose-50 text-rose-600' },
    Vocabulary: { icon: Lightbulb, accent: 'text-indigo-600', tile: 'bg-indigo-50 text-indigo-600' },
    ExamStrategy: { icon: GraduationCap, accent: 'text-slate-600', tile: 'bg-slate-100 text-slate-600' },
};

const TRACK_META = {
    Radiography: { icon: ScanLine, accent: 'text-primary-700', tile: 'bg-primary-50 text-primary-700' },
    General: { icon: Stethoscope, accent: 'text-teal-700', tile: 'bg-teal-50 text-teal-700' },
};

const FALLBACK = META.ExamStrategy;
const TRACK_FALLBACK = TRACK_META.Radiography;

export function lessonCategoryMeta(category) {
    return META[category] || FALLBACK;
}

export function lessonTrackMeta(track) {
    return TRACK_META[track] || TRACK_FALLBACK;
}

export const LEVEL_TONE = {
    beginner: 'badge-green',
    intermediate: 'badge-blue',
    advanced: 'badge-amber',
};

export default { LESSON_CATEGORIES, LESSON_TRACKS, lessonCategoryMeta, lessonTrackMeta, LEVEL_TONE };
