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
    public function showLogin(Request $request)
    {
        $this->rememberIntendedFrom($request);

        return view('auth.login');
    }

    public function showRegister(Request $request)
    {
        $this->rememberIntendedFrom($request);

        $valid = ['La Réunion', 'Martinique', 'Guadeloupe', 'Guyane', 'Mayotte'];
        $cookie = $request->cookie('swapiles_territoire');
        $preselect = in_array($cookie, $valid, true) ? $cookie : 'La Réunion';

        return view('auth.register', ['territoirePreselect' => $preselect]);
    }

    /**
     * Mémorise la page consultée avant l'auth pour y revenir après connexion /
     * inscription (redirect()->intended()). Presque toujours déclenchée par une
     * intention d'achat depuis une fiche annonce : on ne doit pas la perdre.
     */
    private function rememberIntendedFrom(Request $request): void
    {
        // Ne pas écraser une destination déjà mémorisée (ex. connexion -> inscription,
        // ou intended posé par le middleware auth sur une route protégée).
        if ($request->session()->has('url.intended')) {
            return;
        }

        $previous = url()->previous();
        if (blank($previous) || ! str_starts_with($previous, (string) config('app.url'))) {
            return;
        }

        // Ne pas revenir sur une page d'auth (éviterait une boucle).
        $path = (string) parse_url($previous, PHP_URL_PATH);
        foreach (['/connexion', '/inscription', '/deconnexion', '/mot-de-passe', '/magic-link', '/admin'] as $authPath) {
            if (str_starts_with($path, $authPath)) {
                return;
            }
        }

        $request->session()->put('url.intended', $previous);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            if (Auth::user()->is_banned || \App\Models\BlockedEmail::isBlocked(Auth::user()->email)) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return back()->withErrors([
                    'email' => 'Votre compte a été suspendu. Contactez contact@swapiles.com.',
                ])->onlyInput('email');
            }

            \App\Models\UserSession::record(Auth::id(), $request, 'login');

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
            'email' => ['required', 'email', 'max:255', 'unique:users,email', function ($attribute, $value, $fail) {
                if (\App\Models\BlockedEmail::isBlocked($value)) {
                    $fail("Cette adresse e-mail n’est pas autorisée à s’inscrire.");
                }
            }],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'comment_connu' => ['nullable', 'string', 'max:255'],
            'comment_connu_autre' => ['nullable', 'string', 'max:255'],
            'reseaux' => ['nullable', 'array'],
            'reseaux.*' => ['string', 'max:50'],
            'territoire' => ['required', 'string', 'in:La Réunion,Martinique,Guadeloupe,Guyane,Mayotte'],
        ]);

        $commentConnu = $data['comment_connu'] ?? null;

        // Si « Réseaux sociaux » est choisi, on précise le(s)quel(s).
        if (is_string($commentConnu) && str_starts_with($commentConnu, 'Réseaux sociaux') && ! empty($data['reseaux'])) {
            $reseaux = array_slice(array_filter($data['reseaux']), 0, 8);
            $commentConnu = 'Réseaux sociaux : ' . implode(', ', $reseaux);
        }

        // Si « Autre » est choisi, on enregistre la précision saisie.
        if ($commentConnu === 'Autre' && ! empty($data['comment_connu_autre'])) {
            $commentConnu = 'Autre : ' . $data['comment_connu_autre'];
        }

        $commentConnu = $commentConnu ? mb_substr($commentConnu, 0, 255) : null;

        $user = User::create([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'password' => Hash::make($data['password']),
            'territoire' => $data['territoire'],
            'comment_connu' => $commentConnu,
            'rating' => 0,
            'transactions_count' => 0,
        ]);

        Auth::login($user);

        \App\Models\UserSession::record($user->id, $request, 'registration');

        try {
            SendWelcomeEmail::dispatch($user->id);
        } catch (\Throwable $e) {
            report($e);
        }

        // Retour sur la page consultée avant l'inscription (fiche annonce le
        // plus souvent), sinon tableau de bord.
        return redirect()->intended(route('account.dashboard'))
            ->with('status', "Bienvenue sur Swap'Îles ! Un e-mail de bienvenue vient de vous être envoyé.")
            ->with('pixel_event', ['event' => 'CompleteRegistration', 'params' => []])
            ->withCookie(cookie('swapiles_territoire', $data['territoire'], 60 * 24 * 365));
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
