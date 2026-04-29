<?php

namespace App\Http\Controllers;

use App\Services\RegulatoryFetcher;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private RegulatoryFetcher $fetcher) {}

    public function index(Request $request)
    {
        $country  = $request->get('country', 'all');
        $sources  = $this->fetcher->getSources();
        $countries = array_values(array_unique(array_column($sources, 'country')));
        sort($countries);
        $results  = $this->fetcher->fetchAll($country === 'all' ? null : $country);
        $total    = array_sum(array_column($results, 'item_count'));

        $countryOpts = '';
        foreach (array_merge(['all' => 'All Countries'], array_combine($countries, $countries)) as $val => $label) {
            $sel = $val === $country ? ' selected' : '';
            $flag = '';
            if ($val !== 'all') {
                foreach ($sources as $s) {
                    if ($s['country'] === $val) { $flag = $s['flag'] . ' '; break; }
                }
            }
            $countryOpts .= "<option value='{$val}'{$sel}>{$flag}{$label}</option>";
        }

        $cards = '';
        foreach ($results as $r) {
            $s      = $r['source'];
            $color  = $s['color'];
            $items  = $r['items'];
            $count  = $r['item_count'];

            $itemsHtml = '';
            foreach ($items as $item) {
                $badge = $item['badge'];
                $link  = htmlspecialchars($item['link']);
                $title = htmlspecialchars($item['title']);
                $sum   = htmlspecialchars($item['summary']);
                $date  = htmlspecialchars($item['date']);
                $itemsHtml .= <<<HTML
<a href="{$link}" target="_blank" rel="noopener" class="news-item">
  <div class="news-top">
    <span class="badge" style="background:{$badge['bg']};color:{$badge['color']}">{$badge['label']}</span>
    <span class="news-date">{$date}</span>
  </div>
  <div class="news-title">{$title}</div>
  <div class="news-summary">{$sum}</div>
</a>
HTML;
            }

            $cards .= <<<HTML
<div class="card">
  <div class="card-header" style="border-left:4px solid {$color}">
    <div class="card-title-row">
      <span class="flag">{$s['flag']}</span>
      <div>
        <div class="card-name">{$s['name']}</div>
        <div class="card-desc">{$s['description']} · {$s['country']}</div>
      </div>
    </div>
    <div class="card-meta">
      <span class="count-badge">{$count} updates</span>
      <a href="{$s['url']}" target="_blank" rel="noopener" class="src-link">Source ↗</a>
    </div>
  </div>
  <div class="card-body">{$itemsHtml}</div>
</div>
HTML;
        }

        $activeLabel = $country === 'all' ? 'All Countries' : $country;

        return response(<<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>RegTracker — Live Regulatory Updates</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f0f4f8;color:#1e293b;min-height:100vh}

  /* NAV */
  .nav{background:#0f172a;color:#fff;padding:0 32px;height:60px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;box-shadow:0 2px 12px rgba(0,0,0,.25)}
  .nav-brand{font-size:18px;font-weight:700;letter-spacing:-0.3px;display:flex;align-items:center;gap:8px}
  .nav-brand span{background:linear-gradient(135deg,#3b82f6,#8b5cf6);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
  .nav-right{display:flex;align-items:center;gap:16px;font-size:13px;color:#94a3b8}
  .live-dot{width:8px;height:8px;border-radius:50%;background:#22c55e;display:inline-block;animation:pulse 2s infinite}
  @keyframes pulse{0%,100%{opacity:1}50%{opacity:.4}}

  /* HERO */
  .hero{background:linear-gradient(135deg,#0f172a 0%,#1e3a5f 100%);color:#fff;padding:40px 32px 32px;text-align:center}
  .hero h1{font-size:28px;font-weight:800;margin-bottom:8px}
  .hero p{color:#94a3b8;font-size:15px;margin-bottom:24px}
  .stats-row{display:flex;justify-content:center;gap:32px;flex-wrap:wrap}
  .stat{text-align:center}
  .stat-n{font-size:28px;font-weight:800;color:#3b82f6}
  .stat-l{font-size:12px;color:#64748b;margin-top:2px}

  /* FILTER BAR */
  .filter-bar{background:#fff;border-bottom:1px solid #e2e8f0;padding:14px 32px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;position:sticky;top:60px;z-index:90;box-shadow:0 1px 4px rgba(0,0,0,.06)}
  .filter-label{font-size:13px;font-weight:600;color:#475569}
  .filter-select{padding:8px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;background:#f8fafc;cursor:pointer;outline:none;transition:.2s}
  .filter-select:hover,.filter-select:focus{border-color:#3b82f6;background:#fff}
  .result-info{margin-left:auto;font-size:13px;color:#64748b}
  .refresh-btn{padding:8px 16px;background:#0f172a;color:#fff;border:none;border-radius:8px;font-size:13px;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:6px}
  .refresh-btn:hover{background:#1e293b}

  /* GRID */
  .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(380px,1fr));gap:20px;padding:24px 32px;max-width:1600px;margin:0 auto}

  /* CARD */
  .card{background:#fff;border-radius:14px;border:1px solid #e2e8f0;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.06);transition:.2s}
  .card:hover{box-shadow:0 8px 24px rgba(0,0,0,.10);transform:translateY(-2px)}
  .card-header{padding:16px 18px;background:#fafbfc;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;gap:12px}
  .card-title-row{display:flex;align-items:center;gap:10px}
  .flag{font-size:26px;line-height:1}
  .card-name{font-size:15px;font-weight:700;color:#0f172a}
  .card-desc{font-size:11px;color:#64748b;margin-top:2px}
  .card-meta{text-align:right;flex-shrink:0}
  .count-badge{display:block;background:#eff6ff;color:#2563eb;font-size:11px;font-weight:600;padding:3px 8px;border-radius:20px;margin-bottom:4px}
  .src-link{font-size:11px;color:#94a3b8;text-decoration:none}
  .src-link:hover{color:#3b82f6}

  /* NEWS ITEM */
  .card-body{padding:4px 0}
  .news-item{display:block;padding:12px 18px;border-bottom:1px solid #f8fafc;text-decoration:none;color:inherit;transition:.15s}
  .news-item:last-child{border-bottom:none}
  .news-item:hover{background:#f8fafc}
  .news-top{display:flex;align-items:center;gap:8px;margin-bottom:5px}
  .badge{font-size:10px;font-weight:600;padding:2px 7px;border-radius:20px}
  .news-date{font-size:11px;color:#94a3b8;margin-left:auto}
  .news-title{font-size:13px;font-weight:600;color:#0f172a;line-height:1.4;margin-bottom:3px}
  .news-summary{font-size:12px;color:#64748b;line-height:1.5}

  /* FOOTER */
  .footer{text-align:center;padding:24px;color:#94a3b8;font-size:12px;border-top:1px solid #e2e8f0;background:#fff;margin-top:16px}

  @media(max-width:600px){
    .grid{grid-template-columns:1fr;padding:16px}
    .hero{padding:28px 16px 20px}
    .filter-bar{padding:12px 16px}
    .stats-row{gap:16px}
  }
</style>
</head>
<body>

<nav class="nav">
  <div class="nav-brand">
    📡 <span>RegTracker</span>
  </div>
  <div class="nav-right">
    <span class="live-dot"></span> Live updates · Cached 1hr
  </div>
</nav>

<div class="hero">
  <h1>Live Regulatory Update Tracker</h1>
  <p>Real-time compliance news from FCA, FinCEN, OFAC, FATF, FINTRAC, AUSTRAC, EBA &amp; more</p>
  <div class="stats-row">
    <div class="stat"><div class="stat-n">{$total}</div><div class="stat-l">Updates Found</div></div>
    <div class="stat"><div class="stat-n">{$count}</div><div class="stat-l">Sources Checked</div></div>
    <div class="stat"><div class="stat-n">12</div><div class="stat-l">Countries Covered</div></div>
    <div class="stat"><div class="stat-n">1hr</div><div class="stat-l">Refresh Cycle</div></div>
  </div>
</div>

<div class="filter-bar">
  <span class="filter-label">Filter by Country:</span>
  <select class="filter-select" onchange="location.href='/?country='+this.value">
    {$countryOpts}
  </select>
  <span class="result-info">Showing <strong>{$count}</strong> sources for <strong>{$activeLabel}</strong></span>
  <a href="/?country={$country}&refresh=1" class="refresh-btn" onclick="clearCache(event)">🔄 Refresh</a>
</div>

<div class="grid">{$cards}</div>

<div class="footer">
  RegTracker &copy; {$this->year()} · Data sourced directly from official regulatory bodies · For informational purposes only
</div>

<script>
function clearCache(e){
  e.preventDefault();
  fetch('/api/clear-cache').then(()=>location.reload());
}
</script>
</body>
</html>
HTML);
    }

    public function clearCache()
    {
        \Illuminate\Support\Facades\Cache::flush();
        return response()->json(['ok' => true]);
    }

    private function year(): string
    {
        return date('Y');
    }
}
