<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\SafeRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Port of controllers/authController.js.
 *
 * Auth is Laravel's own session guard (Inertia is a same-origin app, so the
 * httpOnly session cookie does the job the JWT cookie used to). Existing
 * bcrypt password hashes verify unchanged, so nobody has to reset a password.
 */
class AuthController extends Controller
{
    private const SALT_ROUNDS = 12;

    public function showLogin(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function showRegister(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'min:2', 'max:120'],
            'email'       => ['required', 'email', 'max:180'],
            'password'    => ['required', 'string', 'min:6', 'max:72', 'regex:/\d/'],
            'country'     => ['nullable', 'string', 'max:80'],
            'target_band' => ['nullable', 'numeric', 'min:0', 'max:6'],
            'language'    => ['nullable', 'string', 'in:en,my'],
        ], [
            'name.required'       => 'Please enter your full name.',
            'name.min'            => 'Please enter your full name.',
            'email.required'      => 'Please enter a valid email address.',
            'email.email'         => 'Please enter a valid email address.',
            'password.required'   => 'Password must be at least 6 characters.',
            'password.min'        => 'Password must be at least 6 characters.',
            'password.regex'      => 'Password must contain at least one number.',
            'target_band.numeric' => 'Target band must be between 0 and 6.',
            'target_band.max'     => 'Target band must be between 0 and 6.',
        ]);

        if (User::where('email', $data['email'])->exists()) {
            throw ValidationException::withMessages([
                'email' => 'An account with that email already exists.',
            ]);
        }

        $user = User::create([
            'name'        => $data['name'],
            'email'       => $data['email'],
            'password'    => Hash::make($data['password'], ['rounds' => self::SALT_ROUNDS]),
            'role'        => User::ROLE_STUDENT,
            'country'     => $data['country'] ?? null,
            'target_band' => $data['target_band'] ?? null,
            'language'    => $data['language'] ?? 'en',
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with(
            'success',
            'Welcome aboard, '.explode(' ', $user->name)[0].'!'
        );
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required'    => 'Please enter a valid email address.',
            'email.email'       => 'Please enter a valid email address.',
            'password.required' => 'Password is required.',
        ]);

        $user = User::where('email', $credentials['email'])->first();

        // Same generic message for an unknown email and a wrong password, so the
        // form cannot be used to enumerate accounts.
        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'Incorrect email or password.',
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => 'Your account has been deactivated. Contact your administrator.',
            ]);
        }

        Auth::login($user, false); // no "remember me" — the table has no remember_token
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        // Deliberately not redirect()->intended(): it replays the stored
        // absolute URL verbatim, scheme and all, which is how a session
        // captured over http:// turns a successful sign-in into a browser
        // mixed-content block. See App\Support\SafeRedirect.
        return redirect()->to(SafeRedirect::toPath(
            $request->session()->pull('url.intended'),
            route('dashboard', [], false),
        ))->with(
            'success',
            'Welcome back, '.explode(' ', $user->name)[0].'!'
        );
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('success', 'You have been signed out.');
    }

    /** PUT /profile */
    public function updateProfile(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'        => ['sometimes', 'nullable', 'string', 'min:2', 'max:120'],
            'country'     => ['sometimes', 'nullable', 'string', 'max:80'],
            'phone'       => ['sometimes', 'nullable', 'string', 'max:40'],
            'target_band' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:6'],
            'language'    => ['sometimes', 'nullable', 'string', 'in:en,my'],
        ]);

        // Only overwrite keys the request actually carried — matching the
        // COALESCE(?, column) behaviour of the original implementation.
        $updates = array_filter(
            $data,
            fn ($value, $key) => $request->has($key),
            ARRAY_FILTER_USE_BOTH
        );

        $request->user()->update($updates);

        return back()->with('success', 'Profile updated.');
    }

    /** PUT /profile/password */
    public function changePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'currentPassword' => ['required', 'string'],
            'newPassword'     => ['required', 'string', 'min:6', 'max:72'],
        ], [
            'currentPassword.required' => 'Current password is required.',
            'newPassword.min'          => 'New password must be at least 6 characters.',
        ]);

        if (! Hash::check($data['currentPassword'], $request->user()->password)) {
            throw ValidationException::withMessages([
                'currentPassword' => 'Your current password is incorrect.',
            ]);
        }

        $request->user()->update([
            'password' => Hash::make($data['newPassword'], ['rounds' => self::SALT_ROUNDS]),
        ]);

        return back()->with('success', 'Password changed successfully.');
    }
}
