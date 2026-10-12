<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Idempotent demo seeding.
 *
 * The `ortexamm` database is shared with the pre-migration Node application and
 * is NOT rebuilt, so this seeder is deliberately non-destructive: `firstOrCreate`
 * means it creates the demo accounts when they are missing and leaves an
 * existing account — including a changed password — completely untouched.
 *
 * Exam / question / lesson content is NOT authored here. It lives in the
 * database and can be restored on a fresh machine from `database/schema.sql`
 * and `database/seed.sql` (see the README's Database section).
 */
class DatabaseSeeder extends Seeder
{
    /** @var list<array<string, mixed>> */
    private const DEMO_ACCOUNTS = [
        [
            'name'        => 'Platform Admin',
            'email'       => 'admin@oetradiographer.com',
            'password'    => 'Admin@123',
            'role'        => User::ROLE_ADMIN,
            'country'     => 'Myanmar',
            'target_band' => 6.0,
            'language'    => 'en',
        ],
        [
            'name'        => 'Demo Student',
            'email'       => 'student@oetradiographer.com',
            'password'    => 'Student@123',
            'role'        => User::ROLE_STUDENT,
            'country'     => 'Myanmar',
            'target_band' => 6.0,
            'language'    => 'en',
        ],
    ];

    public function run(): void
    {
        foreach (self::DEMO_ACCOUNTS as $account) {
            $user = User::firstOrCreate(
                ['email' => $account['email']],
                [
                    'name'        => $account['name'],
                    'password'    => Hash::make($account['password']),
                    'role'        => $account['role'],
                    'country'     => $account['country'],
                    'target_band' => $account['target_band'],
                    'language'    => $account['language'],
                    'is_active'   => true,
                ]
            );

            $this->command?->line(sprintf(
                '  %s %s (%s)',
                $user->wasRecentlyCreated ? 'created' : 'kept   ',
                $account['email'],
                $account['role']
            ));
        }

        $this->command?->newLine();
        $this->command?->warn(
            'Exam, question and lesson content is not seeded from PHP — it lives in the '
            .'database and is restored from database/schema.sql + database/seed.sql.'
        );
    }
}
