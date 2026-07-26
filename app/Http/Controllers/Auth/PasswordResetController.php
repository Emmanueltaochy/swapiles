<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    public function request()
    {
        return view('auth.forgot-password');
    }

    public function email(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink($request->only('email'));

        // Point 6 : mesurer l'entonnoir. On logue chaque étape sous le tag
        // [pwd-reset] pour reconstituer demande -> clic -> succès.
        Log::info('[pwd-reset] link_requested', [
            'email' => $request->input('email'),
            'status' => $status, // passwords.sent / passwords.user (email inconnu)
        ]);

        return back()->with('status', 'Si un compte existe avec cet email, un lien de réinitialisation vient d’être envoyé.');
    }

    public function reset(string $token)
    {
        Log::info('[pwd-reset] link_opened', ['email' => request('email')]);

        return view('auth.reset-password', ['token' => $token]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();
            }
        );

        Log::info('[pwd-reset] update_attempt', [
            'email' => $request->input('email'),
            'status' => $status, // passwords.reset (succès) / passwords.token (lien invalide/expiré)
        ]);

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', 'Mot de passe réinitialisé. Vous pouvez vous connecter.')
            : back()->withErrors(['email' => 'Le lien est invalide ou expiré.']);
    }
}
