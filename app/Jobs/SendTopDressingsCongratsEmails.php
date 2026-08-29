<?php

namespace App\Jobs;

use App\Support\DressingLeaderboard;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

/**
 * Envoie un e-mail de félicitations aux vendeurs du Top des dressings
 * (« Bravo, tu es dans le Top X ! ») avec le lien vers le classement.
 * Déclenché manuellement depuis l'admin.
 */
class SendTopDressingsCongratsEmails implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;
    public int $timeout = 120;

    public function __construct(public ?int $limit = null)
    {
    }

    public function handle(): void
    {
        $top = DressingLeaderboard::top($this->limit);
        $total = $top->count();

        if ($total === 0) {
            return;
        }

        try {
            $url = route('dressings.top');
        } catch (\Throwable $e) {
            $url = 'https://swapiles.com/meilleurs-dressings';
        }

        foreach ($top as $row) {
            $user = $row->user ?? null;
            if (! $user?->email) {
                continue;
            }

            [$subject, $body] = self::buildEmail($user->name ?? 'toi', (int) $row->rank, $total, $url);

            try {
                Mail::raw($body, function ($mail) use ($user, $subject) {
                    $mail->from('contact@swapiles.com', "Swap'Îles")
                        ->to($user->email)
                        ->subject($subject);
                });
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    /**
     * Sujet + corps de l'e-mail de félicitations. Public/statique pour être
     * testable sans envoi réel.
     *
     * @return array{0:string,1:string}
     */
    public static function buildEmail(string $name, int $rank, int $total, string $url): array
    {
        $subject = "🏆 Bravo {$name}, tu es dans le Top {$total} des dressings Swap'Îles !";

        $body = "Bonjour {$name},\n\n"
            . "Félicitations ! 🎉 Ton dressing fait partie des MEILLEURS de Swap'Îles : "
            . "tu es actuellement classé n°{$rank} au Top {$total} des dressings les plus populaires de nos îles.\n\n"
            . "C'est le fruit de ton activité : tes articles plaisent, on te suit, on t'achète. "
            . "Continue comme ça — chaque nouvelle annonce, chaque vente et chaque favori te fait grimper.\n\n"
            . "👉 Découvre le classement complet : {$url}\n\n"
            . "Merci de faire vivre la seconde main dans les Outre-mer 🌴\n\n"
            . "L'équipe Swap'Îles\nhttps://swapiles.com";

        return [$subject, $body];
    }
}
