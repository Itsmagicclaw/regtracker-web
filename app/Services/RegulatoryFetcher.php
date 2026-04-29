<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class RegulatoryFetcher
{
    // All regulatory sources with country tags
    private array $sources = [
        [
            'name'        => 'FCA (UK)',
            'country'     => 'UK',
            'flag'        => '🇬🇧',
            'color'       => '#003087',
            'url'         => 'https://www.fca.org.uk/news/rss.xml',
            'type'        => 'rss',
            'description' => 'Financial Conduct Authority',
        ],
        [
            'name'        => 'FinCEN (USA)',
            'country'     => 'USA',
            'flag'        => '🇺🇸',
            'color'       => '#B22234',
            'url'         => 'https://www.fincen.gov/news/rss',
            'type'        => 'rss',
            'description' => 'Financial Crimes Enforcement Network',
        ],
        [
            'name'        => 'OFAC (USA)',
            'country'     => 'USA',
            'flag'        => '🇺🇸',
            'color'       => '#B22234',
            'url'         => 'https://home.treasury.gov/news/press-releases/feed',
            'type'        => 'rss',
            'description' => 'Office of Foreign Assets Control',
        ],
        [
            'name'        => 'FATF',
            'country'     => 'Global',
            'flag'        => '🌍',
            'color'       => '#1a56db',
            'url'         => 'https://www.fatf-gafi.org/en/publications.rss.xml',
            'type'        => 'rss',
            'description' => 'Financial Action Task Force',
        ],
        [
            'name'        => 'FINTRAC (Canada)',
            'country'     => 'Canada',
            'flag'        => '🇨🇦',
            'color'       => '#D80621',
            'url'         => 'https://www.fintrac-canafe.gc.ca/util/feed/newsen',
            'type'        => 'rss',
            'description' => 'Financial Transactions & Reports Analysis Centre',
        ],
        [
            'name'        => 'AUSTRAC (Australia)',
            'country'     => 'Australia',
            'flag'        => '🇦🇺',
            'color'       => '#00843D',
            'url'         => 'https://www.austrac.gov.au/news-media/news?type=news&format=feed&type=rss',
            'type'        => 'rss',
            'description' => 'Australian Transaction Reports & Analysis Centre',
        ],
        [
            'name'        => 'EBA (EU)',
            'country'     => 'EU',
            'flag'        => '🇪🇺',
            'color'       => '#003399',
            'url'         => 'https://www.eba.europa.eu/rss-feed/news',
            'type'        => 'rss',
            'description' => 'European Banking Authority',
        ],
        [
            'name'        => 'FinCEN Advisories (USA)',
            'country'     => 'USA',
            'flag'        => '🇺🇸',
            'color'       => '#7B1818',
            'url'         => 'https://www.federalregister.gov/api/v1/articles.rss?conditions%5Bagencies%5D%5B%5D=financial-crimes-enforcement-network&conditions%5Btype%5D%5B%5D=Rule',
            'type'        => 'rss',
            'description' => 'US Federal Register — FinCEN Rules',
        ],
        [
            'name'        => 'CBUAE (UAE)',
            'country'     => 'UAE',
            'flag'        => '🇦🇪',
            'color'       => '#00732F',
            'url'         => 'https://www.centralbank.ae/umbraco/Surface/NewsAndPublications/Rss?culture=en',
            'type'        => 'rss',
            'description' => 'Central Bank of UAE',
        ],
        [
            'name'        => 'SEC (USA)',
            'country'     => 'USA',
            'flag'        => '🇺🇸',
            'color'       => '#003087',
            'url'         => 'https://www.sec.gov/cgi-bin/browse-edgar?action=getcurrent&type=&dateb=&owner=include&count=20&search_text=&output=atom',
            'type'        => 'rss',
            'description' => 'Securities & Exchange Commission',
        ],
        [
            'name'        => 'RBI (India)',
            'country'     => 'India',
            'flag'        => '🇮🇳',
            'color'       => '#FF9933',
            'url'         => 'https://www.rbi.org.in/Scripts/Rss.aspx?Id=2',
            'type'        => 'rss',
            'description' => 'Reserve Bank of India',
        ],
        [
            'name'        => 'MAS (Singapore)',
            'country'     => 'Singapore',
            'flag'        => '🇸🇬',
            'color'       => '#EF3340',
            'url'         => 'https://www.mas.gov.sg/news',
            'type'        => 'web',
            'description' => 'Monetary Authority of Singapore',
        ],
    ];

    public function getSources(): array
    {
        return $this->sources;
    }

    public function fetchAll(?string $country = null): array
    {
        $sources = $country && $country !== 'all'
            ? array_filter($this->sources, fn($s) => $s['country'] === $country)
            : $this->sources;

        $results = [];
        foreach ($sources as $source) {
            $cacheKey = 'reg_' . md5($source['url']);
            $items = Cache::remember($cacheKey, 3600, function () use ($source) {
                return $this->fetchSource($source);
            });
            $results[] = [
                'source'      => $source,
                'items'       => $items,
                'item_count'  => count($items),
                'last_fetched'=> now()->format('M d, Y H:i') . ' UTC',
            ];
        }

        // Sort by sources that have most recent items first
        usort($results, function ($a, $b) {
            $aDate = $a['items'][0]['date_ts'] ?? 0;
            $bDate = $b['items'][0]['date_ts'] ?? 0;
            return $bDate <=> $aDate;
        });

        return $results;
    }

    private function fetchSource(array $source): array
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'User-Agent' => 'RegTracker/1.0 (Regulatory Monitoring Tool)',
                    'Accept'     => 'application/rss+xml, application/xml, text/xml, */*',
                ])
                ->get($source['url']);

            if (!$response->successful()) {
                return $this->errorItems($source, 'HTTP ' . $response->status());
            }

            if ($source['type'] === 'rss') {
                return $this->parseRss($response->body(), $source);
            }

            return $this->parseWeb($response->body(), $source);

        } catch (\Exception $e) {
            return $this->errorItems($source, $e->getMessage());
        }
    }

    private function parseRss(string $xml, array $source): array
    {
        libxml_use_internal_errors(true);
        $feed = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);

        if (!$feed) {
            return $this->errorItems($source, 'Invalid RSS feed');
        }

        $items  = [];
        $isAtom = isset($feed->entry);
        $nodes  = $isAtom ? $feed->entry : ($feed->channel->item ?? []);

        foreach ($nodes as $node) {
            if (count($items) >= 5) break;

            if ($isAtom) {
                $title   = (string)($node->title ?? '');
                $link    = (string)($node->link['href'] ?? $node->link ?? '');
                $desc    = strip_tags((string)($node->summary ?? $node->content ?? ''));
                $dateStr = (string)($node->updated ?? $node->published ?? '');
            } else {
                $title   = (string)($node->title ?? '');
                $link    = (string)($node->link ?? '');
                $desc    = strip_tags((string)($node->description ?? ''));
                $dateStr = (string)($node->pubDate ?? $node->date ?? '');
            }

            $dateTs = $dateStr ? strtotime($dateStr) : 0;
            $dateTs = $dateTs ?: time();

            $items[] = [
                'title'   => $this->truncate(html_entity_decode($title, ENT_QUOTES, 'UTF-8'), 120),
                'link'    => $link,
                'summary' => $this->truncate(html_entity_decode($desc, ENT_QUOTES, 'UTF-8'), 200),
                'date'    => $dateTs ? date('M d, Y', $dateTs) : 'Unknown date',
                'date_ts' => $dateTs,
                'badge'   => $this->detectBadge($title . ' ' . $desc),
            ];
        }

        return $items ?: $this->errorItems($source, 'No items found in feed');
    }

    private function parseWeb(string $html, array $source): array
    {
        // Generic web page extraction — grab <title> and <a> tags with date hints
        $items = [];
        preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/si', $html, $matches);

        foreach ($matches[1] as $i => $href) {
            if (count($items) >= 5) break;
            $text = trim(strip_tags($matches[2][$i] ?? ''));
            if (strlen($text) < 20 || strlen($text) > 300) continue;
            if (preg_match('/nav|menu|skip|cookie|home|contact/i', $text)) continue;

            $fullUrl = str_starts_with($href, 'http') ? $href : (rtrim($source['url'], '/') . '/' . ltrim($href, '/'));

            $items[] = [
                'title'   => $this->truncate($text, 120),
                'link'    => $fullUrl,
                'summary' => 'Visit source for full details.',
                'date'    => date('M d, Y'),
                'date_ts' => time(),
                'badge'   => $this->detectBadge($text),
            ];
        }

        return $items ?: $this->errorItems($source, 'Could not extract items from page');
    }

    private function detectBadge(string $text): array
    {
        $text = strtolower($text);
        if (preg_match('/sanction|ofac|sdn|block/i', $text))
            return ['label' => 'Sanctions', 'color' => '#7c3aed', 'bg' => '#ede9fe'];
        if (preg_match('/aml|anti.money|laundering|kyc/i', $text))
            return ['label' => 'AML/KYC',   'color' => '#b45309', 'bg' => '#fef3c7'];
        if (preg_match('/fine|penalty|enforcement|action|breach/i', $text))
            return ['label' => 'Enforcement','color' => '#dc2626', 'bg' => '#fee2e2'];
        if (preg_match('/guideline|guidance|consult|proposal|draft/i', $text))
            return ['label' => 'Guidance',   'color' => '#0284c7', 'bg' => '#e0f2fe'];
        if (preg_match('/rule|regulation|amend|update|policy|circular/i', $text))
            return ['label' => 'Regulation', 'color' => '#16a34a', 'bg' => '#dcfce7'];
        return ['label' => 'Update', 'color' => '#475569', 'bg' => '#f1f5f9'];
    }

    private function truncate(string $str, int $len): string
    {
        $str = trim(preg_replace('/\s+/', ' ', $str));
        return mb_strlen($str) > $len ? mb_substr($str, 0, $len) . '…' : $str;
    }

    private function errorItems(array $source, string $reason): array
    {
        return [[
            'title'   => 'Unable to fetch latest updates',
            'link'    => $source['url'],
            'summary' => 'Source temporarily unavailable: ' . $reason . '. Click to visit directly.',
            'date'    => date('M d, Y'),
            'date_ts' => 0,
            'badge'   => ['label' => 'Unavailable', 'color' => '#94a3b8', 'bg' => '#f1f5f9'],
        ]];
    }
}
