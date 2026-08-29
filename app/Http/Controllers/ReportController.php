<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use App\Models\Report;
use App\Models\User;
use App\Support\AdminEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Signalement d'un contenu par un membre (annonce ou membre). Chaque
 * signalement est enregistré et remonté à l'admin (Communauté > Signalements).
 */
class ReportController extends Controller
{
    /** Signaler une annonce. */
    public function listing(Request $request, Listing $listing): RedirectResponse
    {
        $data = $this->validated($request);

        // On ne signale pas sa propre annonce.
        if ((int) $listing->user_id === (int) Auth::id()) {
            return back()->with('error', 'Vous ne pouvez pas signaler votre propre annonce.');
        }

        $this->record($listing, $data, 'Annonce signalée', $listing->title);

        return back()->with('report_sent', true);
    }

    /** Signaler un membre. */
    public function user(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request);

        if ((int) $user->id === (int) Auth::id()) {
            return back()->with('error', 'Vous ne pouvez pas vous signaler vous-même.');
        }

        $this->record($user, $data, 'Membre signalé', $user->name);

        return back()->with('report_sent', true);
    }

    /** @return array{reason:string,details:?string} */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'in:' . implode(',', array_keys(Report::REASONS))],
            'details' => ['nullable', 'string', 'max:1000'],
        ], [
            'reason.required' => 'Choisissez un motif de signalement.',
            'reason.in' => 'Motif de signalement invalide.',
        ]);

        return [
            'reason' => $validated['reason'],
            'details' => $validated['details'] ?? null,
        ];
    }

    /**
     * Enregistre le signalement (en évitant les doublons du même membre sur la
     * même cible) et prévient l'admin.
     *
     * @param  array{reason:string,details:?string}  $data
     */
    private function record(object $target, array $data, string $adminTitle, ?string $label): void
    {
        $report = Report::updateOrCreate(
            [
                'reporter_id' => Auth::id(),
                'reportable_type' => $target::class,
                'reportable_id' => $target->getKey(),
            ],
            [
                'reason' => $data['reason'],
                'details' => $data['details'],
                'status' => 'open',
                'handled_at' => null,
            ],
        );

        try {
            AdminEvent::notify(
                $adminTitle,
                'Motif : ' . ($report->reasonLabel())
                    . ($label ? ' — « ' . $label . ' »' : '')
                    . ($data['details'] ? '. Détail : ' . $data['details'] : '')
                    . '. Signalé par ' . (Auth::user()->name ?? 'un membre') . '.',
                url('/admin/reports'),
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
