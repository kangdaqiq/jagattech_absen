<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $input = $request->validate([
            'email' => ['required', 'string'],
            'password' => ['required'],
        ]);

        $loginField = $input['email'];
        $password = $input['password'];

        $throttleKey = Str::transliterate(Str::lower($loginField).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return back()->withErrors([
                'email' => "Terlalu banyak percobaan login. Silakan coba lagi dalam {$seconds} detik.",
            ])->onlyInput('email');
        }

        // Try to login with email first
        if (Auth::attempt(['email' => $loginField, 'password' => $password])) {
            $request->session()->regenerate();

            // Check if user's school is active (skip for super admin)
            $user = Auth::user();
            if ($user->school_id && $user->school) {
                if (!$user->school->is_active) {
                    Auth::logout();
                    return back()->withErrors([
                        'email' => 'Sekolah Anda sedang nonaktif. Hubungi Super Admin untuk informasi lebih lanjut.',
                    ])->onlyInput('email');
                }
            }

            // Redirect based on role
            if ($user->role === 'super_admin') {
                RateLimiter::clear($throttleKey);
                return redirect()->intended(route('super-admin.dashboard'));
            }

            RateLimiter::clear($throttleKey);
            return redirect()->intended(route('dashboard'));
        }

        // If email login fails, try with username
        if (Auth::attempt(['username' => $loginField, 'password' => $password])) {
            $request->session()->regenerate();

            // Check if user's school is active (skip for super admin)
            $user = Auth::user();
            if ($user->school_id && $user->school) {
                if (!$user->school->is_active) {
                    Auth::logout();
                    return back()->withErrors([
                        'email' => 'Sekolah Anda sedang nonaktif. Hubungi Super Admin untuk informasi lebih lanjut.',
                    ])->onlyInput('email');
                }
            }

            // Redirect based on role
            if ($user->role === 'super_admin') {
                RateLimiter::clear($throttleKey);
                return redirect()->intended(route('super-admin.dashboard'));
            }

            RateLimiter::clear($throttleKey);
            return redirect()->intended(route('dashboard'));
        }

        RateLimiter::hit($throttleKey);

        return back()->withErrors([
            'email' => 'Email/Username atau password tidak sesuai.',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }
}
