<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Models\LoginToken;
use App\Mail\MagicLoginLinkMail;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class CommunityNewsletter extends Page
{
    protected static ?string $navigationLabel = 'Newsletter';
    protected static ?string $title = 'Newsletter communauté';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-envelope';
    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.community-newsletter';

    public ?array $data = [];

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                TextInput::make('subject')->label('Objet')->required()->maxLength(120),

                Select::make('format')
                    ->label('Format du message')
                    ->required()
                    ->options([
                        'text' => 'Texte simple',
                        'html' => 'HTML',
                    ])
                    ->default('text'),

                Select::make('audience')
                    ->label('Destinataires')
                    ->required()
                    ->options([
                        'all' => 'Tous les membres',
                        'sellers' => 'Vendeurs avec au moins une annonce',
                        'buyers' => 'Membres sans annonce',
                        'manual' => 'Emails manuels uniquement',
                        'audience_plus_manual' => 'Audience sélectionnée + emails manuels',
                    ])
                    ->default('manual'),

                Textarea::make('manual_emails')
                    ->label('Emails manuels')
                    ->rows(4)
                    ->placeholder("email1@gmail.com\nemail2@gmail.com")
                    ->helperText('Séparez les emails par virgule, espace ou retour à la ligne.'),

                Textarea::make('message')
                    ->label('Message')
                    ->required()
                    ->rows(12)
                    ->maxLength(15000),

                TextInput::make('button_label')
                    ->label('Texte du bouton')
                    ->default('Voir la plateforme')
                    ->maxLength(40),

                TextInput::make('button_url')
                    ->label('Lien du bouton')
                    ->default(fn () => url('/'))
                    ->url()
                    ->required(),
            ]);
    }

    public function send(): void
    {
        $state = $this->form->getState();
        $state = array_merge($this->data ?? [], $state);

        $audience = $state['audience'] ?? 'manual';
        $manualEmails = $this->parseEmails($state['manual_emails'] ?? '');

        // Fallback si Filament/Livewire ne remonte pas le champ manuel.
        if (empty($manualEmails)) {
            $manualEmails = $this->parseEmails(json_encode($this->data) . ' ' . json_encode(request()->all()));
        }

        $recipients = [];

        if (in_array($audience, ['all', 'sellers', 'buyers', 'audience_plus_manual'], true)) {
            $query = User::query()->whereNotNull('email');

            if ($audience === 'sellers') {
                $query->whereHas('listings');
            }

            if ($audience === 'buyers') {
                $query->whereDoesntHave('listings');
            }

            $query->pluck('email')->each(function ($email) use (&$recipients) {
                $email = strtolower(trim((string) $email));
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $recipients[$email] = $email;
                }
            });
        }

        if (in_array($audience, ['manual', 'audience_plus_manual'], true)) {
            foreach ($manualEmails as $email) {
                $recipients[$email] = $email;
            }
        }

        if (count($recipients) === 0) {
            FilamentNotification::make()
                ->title('Aucun email envoyé')
                ->body('Aucun destinataire valide trouvé.')
                ->danger()
                ->send();
            return;
        }

        $sent = 0;
        $failed = 0;

        foreach ($recipients as $email) {
            try {
                if (($state['format'] ?? 'text') === 'html') {
                    Mail::html($this->htmlBody($state), function ($message) use ($email, $state) {
                        $message->from('contact@swapiles.com', 'Swap’Îles')
                            ->to($email)
                            ->subject($state['subject']);
                    });
                } else {
                    Mail::raw($this->textBody($state), function ($message) use ($email, $state) {
                        $message->from('contact@swapiles.com', 'Swap’Îles')
                            ->to($email)
                            ->subject($state['subject']);
                    });
                }

                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                report($e);
            }
        }

        FilamentNotification::make()
            ->title('Newsletter envoyée')
            ->body($sent . ' email(s) envoyé(s).' . ($failed ? ' ' . $failed . ' erreur(s).' : ''))
            ->success()
            ->send();
    }

    private function parseEmails(?string $raw): array
    {
        preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', (string) $raw, $matches);

        return collect($matches[0] ?? [])
            ->map(fn ($email) => strtolower(trim($email)))
            ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();
    }

    private function textBody(array $state): string
    {
        return trim($state['message'] ?? '') . "\n\n" .
            ($state['button_label'] ?? 'Voir Swap’Îles') . " : " . ($state['button_url'] ?? url('/')) . "\n\n" .
            "L’équipe Swap’Îles\nhttps://swapiles.com";
    }

    private function htmlBody(array $state): string
    {
        $subject = e($state['subject'] ?? 'Swap’Îles');
        $html = $state['message'] ?? '';
        $buttonLabel = e($state['button_label'] ?? 'Voir Swap’Îles');
        $buttonUrl = e($state['button_url'] ?? url('/'));

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,sans-serif;color:#111827;">
<div style="max-width:620px;margin:0 auto;padding:28px 16px;">
<div style="background:#fff;border-radius:24px;overflow:hidden;border:1px solid #e5e7eb;">
<div style="padding:28px;text-align:center;background:#0f766e;color:#fff;">
<div style="font-size:30px;font-weight:900;">Swap’Îles</div>
<div style="font-size:14px;margin-top:6px;">La marketplace seconde main des îles</div>
</div>
<div style="padding:30px;">
<h1 style="font-size:24px;margin:0 0 20px;color:#111827;">{$subject}</h1>
<div style="font-size:16px;line-height:1.7;color:#374151;">{$html}</div>
<div style="text-align:center;margin-top:28px;">
<a href="{$buttonUrl}" style="display:inline-block;background:#0f766e;color:#ffffff;text-decoration:none;font-weight:800;padding:14px 24px;border-radius:16px;">{$buttonLabel}</a>
</div>
</div>
<div style="padding:22px 30px;background:#f9fafb;color:#6b7280;font-size:13px;line-height:1.6;text-align:center;">
Merci de faire partie de la communauté Swap’Îles.<br>
<strong>L’équipe Swap’Îles</strong><br>
<a href="https://swapiles.com" style="color:#0f766e;">swapiles.com</a>
</div>
</div>
</div>
</body>
</html>
HTML;
    }

    public function sendMagicLinksToAllUsers(): void
    {
        $sent = 0;
        $failed = 0;

        User::query()
            ->whereNotNull('email')
            ->orderBy('id')
            ->chunkById(100, function ($users) use (&$sent, &$failed) {
                foreach ($users as $user) {
                    try {
                        LoginToken::where('user_id', $user->id)
                            ->whereNull('used_at')
                            ->delete();

                        $loginToken = LoginToken::create([
                            'user_id' => $user->id,
                            'token' => hash('sha256', Str::random(64)),
                            'expires_at' => now()->addDays(7),
                        ]);

                        Mail::to($user->email)->send(new MagicLoginLinkMail($loginToken, true));

                        $sent++;
                    } catch (\Throwable $e) {
                        $failed++;
                        report($e);
                    }
                }
            });

        \Filament\Notifications\Notification::make()
            ->title('Magic Links envoyés')
            ->body($sent . ' email(s) envoyé(s).' . ($failed ? ' ' . $failed . ' erreur(s).' : ''))
            ->success()
            ->send();
    }

}
