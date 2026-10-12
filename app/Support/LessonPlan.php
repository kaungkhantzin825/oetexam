<?php

namespace App\Support;

/**
 * The Tutorials curriculum, organised into two tracks.
 *
 * The OET paper is the same for every healthcare profession — a nurse, a
 * pharmacist and a radiographer sit the identical test. So the catalogue has two
 * halves:
 *
 *   Radiography  20 lessons per sub-test, profession-specific practice
 *   General      15 lessons per sub-test, the shared medical paper
 *
 * The General half exists because real OET Listening and Reading passages are
 * drawn from the whole of general medicine, not from imaging. A candidate who
 * only ever practises radiography scenarios meets unfamiliar material on the day.
 *
 * The catalogue is DATA, not code — `lessons:generate` walks this plan and asks
 * the AI facade for each lesson. The plan is idempotent: the generator derives a
 * slug from each title and skips anything that already exists, so re-running it
 * after a quota reset only fills the gaps.
 *
 * The first two entries in each Radiography category are the hand-written lessons
 * that shipped with the platform. They are listed here so this file documents the
 * complete curriculum rather than only the generated remainder.
 *
 * `focus` is the per-topic steering instruction. It exists to stop the model
 * writing the same generic "here is how Part A works" lesson twenty times —
 * every entry must cover genuinely different ground.
 */
final class LessonPlan
{
    /** The four sub-tests, in both tracks. */
    public const CATEGORIES = ['Listening', 'Reading', 'Writing', 'Speaking'];

    /** Must match App\Models\Lesson::TRACKS. */
    public const TRACKS = ['Radiography', 'General'];

    public const DEFAULT_TRACK = 'Radiography';

    /**
     * Order-index base per track and category, so every block is contiguous and
     * no two blocks can ever overlap. Radiography occupies 200–890 (unchanged
     * from before the tracks existed); General starts at 1200 so it sits clear
     * of everything already in the table.
     *
     * @var array<string, array<string, int>>
     */
    private const ORDER_BASE = [
        'Radiography' => [
            'Listening' => 200,
            'Reading'   => 400,
            'Writing'   => 600,
            'Speaking'  => 800,
        ],
        'General' => [
            'Listening' => 1200,
            'Reading'   => 1400,
            'Writing'   => 1600,
            'Speaking'  => 1800,
        ],
    ];

    /**
     * Slugs of the lessons that were hand-authored before the AI generator
     * existed. They are matched by their REAL stored slug (which does not
     * follow the generated naming scheme) and are never overwritten — the
     * generator skips them even under --force, so a regeneration run can never
     * replace curated content with machine output.
     *
     * @var array<string, string> title => stored slug
     */
    public const HAND_WRITTEN = [
        'Listening Part A: Note Completion Without Panic'          => 'listening-part-a-note-completion',
        'Listening Parts B and C: Winning the Multiple Choice'     => 'listening-part-b-c-multiple-choice',
        'Reading Part A: 15 Minutes, 20 Gaps — The Scanning Method' => 'reading-part-a-expeditious',
        'Reading Parts B and C: Careful Reading Under Pressure'    => 'reading-part-b-c-detailed',
        'Writing: The Referral Letter That Scores'                 => 'writing-referral-letter-structure',
        'Writing: The Six Criteria, and Where Candidates Lose Marks' => 'writing-assessment-criteria',
        'Speaking: A Framework for Every Role-Play'                => 'speaking-roleplay-framework',
        'Speaking: The Language Bank for Patient Conversations'    => 'speaking-patient-communication-phrases',
    ];

    /** The stable slug for a plan entry — hand-written ones use their real slug. */
    public static function slugFor(string $title): string
    {
        return self::HAND_WRITTEN[$title] ?? \App\Models\Lesson::makeSlug($title);
    }

    public static function isHandWritten(string $title): bool
    {
        return isset(self::HAND_WRITTEN[$title]);
    }

    /**
     * The whole plan, or one track's worth.
     *
     * With no argument the return shape is nested by track, so the generator can
     * walk both catalogues. Pass a track to get just that track's
     * `category => entries` map.
     *
     * @return array<string, array<string, list<array{title: string, level: string, duration: int, focus: string}>>>
     */
    public static function all(?string $track = null): array
    {
        $plan = [
            'Radiography' => [
                'Listening' => self::listening(),
                'Reading'   => self::reading(),
                'Writing'   => self::writing(),
                'Speaking'  => self::speaking(),
            ],
            'General' => [
                'Listening' => self::generalListening(),
                'Reading'   => self::generalReading(),
                'Writing'   => self::generalWriting(),
                'Speaking'  => self::generalSpeaking(),
            ],
        ];

        if ($track === null) {
            return $plan;
        }

        return $plan[$track] ?? [];
    }

    /** @return list<array{title: string, level: string, duration: int, focus: string}> */
    public static function forCategory(string $category, string $track = self::DEFAULT_TRACK): array
    {
        return self::all($track)[$category] ?? [];
    }

    public static function orderBase(string $category, string $track = self::DEFAULT_TRACK): int
    {
        return self::ORDER_BASE[$track][$category] ?? 2000;
    }

    public static function isTrack(string $track): bool
    {
        return in_array($track, self::TRACKS, true);
    }

    // -----------------------------------------------------------------------
    // Listening
    // -----------------------------------------------------------------------

    private static function listening(): array
    {
        return [
            [
                'title'    => 'Listening Part A: Note Completion Without Panic',
                'level'    => 'beginner',
                'duration' => 15,
                'focus'    => 'The read-ahead routine, the paraphrase trap and the spelling rules that quietly cost marks in Part A.',
            ],
            [
                'title'    => 'Listening Parts B and C: Winning the Multiple Choice',
                'level'    => 'intermediate',
                'duration' => 15,
                'focus'    => 'How Part B and Part C distractors are engineered, and how to eliminate options before the audio ends.',
            ],
            [
                'title'    => 'Hearing the Paraphrase: Fifty Pairs You Must Recognise',
                'level'    => 'intermediate',
                'duration' => 18,
                'focus'    => 'A drill bank of clinical paraphrases (pain relief / something for the discomfort) so candidates stop listening for the printed word.',
            ],
            [
                'title'    => 'Predicting Answer Types Before the Audio Starts',
                'level'    => 'beginner',
                'duration' => 14,
                'focus'    => 'Using the 30-second read-ahead to label every gap as number, time, duration, name, medication or adjective.',
            ],
            [
                'title'    => 'Signposting Language: Hearing Where the Answer Will Be',
                'level'    => 'intermediate',
                'duration' => 16,
                'focus'    => 'Discourse markers ("the thing is", "what I would suggest", "the main risk") that announce an imminent answer.',
            ],
            [
                'title'    => 'Spelling Under Pressure: Radiography Terms That Cost Marks',
                'level'    => 'beginner',
                'duration'    => 15,
                'focus'    => 'The high-frequency clinical spellings candidates miss, with a listen-and-write drill and common phonetic traps.',
            ],
            [
                'title'    => 'Accents and Speed: Training Your Ear for Australian, British and Irish English',
                'level'    => 'advanced',
                'duration' => 20,
                'focus'    => 'Vowel shifts and consonant changes across OET speaker accents, with strategies for adjusting when the accent is unfamiliar.',
            ],
            [
                'title'    => 'Connected Speech: Why "Want To" Sounds Like "Wanna"',
                'level'    => 'advanced',
                'duration' => 18,
                'focus'    => 'Elision, assimilation and weak forms in clinical conversation, and how to decode them without losing the thread.',
            ],
            [
                'title'    => 'Negatives, Conditions and Caveats',
                'level'    => 'intermediate',
                'duration' => 15,
                'focus'    => 'Hearing "unless", "as long as", "only if" and "not necessarily" correctly — the small words that flip an answer.',
            ],
            [
                'title'    => 'Numbers, Units and Dosages in Clinical Speech',
                'level'    => 'intermediate',
                'duration' => 16,
                'focus'    => 'Distinguishing fifteen from fifty, mg from mcg, and millilitres from milligrams; writing digits accurately under time pressure.',
            ],
            [
                'title'    => 'Part A: Recovering From a Missed Answer',
                'level'    => 'beginner',
                'duration' => 12,
                'focus'    => 'The two-gap abandonment strategy and a recovery drill so one lost answer never becomes three.',
            ],
            [
                'title'    => 'Medication and Anatomy Names: Building a Listening Vocabulary',
                'level'    => 'intermediate',
                'duration' => 18,
                'focus'    => 'The drug classes, procedure names and anatomy terms that recur in OET recordings, with pronunciation notes.',
            ],
            [
                'title'    => 'Telephone and Referral Conversations',
                'level'    => 'intermediate',
                'duration' => 15,
                'focus'    => 'Handling calls with poor audio, spelling names aloud, and confirming details the way the recording expects.',
            ],
            [
                'title'    => 'Note-Taking Symbols and Abbreviations That Save Seconds',
                'level'    => 'beginner',
                'duration' => 12,
                'focus'    => 'A personal shorthand system for the note-completion tasks, built around clinical abbreviations.',
            ],
            [
                'title'    => 'Part B: Workplace Extracts and Professional Register',
                'level'    => 'intermediate',
                'duration' => 15,
                'focus'    => 'Short exchanges between colleagues — what the speaker actually intends when they are being indirect or diplomatic.',
            ],
            [
                'title'    => 'Part C: Following the Structure of a Monologue',
                'level'    => 'advanced',
                'duration' => 18,
                'focus'    => 'Mapping an extended presentation or interview onto the questions so you always know where you are.',
            ],
            [
                'title'    => 'Listening for Attitude and Intention',
                'level'    => 'advanced',
                'duration' => 16,
                'focus'    => 'Tone, hedging and emphasis — how speakers signal doubt, agreement or reluctance without saying it directly.',
            ],
            [
                'title'    => 'When the Answer Comes First and the Question Second',
                'level'    => 'advanced',
                'duration' => 14,
                'focus'    => 'Handling questions whose answer is stated before the topic appears on the page, a common Part C ordering trap.',
            ],
            [
                'title'    => 'Listening: Vocabulary for Imaging and Radiation Safety',
                'level'    => 'beginner',
                'duration' => 16,
                'focus'    => 'The ALARA, contrast, collimation and positioning vocabulary that dominates radiography listening papers.',
            ],
            [
                'title'    => 'Full Listening Mock: Timing, Transfers and the Final Check',
                'level'    => 'advanced',
                'duration' => 25,
                'focus'    => 'A complete timed walkthrough of all three parts with transfer time, plus the 60-second end-of-test review routine.',
            ],
        ];
    }

    // -----------------------------------------------------------------------
    // Reading
    // -----------------------------------------------------------------------

    private static function reading(): array
    {
        return [
            [
                'title'    => 'Reading Part A: 15 Minutes, 20 Gaps — The Scanning Method',
                'level'    => 'intermediate',
                'duration' => 16,
                'focus'    => 'Skimming the four texts, locating the exact answer, and the disciplined 15-minute cap.',
            ],
            [
                'title'    => 'Reading Parts B and C: Careful Reading Under Pressure',
                'level'    => 'intermediate',
                'duration' => 16,
                'focus'    => 'The 45/30 split, question-first reading, and eliminating options that are true but do not answer the question.',
            ],
            [
                'title'    => 'Skimming Versus Scanning: Choosing the Right Speed',
                'level'    => 'beginner',
                'duration' => 14,
                'focus'    => 'When to read for gist and when to hunt for a detail, with timed drills for each mode.',
            ],
            [
                'title'    => 'Part B: Six Short Texts, Six Different Purposes',
                'level'    => 'intermediate',
                'duration' => 16,
                'focus'    => 'Hospital notices, memos, policy extracts and patient letters — identifying the writer\'s purpose in one pass.',
            ],
            [
                'title'    => 'Part C: Reading a Research Article in Four Paragraphs',
                'level'    => 'advanced',
                'duration' => 18,
                'focus'    => 'Aim, method, result, conclusion — mapping the standard clinical paper onto the eight questions that follow.',
            ],
            [
                'title'    => 'The Two-Pass Method: Gist Then Detail',
                'level'    => 'intermediate',
                'duration' => 15,
                'focus'    => 'A repeatable reading routine that stops candidates re-reading the same paragraph three times.',
            ],
            [
                'title'    => 'Opinion, Evidence and Hedging in Clinical Text',
                'level'    => 'advanced',
                'duration' => 16,
                'focus'    => 'Separating what the author asserts from what the data shows, using modal verbs and reporting verbs as clues.',
            ],
            [
                'title'    => 'Guessing Meaning From Context',
                'level'    => 'intermediate',
                'duration' => 15,
                'focus'    => 'Decoding unfamiliar clinical vocabulary from word parts, surrounding grammar and contrast signals.',
            ],
            [
                'title'    => 'Reference Words: Tracking "This", "They" and "The Former"',
                'level'    => 'intermediate',
                'duration' => 14,
                'focus'    => 'Cohesive devices that cause misreading in Part C, with a tracking drill.',
            ],
            [
                'title'    => 'Not Given Versus False: The Hardest Distinction',
                'level'    => 'advanced',
                'duration' => 16,
                'focus'    => 'A decision procedure for the three-way True / False / Not Given judgement, with worked examples.',
            ],
            [
                'title'    => 'Time Management: The 15 / 45 / 30 Split',
                'level'    => 'beginner',
                'duration' => 13,
                'focus'    => 'Pacing the three parts, knowing when to abandon a question, and protecting the final five minutes.',
            ],
            [
                'title'    => 'Scanning for Numbers, Units and Dosages',
                'level'    => 'beginner',
                'duration' => 13,
                'focus'    => 'Fast, reliable location of numeric detail in dense clinical text, including unit and decimal traps.',
            ],
            [
                'title'    => 'Reading Tables, Charts and Referral Forms',
                'level'    => 'intermediate',
                'duration' => 15,
                'focus'    => 'Extracting answers from non-prose layouts — lab results, observation charts and admission forms.',
            ],
            [
                'title'    => 'Cohesion: How Clinical Texts Signal Structure',
                'level'    => 'advanced',
                'duration' => 15,
                'focus'    => 'Topic sentences, contrast markers and enumeration, used to predict where an answer must sit.',
            ],
            [
                'title'    => 'Part A: The Advertisement and the Detail Trap',
                'level'    => 'intermediate',
                'duration' => 15,
                'focus'    => 'Matching patients to services when several clinics look plausible, and the qualifying details that decide it.',
            ],
            [
                'title'    => 'Reading Speed: From 150 to 250 Words per Minute',
                'level'    => 'intermediate',
                'duration' => 16,
                'focus'    => 'Chunking, reducing sub-vocalisation and a four-week speed-building schedule.',
            ],
            [
                'title'    => 'Inference Questions: Reading Between the Lines',
                'level'    => 'advanced',
                'duration' => 16,
                'focus'    => 'Answering what the text implies without importing outside clinical knowledge.',
            ],
            [
                'title'    => 'Vocabulary in Context: Clinical Collocations',
                'level'    => 'intermediate',
                'duration' => 15,
                'focus'    => 'The verb-plus-noun and adjective-plus-noun pairings that recur in OET reading passages.',
            ],
            [
                'title'    => 'Reading: Common Traps and How to Avoid Them',
                'level'    => 'intermediate',
                'duration' => 14,
                'focus'    => 'The ten recurring error patterns in Part B and C, each with a correction routine.',
            ],
            [
                'title'    => 'Full Reading Mock: Pacing All Three Parts',
                'level'    => 'advanced',
                'duration' => 25,
                'focus'    => 'A complete timed walkthrough of Parts A, B and C with a post-test review protocol.',
            ],
        ];
    }

    // -----------------------------------------------------------------------
    // Writing
    // -----------------------------------------------------------------------

    private static function writing(): array
    {
        return [
            [
                'title'    => 'Writing: The Referral Letter That Scores',
                'level'    => 'beginner',
                'duration' => 18,
                'focus'    => 'The overall shape of the task, the word limit and the opening that sets up every later mark.',
            ],
            [
                'title'    => 'Writing: The Six Criteria, and Where Candidates Lose Marks',
                'level'    => 'intermediate',
                'duration' => 15,
                'focus'    => 'Purpose, content, conciseness, genre and style, organisation, and language — what each one actually rewards.',
            ],
            [
                'title'    => 'Writing: The Opening Line That Earns the Purpose Mark',
                'level'    => 'beginner',
                'duration' => 14,
                'focus'    => 'Six model openings for six referral scenarios, and why "I am writing to refer..." is only sometimes right.',
            ],
            [
                'title'    => 'Writing: Selecting Content — What to Include and What to Cut',
                'level'    => 'intermediate',
                'duration'    => 16,
                'focus'    => 'Reading the case notes and the task prompt to decide which details the reader genuinely needs.',
            ],
            [
                'title'    => 'Writing: Conciseness — Cutting 200 Words Without Losing Meaning',
                'level'    => 'advanced',
                'duration' => 16,
                'focus'    => 'Nominalisation, deleting redundancy and compressing case history, with before-and-after rewrites.',
            ],
            [
                'title'    => 'Writing: Genre and Style in Clinical Correspondence',
                'level'    => 'intermediate',
                'duration' => 15,
                'focus'    => 'Formal register, avoiding conversational language, and the conventions of the professional letter.',
            ],
            [
                'title'    => 'Writing: Organising the Referral Letter Into Paragraphs',
                'level'    => 'beginner',
                'duration' => 14,
                'focus'    => 'A four-paragraph skeleton that satisfies the organisation criterion every time.',
            ],
            [
                'title'    => 'Writing: Language Accuracy and Range',
                'level'    => 'advanced',
                'duration' => 16,
                'focus'    => 'The grammar and vocabulary errors that most damage the language mark, and how to widen range safely.',
            ],
            [
                'title'    => 'Writing: The Transfer Letter Versus the Referral Letter',
                'level'    => 'intermediate',
                'duration' => 15,
                'focus'    => 'How the reader, purpose and content change when you are handing a patient back rather than onward.',
            ],
            [
                'title'    => 'Writing: What the Specialist Actually Needs to Know',
                'level'    => 'intermediate',
                'duration' => 15,
                'focus'    => 'Prioritising clinical information from the referrer\'s point of view.',
            ],
            [
                'title'    => 'Writing: The Discharge Summary',
                'level'    => 'advanced',
                'duration' => 16,
                'focus'    => 'Structure, tense and the treatment/medication detail expected in a discharge letter.',
            ],
            [
                'title'    => 'Writing: Handling a Complex Case History in 180 Words',
                'level'    => 'advanced',
                'duration' => 18,
                'focus'    => 'Triage technique for long, messy notes, with a full worked example.',
            ],
            [
                'title'    => 'Writing: Describing Test Results Accurately',
                'level'    => 'intermediate',
                'duration' => 15,
                'focus'    => 'Reporting imaging findings, values and dates without ambiguity or overstatement.',
            ],
            [
                'title'    => 'Writing: Summarising Medication, Allergies and Risks',
                'level'    => 'intermediate',
                'duration' => 15,
                'focus'    => 'Compact, unambiguous phrasing for drug names, doses, reactions and contraindications.',
            ],
            [
                'title'    => 'Writing: Connectors and Cohesion in Clinical Prose',
                'level'    => 'intermediate',
                'duration' => 14,
                'focus'    => 'Linking sentences and paragraphs so the letter reads as one argument rather than a list.',
            ],
            [
                'title'    => 'Writing: Timing — 45 Minutes, 180 Words, One Clean Draft',
                'level'    => 'beginner',
                'duration' => 15,
                'focus'    => 'A minute-by-minute plan: read, plan, write, check, with a proofreading checklist.',
            ],
            [
                'title'    => 'Writing: Common Radiography Referral Scenarios',
                'level'    => 'intermediate',
                'duration' => 16,
                'focus'    => 'Recurring OET cases — suspected fracture, incidental finding, contrast reaction, non-attendance — with model content for each.',
            ],
            [
                'title'    => 'Writing: Self-Assessment Against the Six Criteria',
                'level'    => 'advanced',
                'duration' => 14,
                'focus'    => 'A scoring rubric the candidate can apply to their own draft before time runs out.',
            ],
            [
                'title'    => 'Writing: The Ten Errors Examiners Notice Most',
                'level'    => 'intermediate',
                'duration' => 15,
                'focus'    => 'Ranked error list with a correction for each, drawn from the six assessment criteria.',
            ],
            [
                'title'    => 'Full Writing Mock With an Annotated Model Answer',
                'level'    => 'advanced',
                'duration' => 25,
                'focus'    => 'A complete timed letter task, followed by a band-6 model answer annotated against every criterion.',
            ],
        ];
    }

    // -----------------------------------------------------------------------
    // Speaking
    // -----------------------------------------------------------------------

    private static function speaking(): array
    {
        return [
            [
                'title'    => 'Speaking: A Framework for Every Role-Play',
                'level'    => 'beginner',
                'duration' => 17,
                'focus'    => 'Greet, explain, gather, check, summarise, close — a structure that fits any OET speaking card.',
            ],
            [
                'title'    => 'Speaking: The Language Bank for Patient Conversations',
                'level'    => 'beginner',
                'duration' => 14,
                'focus'    => 'Reusable phrases for explaining, reassuring, checking understanding and closing a consultation.',
            ],
            [
                'title'    => 'Speaking: The Warm-Up — Two Minutes That Set the Tone',
                'level'    => 'beginner',
                'duration' => 13,
                'focus'    => 'The preparation minute, note-taking on the card, and the opening move that establishes rapport.',
            ],
            [
                'title'    => 'Speaking: Explaining a Procedure to an Anxious Patient',
                'level'    => 'intermediate',
                'duration' => 16,
                'focus'    => 'Sequencing an explanation, signposting each step and pre-empting the patient\'s fears.',
            ],
            [
                'title'    => 'Speaking: Open, Closed and Follow-Up Questions',
                'level'    => 'intermediate',
                'duration' => 15,
                'focus'    => 'Choosing the question type that gets the information without interrogating the patient.',
            ],
            [
                'title'    => 'Speaking: Giving Results and Handling Emotional Responses',
                'level'    => 'advanced',
                'duration' => 18,
                'focus'    => 'Delivering unwelcome findings, pausing, acknowledging emotion and offering the next step.',
            ],
            [
                'title'    => 'Speaking: Asking About Pain Without Leading the Patient',
                'level'    => 'intermediate',
                'duration' => 15,
                'focus'    => 'The SOCRATES framework in plain language, and avoiding questions that put words in the patient\'s mouth.',
            ],
            [
                'title'    => 'Speaking: Obtaining Consent and Checking Understanding',
                'level'    => 'intermediate',
                'duration' => 15,
                'focus'    => 'Teach-back technique, consent phrasing for contrast and ionising radiation, and documenting agreement.',
            ],
            [
                'title'    => 'Speaking: When a Patient Refuses a Scan',
                'level'    => 'advanced',
                'duration' => 17,
                'focus'    => 'Exploring the objection, correcting misconceptions about radiation, and respecting a refusal without abandoning the task.',
            ],
            [
                'title'    => 'Speaking: Telephone and Referral Role-Plays',
                'level'    => 'intermediate',
                'duration' => 16,
                'focus'    => 'Speaking to a colleague who cannot see the patient, confirming details and closing the loop.',
            ],
            [
                'title'    => 'Speaking: Explaining Radiation Risk in Plain Language',
                'level'    => 'intermediate',
                'duration' => 16,
                'focus'    => 'Translating dose, benefit and ALARA into words a frightened patient can act on.',
            ],
            [
                'title'    => 'Speaking: Talking to a Child or an Elderly Patient',
                'level'    => 'advanced',
                'duration' => 16,
                'focus'    => 'Adjusting register, pace and vocabulary to the listener, including speaking with a carer present.',
            ],
            [
                'title'    => 'Speaking: Handling Complaints and Apologising',
                'level'    => 'advanced',
                'duration' => 16,
                'focus'    => 'Acknowledging a problem, avoiding blame language and moving to resolution.',
            ],
            [
                'title'    => 'Speaking: Interrupting, Redirecting and Closing',
                'level'    => 'intermediate',
                'duration' => 14,
                'focus'    => 'Managing a talkative patient politely and ending the consultation on task.',
            ],
            [
                'title'    => 'Speaking: Pronunciation and Intelligibility for Myanmar Speakers',
                'level'    => 'intermediate',
                'duration' => 18,
                'focus'    => 'The specific consonant clusters, final sounds and stress patterns that most affect intelligibility.',
            ],
            [
                'title'    => 'Speaking: Fluency — Reducing Hesitation and Fillers',
                'level'    => 'intermediate',
                'duration' => 15,
                'focus'    => 'Replacing "um" and long pauses with useful fillers and self-repair strategies.',
            ],
            [
                'title'    => 'Speaking: Grammar Range Under Pressure',
                'level'    => 'advanced',
                'duration' => 16,
                'focus'    => 'Conditionals, modals and passive structures used naturally in patient explanations.',
            ],
            [
                'title'    => 'Speaking: Cultural Sensitivity in Patient Communication',
                'level'    => 'advanced',
                'duration' => 15,
                'focus'    => 'Modesty, family involvement, religious practice and consent norms across the patients OET presents.',
            ],
            [
                'title'    => 'Speaking: Reading the Role-Play Card in 30 Seconds',
                'level'    => 'beginner',
                'duration' => 13,
                'focus'    => 'Identifying the setting, the task and the three things the examiner must hear you do.',
            ],
            [
                'title'    => 'Full Speaking Mock With Examiner Commentary',
                'level'    => 'advanced',
                'duration' => 25,
                'focus'    => 'Two complete role-plays assessed against all six criteria, with commentary on what lifted or lost each band.',
            ],
        ];
    }

    // =======================================================================
    // General medical track
    // =======================================================================
    //
    // The OET paper is identical for every healthcare profession, and its
    // Listening and Reading passages are drawn from the whole of general
    // medicine — ward rounds, medication counselling, discharge planning, public
    // health. These 60 lessons deliberately avoid imaging vocabulary so a
    // candidate is not blindsided by unfamiliar material on the day.

    /** @return list<array{title: string, level: string, duration: int, focus: string}> */
    private static function generalListening(): array
    {
        return [
            [
                'title'    => 'Listening Part A: Note Completion in a General Medical Consultation',
                'level'    => 'beginner',
                'duration' => 15,
                'focus'    => 'The read-ahead routine applied to a GP consultation: labelling each gap, then catching the answer as it is spoken.',
            ],
            [
                'title'    => 'Listening Part B: Ward and Clinic Conversations',
                'level'    => 'intermediate',
                'duration' => 15,
                'focus'    => 'Short extracts between colleagues and patients, and how Part B distractors are built to sound plausible.',
            ],
            [
                'title'    => 'Listening Part C: Following a Public Health Talk',
                'level'    => 'advanced',
                'duration' => 20,
                'focus'    => 'Holding the thread of a monologue on vaccination, screening or health promotion while answering inference questions.',
            ],
            [
                'title'    => 'Look-Alike Medication Names: A Listening Drill',
                'level'    => 'intermediate',
                'duration' => 16,
                'focus'    => 'Pairs such as Losec/Lasix and Zantac/Xanax, why the wrong one changes the answer, and how to confirm from context.',
            ],
            [
                'title'    => 'Doses, Units and Numbers: Hearing Them Right',
                'level'    => 'beginner',
                'duration' => 14,
                'focus'    => 'Milligrams versus micrograms, 15 versus 50, twice daily versus twice weekly — the number errors that quietly cost marks.',
            ],
            [
                'title'    => 'Telephone Triage: Listening for the Red Flags',
                'level'    => 'intermediate',
                'duration' => 16,
                'focus'    => 'Hearing urgency in a caller who understates it, and separating the presenting complaint from the real concern.',
            ],
            [
                'title'    => 'The Clinical Handover: Keeping Up at Speed',
                'level'    => 'intermediate',
                'duration' => 16,
                'focus'    => 'A nurse-to-nurse handover read at natural speed: names, bed numbers, observations and the one detail that matters.',
            ],
            [
                'title'    => 'History Taking: Hearing the Chronology',
                'level'    => 'beginner',
                'duration' => 15,
                'focus'    => 'Tracking onset, duration and progression when a patient tells the story out of order, and using tense to place events.',
            ],
            [
                'title'    => 'Advice, Instruction or Warning? Hearing the Difference',
                'level'    => 'intermediate',
                'duration' => 15,
                'focus'    => 'Modal verbs ("you should", "you must", "you could") and what each signals about how firm the instruction really is.',
            ],
            [
                'title'    => 'Listening to a Distressed Patient',
                'level'    => 'intermediate',
                'duration' => 16,
                'focus'    => 'Hearing the clinical information inside an emotional account, where repetition and hesitation hide the facts.',
            ],
            [
                'title'    => 'Three Speakers, One Consultation: Who Said What?',
                'level'    => 'advanced',
                'duration' => 18,
                'focus'    => 'Following a patient, a relative and a clinician at once, and attributing each opinion correctly.',
            ],
            [
                'title'    => 'Allergy and Contraindication Cues',
                'level'    => 'intermediate',
                'duration' => 15,
                'focus'    => 'Recognising allergy, intolerance and adverse-reaction language, and the hedges that distinguish them.',
            ],
            [
                'title'    => 'Negation: Hearing "No", "Never" and "Not Since"',
                'level'    => 'beginner',
                'duration' => 14,
                'focus'    => 'Why candidates write down the symptom the patient explicitly denied, and how to catch negatives at speed.',
            ],
            [
                'title'    => 'The Discharge Conversation: Instructions and Follow-Up',
                'level'    => 'intermediate',
                'duration' => 16,
                'focus'    => 'Extracting the medication changes, the warning signs and the review date from a hurried discharge talk.',
            ],
            [
                'title'    => 'General Medical Listening Mock With Annotated Transcript',
                'level'    => 'advanced',
                'duration' => 25,
                'focus'    => 'A complete three-part paper with a full transcript, showing exactly where each answer was signalled.',
            ],
        ];
    }

    /** @return list<array{title: string, level: string, duration: int, focus: string}> */
    private static function generalReading(): array
    {
        return [
            [
                'title'    => 'Reading Part A: Four Short Texts in 15 Minutes',
                'level'    => 'beginner',
                'duration' => 15,
                'focus'    => 'The scanning routine for the expeditious part: deciding what to read, what to skim and what to ignore entirely.',
            ],
            [
                'title'    => 'Reading Part B: Short Workplace Texts Under Pressure',
                'level'    => 'intermediate',
                'duration' => 15,
                'focus'    => 'Memo, email, protocol and notice — reading for purpose and audience fast enough to keep time for Part C.',
            ],
            [
                'title'    => 'Reading Part C: The Longer General Medical Article',
                'level'    => 'advanced',
                'duration' => 20,
                'focus'    => 'Mapping a research or review article before reading it, so the questions become lookups rather than re-reads.',
            ],
            [
                'title'    => 'Gist Before Detail: Reading for the Argument',
                'level'    => 'beginner',
                'duration' => 14,
                'focus'    => 'Finding the claim, the evidence and the conclusion in each paragraph before hunting for a specific fact.',
            ],
            [
                'title'    => 'True, False or Not Given — and Why It Trips Candidates',
                'level'    => 'intermediate',
                'duration' => 16,
                'focus'    => 'The difference between "the text disagrees" and "the text never says", with a decision rule for the hard cases.',
            ],
            [
                'title'    => 'Recognising Opinion, Evidence and Hedging',
                'level'    => 'advanced',
                'duration' => 18,
                'focus'    => '"The study suggests" versus "the study proves": how modal and reporting verbs carry the writer\'s real commitment.',
            ],
            [
                'title'    => 'Inference: What the Text Implies Without Saying',
                'level'    => 'intermediate',
                'duration' => 17,
                'focus'    => 'Answering from what must be true given the text, without importing outside medical knowledge.',
            ],
            [
                'title'    => 'Reading a Discharge Summary',
                'level'    => 'intermediate',
                'duration' => 15,
                'focus'    => 'Admission, course, medication changes and follow-up: reading a real discharge document in the order it is structured.',
            ],
            [
                'title'    => 'Reading Referral and Clinic Letters',
                'level'    => 'intermediate',
                'duration' => 15,
                'focus'    => 'Extracting the request, the clinical picture and the outstanding questions from a specialist letter.',
            ],
            [
                'title'    => 'Medical Statistics in Text: Percentages, Risk and Rate',
                'level'    => 'advanced',
                'duration' => 18,
                'focus'    => 'Absolute versus relative risk, incidence versus prevalence, and the sentence patterns that distinguish them.',
            ],
            [
                'title'    => 'Vocabulary in Context: Guessing Without a Dictionary',
                'level'    => 'intermediate',
                'duration'    => 15,
                'focus'    => 'Using morphology, contrast and definition clues so an unfamiliar clinical term never blocks the question.',
            ],
            [
                'title'    => 'Reading a Medication Information Leaflet',
                'level'    => 'beginner',
                'duration' => 13,
                'focus'    => 'Indications, contraindications, interactions and side effects — the standard layout and where each answer hides.',
            ],
            [
                'title'    => 'Reading Guidelines and Protocols Accurately',
                'level'    => 'advanced',
                'duration' => 18,
                'focus'    => 'Conditional and exception language in clinical guidance ("unless", "in the absence of", "except where").',
            ],
            [
                'title'    => '60 Minutes, Three Parts: Reading Time Management',
                'level'    => 'intermediate',
                'duration' => 15,
                'focus'    => 'Budgeting the hour across the three parts, and the two-minute rule that stops one question eating the paper.',
            ],
            [
                'title'    => 'General Medical Reading Mock With Annotated Answers',
                'level'    => 'advanced',
                'duration' => 25,
                'focus'    => 'A full three-part paper with every answer traced back to the line that proves it.',
            ],
        ];
    }

    /** @return list<array{title: string, level: string, duration: int, focus: string}> */
    private static function generalWriting(): array
    {
        return [
            [
                'title'    => 'Writing: The Referral Letter for Any Profession',
                'level'    => 'beginner',
                'duration' => 15,
                'focus'    => 'The fixed anatomy of an OET letter, built from case notes that belong to no particular specialty.',
            ],
            [
                'title'    => 'Writing: Choosing What Matters From Case Notes',
                'level'    => 'intermediate',
                'duration' => 18,
                'focus'    => 'Turning six pages of notes into four relevant paragraphs, and defending every inclusion against the task prompt.',
            ],
            [
                'title'    => 'Writing: The Discharge Letter',
                'level'    => 'intermediate',
                'duration' => 16,
                'focus'    => 'Summarising an admission for a GP: what happened, what changed, and what the GP must now do.',
            ],
            [
                'title'    => 'Writing: The Inter-Departmental Transfer Letter',
                'level'    => 'intermediate',
                'duration' => 16,
                'focus'    => 'Handing a patient to a colleague in another team without repeating the notes verbatim.',
            ],
            [
                'title'    => 'Writing: A Letter to a Patient or Their Family',
                'level'    => 'intermediate',
                'duration' => 16,
                'focus'    => 'Adjusting register for a non-clinical reader while keeping the content complete and precise.',
            ],
            [
                'title'    => 'Writing: Structuring a Letter of Advice to a Colleague',
                'level'    => 'intermediate',
                'duration' => 16,
                'focus'    => 'Writing to request rather than instruct, and making the specific ask unmistakable in the final paragraph.',
            ],
            [
                'title'    => 'Writing: Cutting 300 Words Down to 180',
                'level'    => 'intermediate',
                'duration' => 15,
                'focus'    => 'Compression techniques — nominalisation, subordination, cutting hedges — that keep every fact while halving the words.',
            ],
            [
                'title'    => 'Writing: Register and Tone Across the Professions',
                'level'    => 'intermediate',
                'duration' => 16,
                'focus'    => 'How a nurse, a pharmacist and a physiotherapist each address a doctor, and the register that scores in all three.',
            ],
            [
                'title'    => 'Writing: Confidentiality and Sensitive Information',
                'level'    => 'advanced',
                'duration' => 18,
                'focus'    => 'Including what the reader needs while excluding what they do not, and wording third-party information carefully.',
            ],
            [
                'title'    => 'Writing: The Nursing Discharge Summary',
                'level'    => 'intermediate',
                'duration' => 16,
                'focus'    => 'Packaging functional status, wound care, medication and social support into a concise nursing handover.',
            ],
            [
                'title'    => 'Writing: The Medication Review Letter',
                'level'    => 'advanced',
                'duration' => 18,
                'focus'    => 'Reporting interactions, adherence and dose changes to a prescriber with precision and no ambiguity.',
            ],
            [
                'title'    => 'Writing: The Allied Health Progress Letter',
                'level'    => 'intermediate',
                'duration' => 16,
                'focus'    => 'Documenting measurable progress and revised goals for a referring clinician who needs the outcome, not the session.',
            ],
            [
                'title'    => 'Writing: Punctuation, Cohesion and Flow',
                'level'    => 'beginner',
                'duration' => 14,
                'focus'    => 'Comma splices, sentence boundaries and linking words — the language errors that cap the score regardless of content.',
            ],
            [
                'title'    => 'Writing: Self-Editing in the Final Five Minutes',
                'level'    => 'intermediate',
                'duration' => 14,
                'focus'    => 'A fixed checklist run against your own draft: purpose, completeness, word count, tense consistency, closing request.',
            ],
            [
                'title'    => 'General Medical Writing Mock With an Annotated Model Letter',
                'level'    => 'advanced',
                'duration' => 25,
                'focus'    => 'A complete task, a candidate attempt with its band, and a model letter annotated against all six criteria.',
            ],
        ];
    }

    /** @return list<array{title: string, level: string, duration: int, focus: string}> */
    private static function generalSpeaking(): array
    {
        return [
            [
                'title'    => 'Speaking: Adapting the Framework to Your Profession',
                'level'    => 'beginner',
                'duration' => 15,
                'focus'    => 'A single role-play structure that works for any OET scenario, and how to bend it to your own clinical setting.',
            ],
            [
                'title'    => 'Speaking: Explaining a Diagnosis in Plain Language',
                'level'    => 'intermediate',
                'duration' => 16,
                'focus'    => 'Turning clinical vocabulary into words a worried patient can act on, without losing accuracy.',
            ],
            [
                'title'    => 'Speaking: Breaking Bad News With Care',
                'level'    => 'advanced',
                'duration' => 18,
                'focus'    => 'Structuring the conversation, warning shots, pausing, and handling the silence that follows.',
            ],
            [
                'title'    => 'Speaking: The Medication Counselling Role-Play',
                'level'    => 'intermediate',
                'duration' => 16,
                'focus'    => 'Dose, timing, side effects and adherence — counselling clearly while checking the patient has understood.',
            ],
            [
                'title'    => 'Speaking: Handling an Angry or Complaining Patient',
                'level'    => 'intermediate',
                'duration' => 16,
                'focus'    => 'Acknowledging without admitting fault, de-escalating language, and returning to the clinical task.',
            ],
            [
                'title'    => 'Speaking: The Telephone Consultation',
                'level'    => 'intermediate',
                'duration' => 16,
                'focus'    => 'Compensating for the missing visual channel: confirming, summarising and safety-netting without pauses.',
            ],
            [
                'title'    => 'Speaking: Consent Conversations',
                'level'    => 'advanced',
                'duration' => 18,
                'focus'    => 'Explaining risk and benefit, checking understanding, and respecting a refusal without persuasion.',
            ],
            [
                'title'    => 'Speaking: When English Is the Patient\'s Second Language',
                'level'    => 'intermediate',
                'duration' => 16,
                'focus'    => 'Slowing down, simplifying without patronising, checking comprehension and using an interpreter well.',
            ],
            [
                'title'    => 'Speaking: Discussing Lifestyle Change Without Lecturing',
                'level'    => 'intermediate',
                'duration' => 16,
                'focus'    => 'Motivational language that keeps the patient in control, and why advice-giving backfires.',
            ],
            [
                'title'    => 'Speaking: The Discharge Advice Conversation',
                'level'    => 'intermediate',
                'duration' => 15,
                'focus'    => 'Prioritising the three things the patient must remember, and getting them to repeat them back.',
            ],
            [
                'title'    => 'Speaking: When a Colleague Disagrees With You',
                'level'    => 'advanced',
                'duration' => 17,
                'focus'    => 'Professional disagreement: hedging, evidence, escalation, and keeping the patient\'s interest central.',
            ],
            [
                'title'    => 'Speaking: Paediatric Consultations — Child and Parent Together',
                'level'    => 'intermediate',
                'duration' => 16,
                'focus'    => 'Addressing the child at the right level while keeping the parent informed and reassured.',
            ],
            [
                'title'    => 'Speaking: Palliative and End-of-Life Conversations',
                'level'    => 'advanced',
                'duration' => 18,
                'focus'    => 'Discussing prognosis, comfort and preference honestly, and sitting with distress without filling every silence.',
            ],
            [
                'title'    => 'Speaking: Repair Strategies When You Get Stuck',
                'level'    => 'beginner',
                'duration' => 14,
                'focus'    => 'Paraphrasing, self-correction and buying time — recovering fluency without abandoning the message.',
            ],
            [
                'title'    => 'General Medical Speaking Mock With Examiner Commentary',
                'level'    => 'advanced',
                'duration' => 25,
                'focus'    => 'Two profession-neutral role-plays marked against all six criteria, with commentary on each band decision.',
            ],
        ];
    }
}
