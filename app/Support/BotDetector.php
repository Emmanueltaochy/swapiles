<?php

namespace App\Support;

/**
 * Détection basique de robots/crawlers à partir du User-Agent.
 *
 * Permet d'exclure les bots (Googlebot, Bingbot, scrapers, outils SEO,
 * clients HTTP…) des compteurs de vues et des statistiques d'audience, afin
 * d'obtenir des chiffres proches du trafic humain réel.
 */
class BotDetector
{
    /** Fragments de User-Agent typiques des robots. */
    private const SIGNATURES = [
        'bot', 'crawl', 'spider', 'slurp', 'mediapartners', 'facebookexternalhit',
        'facebot', 'ia_archiver', 'archive.org', 'ahrefs', 'semrush', 'mj12',
        'dotbot', 'petalbot', 'bytespider', 'yandex', 'baidu', 'sogou', 'exabot',
        'gigabot', 'seznam', 'duckduckbot', 'applebot', 'pinterest', 'embedly',
        'quora link preview', 'bitlybot', 'skypeuripreview', 'nuzzel', 'discordbot',
        'telegrambot', 'whatsapp', 'linkedinbot', 'twitterbot', 'slackbot', 'redditbot',
        'google-inspectiontool', 'google favicon', 'gptbot', 'chatgpt', 'ccbot',
        'claudebot', 'anthropic', 'perplexity', 'amazonbot', 'headlesschrome',
        'phantomjs', 'python-requests', 'python-urllib', 'go-http-client', 'okhttp',
        'curl/', 'wget/', 'libwww', 'httpclient', 'axios/', 'node-fetch', 'scrapy',
        'lighthouse', 'pagespeed', 'gtmetrix', 'pingdom', 'uptimerobot', 'statuscake',
        'monitoring', 'censys', 'masscan', 'zgrab', 'nmap',
    ];

    public static function isBot(?string $userAgent): bool
    {
        $ua = trim((string) $userAgent);

        // Un User-Agent vide est presque toujours un script/bot.
        if ($ua === '') {
            return true;
        }

        $ua = mb_strtolower($ua);

        foreach (self::SIGNATURES as $needle) {
            if (str_contains($ua, $needle)) {
                return true;
            }
        }

        return false;
    }
}
