<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class RegulatoryFetcher
{
    // All regulatory sources — QA verified working feeds only
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
            'name'        => 'EBA (EU)',
            'country'     => 'EU',
            'flag'        => '🇪🇺',
            'color'       => '#003399',
            'url'         => 'https://www.eba.europa.eu/news-press/news/rss.xml',
            'type'        => 'rss',
            'description' => 'European Banking Authority',
        ],
        [
            'name'        => 'BIS (Global)',
            'country'     => 'Global',
            'flag'        => '🌍',
            'color'       => '#1a56db',
            'url'         => 'https://www.bis.org/doclist/all_pressrels.rss',
            'type'        => 'rss',
            'description' => 'Bank for International Settlements',
        ],
        [
            'name'        => 'FinCEN (USA)',
            'country'     => 'USA',
            'flag'        => '🇺🇸',
            'color'       => '#B22234',
            'url'         => 'https://www.federalregister.gov/api/v1/articles.rss?conditions%5Bagencies%5D%5B%5D=financial-crimes-enforcement-network',
            'type'        => 'rss',
            'description' => 'Financial Crimes Enforcement Network',
        ],
        [
            'name'        => 'OFAC (USA)',
            'country'     => 'USA',
            'flag'        => '🇺🇸',
            'color'       => '#8B0000',
            'url'         => 'https://www.federalregister.gov/api/v1/articles.rss?conditions%5Bagencies%5D%5B%5D=office-of-foreign-assets-control',
            'type'        => 'rss',
            'description' => 'Office of Foreign Assets Control',
        ],
        [
            'name'        => 'RBI (India)',
            'country'     => 'India',
            'flag'        => '🇮🇳',
            'color'       => '#FF9933',
            'url'         => 'https://rbi.org.in/pressreleases_rss.xml',
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
        [
            'name'        => 'OSFI (Canada)',
            'country'     => 'Canada',
            'flag'        => '🇨🇦',
            'color'       => '#D80621',
            'url'         => 'https://www.osfi-bsif.gc.ca/en/news',
            'type'        => 'web',
            'description' => 'Office of the Superintendent of Financial Institutions',
        ],
        [
            'name'        => 'FSB (Global)',
            'country'     => 'Global',
            'flag'        => '🌐',
            'color'       => '#2d3748',
            'url'         => 'https://www.fsb.org/feed/',
            'type'        => 'rss',
            'description' => 'Financial Stability Board',
        ],
        [
            'name'        => 'BIS Speeches',
            'country'     => 'Global',
            'flag'        => '🏦',
            'color'       => '#1e40af',
            'url'         => 'https://www.bis.org/doclist/cbspeeches.rss',
            'type'        => 'rss',
            'description' => 'Central Bank Speeches — BIS',
        ],
        [
            'name'        => 'CBSL (Sri Lanka)',
            'country'     => 'Sri Lanka',
            'flag'        => '🇱🇰',
            'color'       => '#8B0000',
            'url'         => 'https://www.cbsl.gov.lk/en/news/rss',
            'type'        => 'rss',
            'description' => 'Central Bank of Sri Lanka',
        ],
        [
            'name'        => 'BIS Research',
            'country'     => 'Global',
            'flag'        => '📊',
            'color'       => '#374151',
            'url'         => 'https://www.bis.org/doclist/rss_all_categories.rss',
            'type'        => 'rss',
            'description' => 'BIS Publications — All Categories',
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

        // Detect RSS 1.0/RDF — use xpath to extract items reliably
        $isRdf = false;
        $rdfNs = $feed->getNamespaces(true);
        if (isset($rdfNs['rdf']) || (strpos($xml, 'rdf:RDF') !== false && !isset($feed->channel))) {
            $isRdf = true;
            $feed->registerXPathNamespace('rss', 'http://purl.org/rss/1.0/');
            $feed->registerXPathNamespace('dc',  'http://purl.org/dc/elements/1.1/');
        }

        if ($isRdf) {
            $xpathItems = $feed->xpath('//rss:item') ?: $feed->xpath('//item') ?: [];
            foreach ($xpathItems as $node) {
                if (count($items) >= 5) break;
                $dc      = $node->children('http://purl.org/dc/elements/1.1/');
                $title   = (string)($node->title ?? '');
                $link    = (string)($node->link ?? '');
                $desc    = strip_tags((string)($node->description ?? ''));
                $dateStr = (string)($dc->date ?? $node->pubDate ?? '');
                $dateTs  = $dateStr ? strtotime($dateStr) : 0;
                $dateTs  = $dateTs ?: time();
                $items[] = [
                    'title'   => $this->truncate(html_entity_decode($title, ENT_QUOTES, 'UTF-8'), 120),
                    'link'    => $link,
                    'summary' => $this->truncate(html_entity_decode($desc, ENT_QUOTES, 'UTF-8'), 200),
                    'date'    => $dateTs ? date('M d, Y', $dateTs) : 'Unknown date',
                    'date_ts' => $dateTs,
                    'badge'   => $this->detectBadge($title . ' ' . $desc),
                ];
            }
            return $items ?: $this->errorItems($source, 'No items in RDF feed');
        }

        $nodes = $isAtom ? $feed->entry : ($feed->channel->item ?? []);

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
