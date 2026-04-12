<?php

use App\Models\RegulatorySource;
use App\Models\DetectedChange;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $sources = RegulatorySource::orderBy('jurisdiction')->orderBy('name')->get();
    $totalChanges = DetectedChange::count();
    $pendingQA    = DetectedChange::where('qa_status', 'pending')->count();
    $approved     = DetectedChange::where('qa_status', 'admin_approved')->count();

    $typeColors = [
        'sanctions_list' => ['bg' => '#fff0f0', 'border' => '#fca5a5', 'badge' => '#dc2626', 'label' => 'Sanctions'],
        'guidance'       => ['bg' => '#eff6ff', 'border' => '#93c5fd', 'badge' => '#2563eb', 'label' => 'Guidance'],
        'fatf'           => ['bg' => '#f0fdf4', 'border' => '#86efac', 'badge' => '#16a34a', 'label' => 'FATF'],
    ];

    $statusColors = [
        'pending' => ['dot' => '#f59e0b', 'text' => 'Pending'],
        'ok'      => ['dot' => '#22c55e', 'text' => 'OK'],
        'error'   => ['dot' => '#ef4444', 'text' => 'Error'],
        'failed'  => ['dot' => '#ef4444', 'text' => 'Failed'],
        'warning' => ['dot' => '#f59e0b', 'text' => 'Warning'],
    ];

    ob_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>RegTracker — Live Regulatory Monitor</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f8fafc; color: #1e293b; }
  .topbar { background: #0f172a; padding: 0 32px; display: flex; align-items: center; justify-content: space-between; height: 60px; }
  .logo { color: #fff; font-size: 20px; font-weight: 700; letter-spacing: -0.5px; display: flex; align-items: center; gap: 10px; }
  .logo-dot { width: 10px; height: 10px; background: #22c55e; border-radius: 50%; animation: pulse 2s infinite; }
  @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:0.4} }
  .live-badge { background: #22c55e22; color: #22c55e; font-size: 12px; font-weight: 600; padding: 3px 10px; border-radius: 20px; border: 1px solid #22c55e55; }
  .main { max-width: 1100px; margin: 0 auto; padding: 36px 24px; }
  .hero { margin-bottom: 32px; }
  .hero h1 { font-size: 28px; font-weight: 800; color: #0f172a; margin-bottom: 8px; }
  .hero p { color: #64748b; font-size: 15px; max-width: 600px; line-height: 1.6; }
  .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 36px; }
  .stat { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px 24px; }
  .stat-num { font-size: 32px; font-weight: 800; color: #0f172a; line-height: 1; margin-bottom: 4px; }
  .stat-label { font-size: 13px; color: #64748b; font-weight: 500; }
  .section-title { font-size: 16px; font-weight: 700; color: #0f172a; margin-bottom: 16px; padding-bottom: 10px; border-bottom: 2px solid #e2e8f0; }
  .sources-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 14px; margin-bottom: 40px; }
  .source-card { background: #fff; border-radius: 12px; padding: 18px 20px; border: 1.5px solid #e2e8f0; transition: box-shadow 0.15s; }
  .source-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.08); }
  .source-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 10px; }
  .source-name { font-weight: 700; font-size: 14px; color: #0f172a; line-height: 1.3; }
  .source-juris { font-size: 11px; color: #64748b; margin-top: 2px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; }
  .badge { font-size: 11px; font-weight: 600; padding: 3px 8px; border-radius: 6px; border: 1px solid; white-space: nowrap; flex-shrink: 0; }
  .status-row { display: flex; align-items: center; gap: 6px; margin-top: 8px; }
  .status-dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
  .status-text { font-size: 12px; color: #64748b; }
  .source-url { font-size: 11px; color: #94a3b8; margin-top: 6px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .api-section { background: #0f172a; border-radius: 14px; padding: 28px 32px; color: #e2e8f0; }
  .api-section .section-title { color: #e2e8f0; border-color: #334155; }
  .endpoints { display: flex; flex-direction: column; gap: 8px; }
  .endpoint { display: flex; align-items: center; gap: 12px; padding: 10px 14px; background: #1e293b; border-radius: 8px; font-size: 13px; }
  .method { font-weight: 700; font-size: 11px; padding: 3px 7px; border-radius: 5px; min-width: 42px; text-align: center; }
  .get  { background: #065f46; color: #6ee7b7; }
  .post { background: #1e40af; color: #93c5fd; }
  .put  { background: #92400e; color: #fcd34d; }
  .endpoint-path { color: #7dd3fc; font-family: monospace; flex: 1; }
  .endpoint-desc { color: #94a3b8; font-size: 12px; }
  .auth-note { margin-top: 16px; background: #1e293b; border-radius: 8px; padding: 12px 16px; font-size: 12px; color: #94a3b8; border-left: 3px solid #f59e0b; }
  .auth-note strong { color: #fcd34d; }
  footer { text-align: center; padding: 32px; color: #94a3b8; font-size: 13px; }
</style>
</head>
<body>

<div class="topbar">
  <div class="logo">
    <div class="logo-dot"></div>
    RegTracker
  </div>
  <span class="live-badge">● LIVE</span>
</div>

<div class="main">

  <div class="hero">
    <h1>Live Regulatory Change Tracker</h1>
    <p>Monitors OFAC, UK, UN, EU &amp; DFAT sanctions lists; AUSTRAC, FCA, FINTRAC, FinCEN, and FATF guidance — alerting MTO compliance teams the moment something changes.</p>
  </div>

  <div class="stats">
    <div class="stat">
      <div class="stat-num"><?= $sources->count() ?></div>
      <div class="stat-label">Regulatory Sources</div>
    </div>
    <div class="stat">
      <div class="stat-num"><?= $totalChanges ?></div>
      <div class="stat-label">Changes Detected</div>
    </div>
    <div class="stat">
      <div class="stat-num"><?= $pendingQA ?></div>
      <div class="stat-label">Pending QA Review</div>
    </div>
    <div class="stat">
      <div class="stat-num"><?= $approved ?></div>
      <div class="stat-label">Approved &amp; Sent</div>
    </div>
    <div class="stat">
      <div class="stat-num" style="color:#22c55e">●</div>
      <div class="stat-label">System Online</div>
    </div>
  </div>

  <div class="section-title">Monitored Sources</div>
  <div class="sources-grid">
    <?php foreach ($sources as $s):
      $tc = $typeColors[$s->type] ?? ['bg'=>'#f8fafc','border'=>'#cbd5e1','badge'=>'#475569','label'=>ucfirst($s->type)];
      $sc = $statusColors[$s->last_status] ?? $statusColors['pending'];
    ?>
    <div class="source-card" style="border-color: <?= $tc['border'] ?>; background: <?= $tc['bg'] ?>">
      <div class="source-header">
        <div>
          <div class="source-name"><?= htmlspecialchars($s->name) ?></div>
          <div class="source-juris"><?= htmlspecialchars($s->jurisdiction) ?></div>
        </div>
        <span class="badge" style="color:<?= $tc['badge'] ?>; border-color:<?= $tc['border'] ?>; background:#fff">
          <?= $tc['label'] ?>
        </span>
      </div>
      <div class="status-row">
        <div class="status-dot" style="background:<?= $sc['dot'] ?>"></div>
        <span class="status-text"><?= $sc['text'] ?> · Checks every <?= $s->check_frequency_hours ?>h<?= $s->check_frequency_hours === 0 ? ' (manual)' : '' ?></span>
      </div>
      <div class="source-url" title="<?= htmlspecialchars($s->source_url) ?>"><?= htmlspecialchars($s->source_url) ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="api-section">
    <div class="section-title">API Reference</div>
    <div class="endpoints">
      <div class="endpoint"><span class="method get">GET</span><span class="endpoint-path">/api/health</span><span class="endpoint-desc">Public health check</span></div>
      <div class="endpoint"><span class="method post">POST</span><span class="endpoint-path">/api/auth/login</span><span class="endpoint-desc">MTO user login → Sanctum token</span></div>
      <div class="endpoint"><span class="method get">GET</span><span class="endpoint-path">/api/admin/mto</span><span class="endpoint-desc">List all MTO profiles</span></div>
      <div class="endpoint"><span class="method post">POST</span><span class="endpoint-path">/api/admin/mto</span><span class="endpoint-desc">Register new MTO</span></div>
      <div class="endpoint"><span class="method get">GET</span><span class="endpoint-path">/api/admin/changes</span><span class="endpoint-desc">QA queue — pending regulatory changes</span></div>
      <div class="endpoint"><span class="method post">POST</span><span class="endpoint-path">/api/admin/changes/{id}/approve</span><span class="endpoint-desc">Approve change → dispatch alerts</span></div>
      <div class="endpoint"><span class="method post">POST</span><span class="endpoint-path">/api/admin/changes/{id}/dismiss</span><span class="endpoint-desc">Dismiss false positive</span></div>
      <div class="endpoint"><span class="method get">GET</span><span class="endpoint-path">/api/admin/health</span><span class="endpoint-desc">Scraper health for all sources</span></div>
      <div class="endpoint"><span class="method get">GET</span><span class="endpoint-path">/api/admin/fatf</span><span class="endpoint-desc">FATF grey/black list</span></div>
      <div class="endpoint"><span class="method post">POST</span><span class="endpoint-path">/api/admin/fatf</span><span class="endpoint-desc">Update FATF lists after plenary</span></div>
      <div class="endpoint"><span class="method get">GET</span><span class="endpoint-path">/api/dashboard</span><span class="endpoint-desc">MTO dashboard (Sanctum token)</span></div>
      <div class="endpoint"><span class="method get">GET</span><span class="endpoint-path">/api/alerts</span><span class="endpoint-desc">MTO alerts list (Sanctum token)</span></div>
      <div class="endpoint"><span class="method put">PUT</span><span class="endpoint-path">/api/actions/{id}/status</span><span class="endpoint-desc">Update compliance action status</span></div>
    </div>
    <div class="auth-note">
      <strong>Admin routes</strong> require <code>Authorization: Bearer &lt;ADMIN_SECRET&gt;</code> &nbsp;·&nbsp;
      <strong>MTO routes</strong> require Sanctum token from login
    </div>
  </div>

</div>

<footer>RegTracker · Built for MTO Compliance · <?= date('Y') ?></footer>

</body>
</html>
<?php
    return response(ob_get_clean());
});
