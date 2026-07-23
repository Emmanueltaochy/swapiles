<?php

namespace App\Filament\Widgets;

use App\Models\NewsletterCampaign;
use App\Models\NewsletterRecipient;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Schema;

class NewsletterOverview extends Widget
{
    protected string $view = 'filament.widgets.newsletter-overview';

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        if (! Schema::hasTable('newsletter_campaigns')) {
            return ['empty' => true];
        }

        $campaigns = (int) NewsletterCampaign::count();
        $totalSent = (int) NewsletterCampaign::sum('sent_count');

        $uniqueOpens = (int) NewsletterRecipient::whereNotNull('opened_at')->count();
        $uniqueClicks = (int) NewsletterRecipient::whereNotNull('first_clicked_at')->count();
        $totalOpens = (int) NewsletterRecipient::sum('open_count');
        $totalClicks = (int) NewsletterRecipient::sum('click_count');

        return [
            'empty' => $campaigns === 0,
            'campaigns' => $campaigns,
            'totalSent' => $totalSent,
            'uniqueOpens' => $uniqueOpens,
            'uniqueClicks' => $uniqueClicks,
            'totalOpens' => $totalOpens,
            'totalClicks' => $totalClicks,
            'avgOpenRate' => $totalSent > 0 ? round($uniqueOpens / $totalSent * 100, 1) : 0.0,
            'avgClickRate' => $totalSent > 0 ? round($uniqueClicks / $totalSent * 100, 1) : 0.0,
        ];
    }
}
