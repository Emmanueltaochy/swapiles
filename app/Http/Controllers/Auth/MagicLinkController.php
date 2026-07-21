<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\MagicLoginLinkMail;
use App\Models\LoginToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class MagicLinkController extends Controller
{
    public function show()
    {
        return view('auth.magic-link');
    }

    public function send(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', strtolower($data['email']))->first();

        if ($user) {
            LoginToken::where('user_id', $user->id)
                ->whereNull('used_at')
                ->delete();

            $loginToken = LoginToken::create([
                'user_id' => $user->id,
                'token' => hash('sha256', Str::random(64)),
                'expires_at' => now()->addMinutes(30),
            ]);

            Mail::to($user->email)->send(new MagicLoginLinkMail($loginToken));
        }

        return back()->with('status', 'Si un compte existe avec cet email, un lien de connexion vient d’être envoyé.');
    }

    public function verify(Request $request, string $token)
    {
        $loginToken = LoginToken::where('token', $token)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->firstOrFail();

        $loginToken->update([
            'used_at' => now(),
        ]);

        Auth::login($loginToken->user);
        $request->session()->regenerate();

        return redirect()->route('account.dashboard');
    }
}
