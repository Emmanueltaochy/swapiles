<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Jobs\SendWelcomeEmail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            if (Auth::user()->is_banned) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return back()->withErrors([
                    'email' => 'Votre compte a été suspendu. Contactez contact@swapiles.com.',
                ])->onlyInput('email');
            }

            $request->session()->regenerate();
            return redirect()->intended(route('account.dashboard'));
        }

        return back()->withErrors([
            'email' => 'Identifiants incorrects.',
        ])->onlyInput('email');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'comment_connu' => ['nullable', 'string', 'max:255'],
            'comment_connu_autre' => ['nullable', 'string', 'max:255'],
        ]);

        // Si « Autre » est choisi, on enregistre la précision saisie.
        $commentConnu = $data['comment_connu'] ?? null;
        if ($commentConnu === 'Autre' && ! empty($data['comment_connu_autre'])) {
            $commentConnu = 'Autre : ' . $data['comment_connu_autre'];
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'password' => Hash::make($data['password']),
            'territoire' => $request->cookie('swapiles_territoire', 'La Réunion'),
            'comment_connu' => $commentConnu,
            'rating' => 0,
            'transactions_count' => 0,
        ]);

        Auth::login($user);

        try {
            SendWelcomeEmail::dispatch($user->id);
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('account.dashboard')
            ->with('status', "Bienvenue sur Swap'Îles ! Un e-mail de bienvenue vient de vous être envoyé.")
            ->with('pixel_event', ['event' => 'CompleteRegistration', 'params' => []]);
    }

    public function verifyEmail(Request $request, $id, $hash)
    {
        $user = User::find($id);

        if (!$user || !hash_equals((string) $hash, sha1($user->email))) {
            abort(403);
        }

        if (is_null($user->email_verified_at)) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        if (Auth::check() && Auth::id() === $user->id) {
            return redirect()->route('account.dashboard')
                ->with('status', '✅ Votre adresse e-mail est confirmée, merci !');
        }

        return redirect()->route('login')
            ->with('status', '✅ Adresse e-mail confirmée. Vous pouvez vous connecter.');
    }

    public function resendVerification(Request $request)
    {
        $user = $request->user();

        if ($user && is_null($user->email_verified_at)) {
            try {
                SendWelcomeEmail::dispatch($user->id);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return back()->with('status', 'E-mail de confirmation renvoyé.');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
