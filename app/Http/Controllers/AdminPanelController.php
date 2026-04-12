<?php

namespace App\Http\Controllers;

use App\Models\DetectedChange;
use App\Models\MtoProfile;
use App\Models\MtoUser;
use App\Models\RegulatorySource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminPanelController extends Controller
{
    private function layout(string $title, string $activeTab, string $body): string
    {
        $tabs = [
            'dashboard' => ['icon' => '◉', 'label' => 'Dashboard', 'url' => '/panel'],
            'changes'   => ['icon' => '⚡', 'label' => 'QA Queue',  'url' => '/panel/changes'],
            'sources'   => ['icon' => '📡', 'label' => 'Sources',   'url' => '/panel/sources'],
            'mtos'      => ['icon' => '🏢', 'label' => 'MTOs',      'url' => '/panel/mtos'],
        ];
        $nav = '';
        foreach ($tabs as $key => $tab) {
            $active = $activeTab === $key ? 'nav-active' : '';
            $nav .= "<a href='{$tab['url']}' class='nav-item {$active}'>{$tab['icon']} {$tab['label']}</a>";
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{$title} — RegTracker Admin</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f1f5f9;color:#1e293b;display:flex;min-height:100vh}
.sidebar{width:220px;background:#0f172a;display:flex;flex-direction:column;flex-shrink:0;position:fixed;top:0;left:0;height:100vh}
.sidebar-logo{padding:22px 20px 18px;border-bottom:1px solid #1e293b}
.sidebar-logo span{color:#fff;font-size:18px;font-weight:800;letter-spacing:-0.5px}
.sidebar-logo small{color:#22c55e;font-size:11px;display:block;margin-top:2px;font-weight:600}
.nav-item{display:flex;align-items:center;gap:10px;padding:11px 20px;color:#94a3b8;text-decoration:none;font-size:14px;font-weight:500;transition:all 0.15s;border-left:3px solid transparent}
.nav-item:hover{color:#e2e8f0;background:#1e293b}
.nav-active{color:#fff;background:#1e293b;border-left-color:#3b82f6}
.nav-divider{flex:1}
.sidebar-footer{padding:16px 20px;border-top:1px solid #1e293b}
.sidebar-footer a{color:#64748b;font-size:12px;text-decoration:none}
.sidebar-footer a:hover{color:#94a3b8}
.main{margin-left:220px;flex:1;display:flex;flex-direction:column}
.topbar{background:#fff;border-bottom:1px solid #e2e8f0;padding:0 32px;height:56px;display:flex;align-items:center;justify-content:space-between}
.topbar-title{font-size:16px;font-weight:700;color:#0f172a}
.topbar-right{display:flex;align-items:center;gap:12px}
.live-dot{width:8px;height:8px;background:#22c55e;border-radius:50%;animation:pulse 2s infinite}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:0.3}}
.live-text{font-size:12px;color:#22c55e;font-weight:600}
.content{padding:28px 32px;flex:1}
.page-header{margin-bottom:24px}
.page-header h1{font-size:22px;font-weight:800;color:#0f172a}
.page-header p{color:#64748b;font-size:14px;margin-top:4px}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:28px}
.stat-card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:18px 20px}
.stat-num{font-size:28px;font-weight:800;line-height:1;margin-bottom:4px}
.stat-label{font-size:12px;color:#64748b;font-weight:500}
.card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;margin-bottom:20px}
.card-header{padding:16px 20px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between}
.card-title{font-size:14px;font-weight:700;color:#0f172a}
table{width:100%;border-collapse:collapse}
th{padding:10px 16px;text-align:left;font-size:12px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;background:#f8fafc;border-bottom:1px solid #e2e8f0}
td{padding:12px 16px;font-size:13px;color:#334155;border-bottom:1px solid #f8fafc;vertical-align:middle}
tr:last-child td{border-bottom:0}
tr:hover td{background:#fafafa}
.badge{display:inline-block;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:600}
.badge-red{background:#fee2e2;color:#dc2626}
.badge-orange{background:#ffedd5;color:#ea580c}
.badge-yellow{background:#fef9c3;color:#ca8a04}
.badge-green{background:#dcfce7;color:#16a34a}
.badge-blue{background:#dbeafe;color:#2563eb}
.badge-gray{background:#f1f5f9;color:#64748b}
.badge-purple{background:#f3e8ff;color:#7c3aed}
.btn{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:8px;font-size:13px;font-weight:600;border:none;cursor:pointer;text-decoration:none;transition:all 0.15s}
.btn-primary{background:#3b82f6;color:#fff}.btn-primary:hover{background:#2563eb}
.btn-success{background:#22c55e;color:#fff}.btn-success:hover{background:#16a34a}
.btn-danger{background:#ef4444;color:#fff}.btn-danger:hover{background:#dc2626}
.btn-ghost{background:#f1f5f9;color:#475569;border:1px solid #e2e8f0}.btn-ghost:hover{background:#e2e8f0}
.btn-sm{padding:5px 10px;font-size:12px}
.empty-state{padding:40px;text-align:center;color:#94a3b8}
.empty-state .icon{font-size:36px;margin-bottom:12px}
.empty-state p{font-size:14px}
.form-group{margin-bottom:16px}
.form-label{display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px}
.form-input{width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;color:#1e293b;outline:none;transition:border 0.15s}
.form-input:focus{border-color:#3b82f6}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.flash{padding:12px 16px;border-radius:8px;margin-bottom:20px;font-size:13px;font-weight:500}
.flash-success{background:#dcfce7;color:#16a34a;border:1px solid #bbf7d0}
.flash-error{background:#fee2e2;color:#dc2626;border:1px solid #fecaca}
.source-status{display:flex;align-items:center;gap:6px}
.dot{width:8px;height:8px;border-radius:50%;flex-shrink:0}
</style>
</head>
<body>
<div class="sidebar">
  <div class="sidebar-logo">
    <span>RegTracker</span>
    <small>● Admin Panel</small>
  </div>
  {$nav}
  <div class="nav-divider"></div>
  <div class="sidebar-footer">
    <a href="/" target="_blank">← Back to site</a><br><br>
    <a href="/panel/logout">Logout</a>
  </div>
</div>
<div class="main">
  <div class="topbar">
    <span class="topbar-title">{$title}</span>
    <div class="topbar-right">
      <div class="live-dot"></div>
      <span class="live-text">System Online</span>
    </div>
  </div>
  <div class="content">
    {$body}
  </div>
</div>
</body>
</html>
HTML;
    }

    public function login(Request $request)
    {
        $error = '';
        if ($request->isMethod('post')) {
            $secret = config('regtracker.admin_secret');
            if ($request->input('secret') === $secret) {
                session(['panel_auth' => true]);
                return redirect('/panel');
            }
            $error = '<div class="flash flash-error">Incorrect admin secret. Try again.</div>';
        }

        return response(<<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Login — RegTracker</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#0f172a;display:flex;align-items:center;justify-content:center;min-height:100vh}
.box{background:#fff;border-radius:16px;padding:40px;width:100%;max-width:400px;box-shadow:0 20px 60px rgba(0,0,0,0.4)}
.logo{font-size:22px;font-weight:800;color:#0f172a;margin-bottom:6px}
.subtitle{font-size:14px;color:#64748b;margin-bottom:28px}
label{display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px}
input{width:100%;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;outline:none;margin-bottom:16px}
input:focus{border-color:#3b82f6}
button{width:100%;padding:11px;background:#3b82f6;color:#fff;font-size:14px;font-weight:600;border:none;border-radius:8px;cursor:pointer}
button:hover{background:#2563eb}
.flash{padding:10px 14px;border-radius:8px;margin-bottom:16px;font-size:13px;font-weight:500;background:#fee2e2;color:#dc2626;border:1px solid #fecaca}
</style>
</head>
<body>
<div class="box">
  <div class="logo">🔒 RegTracker</div>
  <div class="subtitle">Admin Panel — Enter your admin secret to continue</div>
  {$error}
  <form method="POST" action="/panel/login">
    <input type="hidden" name="_token" value="{$request->session()->token()}">
    <label>Admin Secret</label>
    <input type="password" name="secret" placeholder="Enter admin secret" autofocus required>
    <button type="submit">Sign In →</button>
  </form>
</div>
</body>
</html>
HTML);
    }

    public function logout()
    {
        session()->forget('panel_auth');
        return redirect('/panel/login');
    }

    public function dashboard()
    {
        $totalSources  = RegulatorySource::count();
        $totalChanges  = DetectedChange::count();
        $pendingQA     = DetectedChange::where('qa_status', 'pending')->count();
        $approved      = DetectedChange::where('qa_status', 'admin_approved')->count();
        $totalMtos     = MtoProfile::where('is_active', true)->count();

        $recentChanges = DetectedChange::with('source')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $severityBadge = fn($s) => match($s) {
            'critical' => 'badge-red',
            'high'     => 'badge-orange',
            'medium'   => 'badge-yellow',
            'low'      => 'badge-blue',
            default    => 'badge-gray',
        };

        $qaBadge = fn($s) => match($s) {
            'pending'        => 'badge-yellow',
            'admin_approved' => 'badge-green',
            'auto_approved'  => 'badge-green',
            'dismissed'      => 'badge-gray',
            default          => 'badge-gray',
        };

        $rows = '';
        if ($recentChanges->isEmpty()) {
            $rows = '<tr><td colspan="5"><div class="empty-state"><div class="icon">📭</div><p>No changes detected yet. Scrapers will populate this once they run.</p></div></td></tr>';
        } else {
            foreach ($recentChanges as $c) {
                $sev = $c->severity ?? 'low';
                $qs  = $c->qa_status ?? 'pending';
                $src = $c->source?->name ?? '—';
                $title = htmlspecialchars($c->title ?? '(untitled)');
                $date = $c->detected_at ? date('M d, H:i', strtotime($c->detected_at)) : '—';
                $rows .= "<tr>
                  <td><strong>{$title}</strong></td>
                  <td>{$src}</td>
                  <td><span class='badge {$severityBadge($sev)}'>" . strtoupper($sev) . "</span></td>
                  <td><span class='badge {$qaBadge($qs)}'>" . str_replace('_', ' ', $qs) . "</span></td>
                  <td>{$date}</td>
                </tr>";
            }
        }

        $pendingAlert = $pendingQA > 0
            ? "<div class='flash flash-error'>⚡ {$pendingQA} change(s) in the QA queue need your review. <a href='/panel/changes' style='color:inherit;font-weight:700;margin-left:8px'>Review now →</a></div>"
            : '';

        $body = <<<HTML
<div class="page-header">
  <h1>Dashboard</h1>
  <p>Overview of regulatory monitoring activity</p>
</div>
{$pendingAlert}
<div class="stats">
  <div class="stat-card"><div class="stat-num" style="color:#3b82f6">{$totalSources}</div><div class="stat-label">Sources Monitored</div></div>
  <div class="stat-card"><div class="stat-num" style="color:#8b5cf6">{$totalChanges}</div><div class="stat-label">Total Changes</div></div>
  <div class="stat-card"><div class="stat-num" style="color:#f59e0b">{$pendingQA}</div><div class="stat-label">Pending QA</div></div>
  <div class="stat-card"><div class="stat-num" style="color:#22c55e">{$approved}</div><div class="stat-label">Approved & Sent</div></div>
  <div class="stat-card"><div class="stat-num" style="color:#0f172a">{$totalMtos}</div><div class="stat-label">Active MTOs</div></div>
</div>
<div class="card">
  <div class="card-header">
    <span class="card-title">Recent Regulatory Changes</span>
    <a href="/panel/changes" class="btn btn-ghost btn-sm">View all →</a>
  </div>
  <table>
    <thead><tr><th>Title</th><th>Source</th><th>Severity</th><th>QA Status</th><th>Detected</th></tr></thead>
    <tbody>{$rows}</tbody>
  </table>
</div>
HTML;
        return response($this->layout('Dashboard', 'dashboard', $body));
    }

    public function changes(Request $request)
    {
        $filter = $request->get('qa_status', '');
        $query  = DetectedChange::with('source')->orderByDesc('detected_at');
        if ($filter) $query->where('qa_status', $filter);
        $changes = $query->paginate(25);

        $severityBadge = fn($s) => match($s) {
            'critical' => 'badge-red', 'high' => 'badge-orange',
            'medium' => 'badge-yellow', 'low' => 'badge-blue', default => 'badge-gray',
        };
        $qaBadge = fn($s) => match($s) {
            'pending' => 'badge-yellow', 'admin_approved' => 'badge-green',
            'auto_approved' => 'badge-green', 'dismissed' => 'badge-gray', default => 'badge-gray',
        };

        $flash = '';
        if (session('success')) {
            $flash = "<div class='flash flash-success'>" . session('success') . "</div>";
        }

        $filterActive = fn($val) => $filter === $val ? 'btn-primary' : 'btn-ghost';

        $rows = '';
        if ($changes->isEmpty()) {
            $rows = '<tr><td colspan="6"><div class="empty-state"><div class="icon">📭</div><p>No changes found. Once scrapers detect regulatory updates, they\'ll appear here for QA review.</p></div></td></tr>';
        } else {
            foreach ($changes as $c) {
                $sev   = $c->severity ?? 'low';
                $qs    = $c->qa_status ?? 'pending';
                $src   = htmlspecialchars($c->source?->name ?? '—');
                $title = htmlspecialchars($c->title ?? '(untitled)');
                $summary = htmlspecialchars(mb_strimwidth($c->plain_english_summary ?? '', 0, 100, '…'));
                $date  = $c->detected_at ? date('M d Y, H:i', strtotime($c->detected_at)) : '—';
                $actions = '';
                if ($qs === 'pending') {
                    $actions = "
                      <form method='POST' action='/panel/changes/{$c->id}/approve' style='display:inline'>
                        <input type='hidden' name='_token' value='" . csrf_token() . "'>
                        <button class='btn btn-success btn-sm'>✓ Approve</button>
                      </form>
                      <form method='POST' action='/panel/changes/{$c->id}/dismiss' style='display:inline;margin-left:6px'>
                        <input type='hidden' name='_token' value='" . csrf_token() . "'>
                        <button class='btn btn-ghost btn-sm'>✕ Dismiss</button>
                      </form>";
                }
                $rows .= "<tr>
                  <td><strong style='display:block'>{$title}</strong><small style='color:#94a3b8'>{$summary}</small></td>
                  <td>{$src}</td>
                  <td><span class='badge {$severityBadge($sev)}'>" . strtoupper($sev) . "</span></td>
                  <td><span class='badge {$qaBadge($qs)}'>" . str_replace('_', ' ', $qs) . "</span></td>
                  <td style='white-space:nowrap'>{$date}</td>
                  <td style='white-space:nowrap'>{$actions}</td>
                </tr>";
            }
        }

        $body = <<<HTML
<div class="page-header">
  <h1>QA Queue</h1>
  <p>Review detected regulatory changes before they are sent as alerts to MTO owners</p>
</div>
{$flash}
<div style="display:flex;gap:8px;margin-bottom:20px">
  <a href="/panel/changes" class="btn btn-sm {$filterActive('')}">All</a>
  <a href="/panel/changes?qa_status=pending" class="btn btn-sm {$filterActive('pending')}">⏳ Pending</a>
  <a href="/panel/changes?qa_status=admin_approved" class="btn btn-sm {$filterActive('admin_approved')}">✅ Approved</a>
  <a href="/panel/changes?qa_status=dismissed" class="btn btn-sm {$filterActive('dismissed')}">🗑 Dismissed</a>
</div>
<div class="card">
  <div class="card-header">
    <span class="card-title">Regulatory Changes ({$changes->total()} total)</span>
  </div>
  <table>
    <thead><tr><th>Change</th><th>Source</th><th>Severity</th><th>Status</th><th>Detected</th><th>Actions</th></tr></thead>
    <tbody>{$rows}</tbody>
  </table>
</div>
HTML;
        return response($this->layout('QA Queue', 'changes', $body));
    }

    public function approveChange(Request $request, $id)
    {
        $change = DetectedChange::findOrFail($id);
        $change->update(['qa_status' => 'admin_approved', 'approved_by' => 0, 'approved_at' => now()]);
        return redirect('/panel/changes')->with('success', "✅ Change #{$id} approved. Alerts will be dispatched to matched MTOs.");
    }

    public function dismissChange(Request $request, $id)
    {
        $change = DetectedChange::findOrFail($id);
        $change->update(['qa_status' => 'dismissed']);
        return redirect('/panel/changes')->with('success', "Change #{$id} dismissed.");
    }

    public function sources()
    {
        $sources = RegulatorySource::orderBy('type')->orderBy('name')->get();

        $typeLabel = fn($t) => match($t) {
            'sanctions_list' => ['badge-red', 'Sanctions'],
            'guidance'       => ['badge-blue', 'Guidance'],
            'fatf'           => ['badge-green', 'FATF'],
            default          => ['badge-gray', ucfirst($t)],
        };
        $statusDot = fn($s) => match($s) {
            'ok'      => '#22c55e',
            'error', 'failed' => '#ef4444',
            'warning' => '#f59e0b',
            default   => '#94a3b8',
        };

        $rows = '';
        foreach ($sources as $s) {
            [$tClass, $tLabel] = $typeLabel($s->type);
            $dot = $statusDot($s->last_status);
            $url = htmlspecialchars($s->source_url);
            $lastChecked = $s->last_checked_at ? date('M d, H:i', strtotime($s->last_checked_at)) : 'Never';
            $freq = $s->check_frequency_hours === 0 ? 'Manual' : "Every {$s->check_frequency_hours}h";
            $statusText = ucfirst($s->last_status ?? 'pending');
            $rows .= "<tr>
              <td><strong>{$s->name}</strong></td>
              <td><span class='badge {$tClass}'>{$tLabel}</span></td>
              <td><span class='badge badge-gray'>{$s->jurisdiction}</span></td>
              <td><div class='source-status'><div class='dot' style='background:{$dot}'></div>{$statusText}</div></td>
              <td>{$freq}</td>
              <td>{$lastChecked}</td>
              <td style='max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap'><a href='{$url}' target='_blank' style='color:#3b82f6;font-size:12px'>{$url}</a></td>
            </tr>";
        }

        $body = <<<HTML
<div class="page-header">
  <h1>Regulatory Sources</h1>
  <p>All monitored sources and their current health status</p>
</div>
<div class="card">
  <table>
    <thead><tr><th>Name</th><th>Type</th><th>Jurisdiction</th><th>Status</th><th>Frequency</th><th>Last Checked</th><th>Source URL</th></tr></thead>
    <tbody>{$rows}</tbody>
  </table>
</div>
HTML;
        return response($this->layout('Sources', 'sources', $body));
    }

    public function mtos()
    {
        $mtos = MtoProfile::withCount('mtoUsers')->orderByDesc('created_at')->get();

        $flash = '';
        if (session('success')) $flash = "<div class='flash flash-success'>" . session('success') . "</div>";

        $rows = '';
        if ($mtos->isEmpty()) {
            $rows = '<tr><td colspan="6"><div class="empty-state"><div class="icon">🏢</div><p>No MTOs registered yet. Add your first MTO to start sending compliance alerts.</p></div></td></tr>';
        } else {
            foreach ($mtos as $m) {
                $jurisdictions = is_array($m->license_jurisdictions) ? implode(', ', $m->license_jurisdictions) : '—';
                $corridors     = is_array($m->active_corridors)     ? count($m->active_corridors) . ' corridors'  : '—';
                $status  = $m->is_active ? "<span class='badge badge-green'>Active</span>" : "<span class='badge badge-gray'>Inactive</span>";
                $created = date('M d, Y', strtotime($m->created_at));
                $rows   .= "<tr>
                  <td><strong>{$m->mto_name}</strong><br><small style='color:#94a3b8'>{$m->notification_email}</small></td>
                  <td>{$m->primary_contact_name}<br><small style='color:#94a3b8'>{$m->primary_contact_email}</small></td>
                  <td><small>{$jurisdictions}</small></td>
                  <td>{$corridors}</td>
                  <td>{$status}</td>
                  <td>{$created}</td>
                </tr>";
            }
        }

        $body = <<<HTML
<div class="page-header" style="display:flex;align-items:center;justify-content:space-between">
  <div><h1>MTO Profiles</h1><p>Money Transfer Operators registered to receive compliance alerts</p></div>
  <a href="/panel/mtos/create" class="btn btn-primary">+ Add MTO</a>
</div>
{$flash}
<div class="card">
  <table>
    <thead><tr><th>MTO Name</th><th>Contact</th><th>Jurisdictions</th><th>Corridors</th><th>Status</th><th>Registered</th></tr></thead>
    <tbody>{$rows}</tbody>
  </table>
</div>
HTML;
        return response($this->layout('MTOs', 'mtos', $body));
    }

    public function createMto()
    {
        $token = csrf_token();
        $body  = <<<HTML
<div class="page-header">
  <h1>Register New MTO</h1>
  <p>Add a new Money Transfer Operator to receive regulatory compliance alerts</p>
</div>
<div class="card" style="max-width:700px">
  <div class="card-header"><span class="card-title">MTO Details</span></div>
  <div style="padding:24px">
    <form method="POST" action="/panel/mtos">
      <input type="hidden" name="_token" value="{$token}">
      <div class="form-row">
        <div class="form-group"><label class="form-label">MTO Company Name *</label><input class="form-input" name="mto_name" placeholder="e.g. FamRemit Ltd" required></div>
        <div class="form-group"><label class="form-label">Notification Email *</label><input class="form-input" type="email" name="notification_email" placeholder="alerts@company.com" required></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Primary Contact Name *</label><input class="form-input" name="primary_contact_name" placeholder="John Smith" required></div>
        <div class="form-group"><label class="form-label">Primary Contact Email *</label><input class="form-input" type="email" name="primary_contact_email" placeholder="john@company.com" required></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">License Jurisdictions * <small style="font-weight:400;color:#94a3b8">(comma separated, e.g. US,UK,CA)</small></label><input class="form-input" name="license_jurisdictions" placeholder="US,UK,CA" required></div>
        <div class="form-group"><label class="form-label">Active Corridors * <small style="font-weight:400;color:#94a3b8">(e.g. US-IN,UK-PK)</small></label><input class="form-input" name="active_corridors" placeholder="US-IN,UK-PK,CA-BD" required></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">License Types * <small style="font-weight:400;color:#94a3b8">(e.g. MSB,MTL,FCA)</small></label><input class="form-input" name="license_types" placeholder="MSB,MTL" required></div>
        <div class="form-group"><label class="form-label">Notification Preference</label>
          <select class="form-input" name="notification_preference">
            <option value="instant">Instant (critical &amp; high only)</option>
            <option value="daily" selected>Daily digest</option>
            <option value="weekly">Weekly digest</option>
          </select>
        </div>
      </div>
      <hr style="margin:20px 0;border:none;border-top:1px solid #e2e8f0">
      <div class="form-group"><label class="form-label">Portal Login Email *</label><input class="form-input" type="email" name="user_email" placeholder="Login email for MTO dashboard" required></div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Portal Login Name *</label><input class="form-input" name="user_name" placeholder="Full name" required></div>
        <div class="form-group"><label class="form-label">Portal Password *</label><input class="form-input" type="password" name="user_password" placeholder="Min 8 characters" required minlength="8"></div>
      </div>
      <div style="display:flex;gap:12px;margin-top:8px">
        <button class="btn btn-primary" type="submit">Register MTO →</button>
        <a href="/panel/mtos" class="btn btn-ghost">Cancel</a>
      </div>
    </form>
  </div>
</div>
HTML;
        return response($this->layout('Add MTO', 'mtos', $body));
    }

    public function storeMto(Request $request)
    {
        $data = $request->validate([
            'mto_name'              => 'required|string',
            'notification_email'    => 'required|email',
            'primary_contact_name'  => 'required|string',
            'primary_contact_email' => 'required|email',
            'license_jurisdictions' => 'required|string',
            'active_corridors'      => 'required|string',
            'license_types'         => 'required|string',
            'notification_preference' => 'required|in:instant,daily,weekly',
            'user_email'            => 'required|email|unique:mto_users,email',
            'user_name'             => 'required|string',
            'user_password'         => 'required|string|min:8',
        ]);

        $mto = MtoProfile::create([
            'mto_name'              => $data['mto_name'],
            'notification_email'    => $data['notification_email'],
            'primary_contact_name'  => $data['primary_contact_name'],
            'primary_contact_email' => $data['primary_contact_email'],
            'license_jurisdictions' => array_map('trim', explode(',', $data['license_jurisdictions'])),
            'active_corridors'      => array_map('trim', explode(',', $data['active_corridors'])),
            'license_types'         => array_map('trim', explode(',', $data['license_types'])),
            'notification_preference' => $data['notification_preference'],
            'created_by_admin'      => true,
            'is_active'             => true,
        ]);

        MtoUser::create([
            'mto_profile_id' => $mto->id,
            'name'           => $data['user_name'],
            'email'          => $data['user_email'],
            'password'       => Hash::make($data['user_password']),
            'is_active'      => true,
        ]);

        return redirect('/panel/mtos')->with('success', "✅ MTO '{$data['mto_name']}' registered successfully with portal access.");
    }
}
