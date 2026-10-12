<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Hash Driver
    |--------------------------------------------------------------------------
    |
    | This option controls the default hash driver that will be used to hash
    | passwords for your application. By default, the bcrypt algorithm is
    | used; however, you remain free to modify this option if you wish.
    |
    | Supported: "bcrypt", "argon", "argon2id"
    |
    */

    'driver' => 'bcrypt',

    /*
    |--------------------------------------------------------------------------
    | Bcrypt Options
    |--------------------------------------------------------------------------
    |
    | Here you may specify the configuration options that should be used when
    | passwords are hashed using the Bcrypt algorithm. This will allow you
    | to control the amount of time it takes to hash the given password.
    |
    | IMPORTANT — `verify` MUST stay false in this project.
    |
    | The 3 pre-existing accounts were created by the previous Node/Express
    | stack using bcryptjs, which emits `$2a$` hashes. Laravel's BcryptHasher
    | refuses anything that is not `$2y$` ("This password does not use the
    | Bcrypt algorithm") and throws a 500 on every login.
    |
    | `$2a$` and `$2y$` are the SAME algorithm — only the version marker
    | differs — and PHP's password_verify() accepts both. Turning the check off
    | lets the existing hashes verify unchanged, so nobody has to reset a
    | password after the migration. New passwords are still hashed with bcrypt
    | at 12 rounds; Laravel writes them as `$2y$`.
    |
    */

    'bcrypt' => [
        'rounds' => env('BCRYPT_ROUNDS', 12),
        'verify' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Argon Options
    |--------------------------------------------------------------------------
    |
    | Here you may specify the configuration options that should be used when
    | passwords are hashed using the Argon algorithm. These will allow you
    | to control the amount of time it takes to hash the given password.
    |
    */

    'argon' => [
        'memory' => 65536,
        'threads' => 1,
        'time' => 4,
        'verify' => true,
    ],

];
