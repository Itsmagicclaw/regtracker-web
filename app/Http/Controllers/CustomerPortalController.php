<?php

namespace App\Http\Controllers;

use App\Models\DetectedChange;
use App\Models\MtoActionProgress;
use App\Models\MtoAlert;
use App\Models\MtoProfile;
use App\Models\MtoUser;
use App\Models\RegulatorySource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class CustomerPortalController extends Controller
{
    // ── Auth ──────────────────────────────────────────────────────────────────

    public function login(Request $request)
    {
        $error = '';
        if ($request->isMethod('post')) {
            $user = MtoUser::where('email', $request->input('email'))
                ->where('is_active', true)
                ->first();
            if ($user && Hash::check($request->input('password'), $user->password)) {
                session([
                    'portal_auth'       => true,
                    'portal_user_id'    => $user->id,
                    'portal_mto_id'     => $user->mto_profile_id,
                    'portal_user_name'  => $user->name,
                    'portal_mto_name'   => $user->mtoProfile->mto_name,
                ]);
                $user->update(['last_login_at' => now()]);
                return redirect('/portal');
            }
            $error = '<div class="flash flash-error">Incorrect email or password. Please try again.</div>';
        }

        $csrfToken = csrf_token();
        return response($this->loginPage($error, $csrfToken));
    }

    public function logout()
    {
        session()->forget(['portal_auth','portal_user_id','portal_mto_id','portal_user_name','portal_mto_name']);
        return redirect('/portal/login');
    }

    // ── Dashboard ─────────────────────────────────────────────────────────────

    public function dashboard(Request $request)
    {
        $mtoId   = session('portal_mto_id');
        $mtoName = session('portal_mto_name');

        $range    = $request->get('range', '30');
        $since    = match($range) {
            '7'  => now()->subDays(7),
            '90' => now()->subDays(90),
            'all'=> null,
            default => now()->subDays(30),
        };

        $alertQuery = MtoAlert::with(['change.source'])
            ->where('mto_id', $mtoId);
        if ($since) $alertQuery->where('alerted_at', '>=', $since);
        $alerts = $alertQuery->orderByDesc('alerted_at')->get();

        $openActions    = MtoActionProgress::whereHas('mtoAlert', fn($q) => $q->where('mto_id', $mtoId))
            ->whereIn('status', ['pending', 'in_progress'])->count();
        $completedCount = MtoActionProgress::whereHas('mtoAlert', fn($q) => $q->where('mto_id', $mtoId))
            ->where('status', 'completed')->count();

        $totalAlerts   = $alerts->count();
        $unviewedCount = $alerts->filter(fn($a) => is_null($a->dashboard_viewed_at))->count();
        $criticalCount = $alerts->filter(fn($a) => $a->change?->severity === 'critical')->count();

        $severityColor = fn($s) => match($s) {
            'critical' => ['#fee2e2','#dc2626','#fecaca'],
            'high'     => ['#fff7ed','#ea580c','#fed7aa'],
            'medium'   => ['#fefce8','#ca8a04','#fef08a'],
            default    => ['#eff6ff','#2563eb','#bfdbfe'],
        };

        $alertRows = '';
        if ($alerts->isEmpty()) {
            $alertRows = '<div class="empty-card"><div class="empty-icon">📭</div><p>No regulatory alerts in this period. You\'re all clear!</p></div>';
        } else {
            foreach ($alerts->take(10) as $a) {
                $sev   = $a->change?->severity ?? 'low';
                $title = htmlspecialchars($a->change?->title ?? '(untitled)');
                $src   = htmlspecialchars($a->change?->source?->name ?? '—');
                $type  = str_replace('_', ' ', $a->change?->change_type ?? '');
                $date  = $a->alerted_at ? date('M d, Y', strtotime($a->alerted_at)) : '—';
                [$bg, $color, $border] = $severityColor($sev);
                $unviewed  = is_null($a->dashboard_viewed_at) ? '<span class="new-badge">NEW</span>' : '';
                $alertRows .= <<<HTML
<a href="/portal/alerts/{$a->id}" class="alert-card" style="border-left:4px solid {$color}">
  <div class="alert-top">
    <span class="sev-badge" style="background:{$bg};color:{$color};border:1px solid {$border}">{$sev}</span>
    {$unviewed}
    <span class="alert-src">{$src}</span>
    <span class="alert-date">{$date}</span>
  </div>
  <div class="alert-title">{$title}</div>
  <div class="alert-type">{$type}</div>
</a>
HTML;
            }
        }

        $rangeOptions = [
            '7'   => 'Last 7 days',
            '30'  => 'Last 30 days',
            '90'  => 'Last 90 days',
            'all' => 'All time',
        ];
        $rangeSelect = '<select onchange="location.href=\'/portal?range=\'+this.value" style="padding:7px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;background:#fff">';
        foreach ($rangeOptions as $val => $label) {
            $sel = $val === $range ? ' selected' : '';
            $rangeSelect .= "<option value='{$val}'{$sel}>{$label}</option>";
        }
        $rangeSelect .= '</select>';

        $body = <<<HTML
<div class="portal-header">
  <div>
    <h1>Compliance Dashboard</h1>
    <p class="sub">Welcome back, {$mtoName}</p>
  </div>
  <div class="header-controls">{$rangeSelect}</div>
</div>

<div class="stats-row">
  <div class="stat-card"><div class="stat-num" style="color:#3b82f6">{$totalAlerts}</div><div class="stat-label">Alerts Received</div></div>
  <div class="stat-card"><div class="stat-num" style="color:#f59e0b">{$unviewedCount}</div><div class="stat-label">Unread Alerts</div></div>
  <div class="stat-card"><div class="stat-num" style="color:#dc2626">{$criticalCount}</div><div class="stat-label">Critical Notices</div></div>
  <div class="stat-card"><div class="stat-num" style="color:#8b5cf6">{$openActions}</div><div class="stat-label">Open Actions</div></div>
  <div class="stat-card"><div class="stat-num" style="color:#22c55e">{$completedCount}</div><div class="stat-label">Completed Actions</div></div>
</div>

<div class="section-header">
  <span>Recent Regulatory Alerts</span>
  <a href="/portal/alerts" class="link-more">View all →</a>
</div>
<div class="alert-list">{$alertRows}</div>
HTML;
        return response($this->layout('Dashboard', 'dashboard', $body));
    }

    // ── Alerts List ───────────────────────────────────────────────────────────

    public function alerts(Request $request)
    {
        $mtoId   = session('portal_mto_id');
        $range   = $request->get('range', '30');
        $source  = $request->get('source', '');
        $sev     = $request->get('severity', '');

        $since = match($range) {
            '7'  => now()->subDays(7),
            '90' => now()->subDays(90),
            'all'=> null,
            default => now()->subDays(30),
        };

        $query = MtoAlert::with(['change.source'])->where('mto_id', $mtoId);
        if ($since) $query->where('alerted_at', '>=', $since);
        if ($source) $query->whereHas('change.source', fn($q) => $q->where('id', $source));
        if ($sev)    $query->whereHas('change', fn($q) => $q->where('severity', $sev));
        $alerts = $query->orderByDesc('alerted_at')->paginate(20);

        $sources = RegulatorySource::orderBy('name')->get();

        $severityColor = fn($s) => match($s) {
            'critical' => ['#dc2626','#fee2e2','#fecaca'],
            'high'     => ['#ea580c','#fff7ed','#fed7aa'],
            'medium'   => ['#ca8a04','#fefce8','#fef08a'],
            default    => ['#2563eb','#eff6ff','#bfdbfe'],
        };

        $sourceOpts = '<option value="">All Sources</option>';
        foreach ($sources as $s) {
            $sel = (string)$s->id === $source ? ' selected' : '';
            $sourceOpts .= "<option value='{$s->id}'{$sel}>" . htmlspecialchars($s->name) . "</option>";
        }

        $rangeOpts = '';
        foreach (['7'=>'Last 7 days','30'=>'Last 30 days','90'=>'Last 90 days','all'=>'All time'] as $v=>$l) {
            $sel = $v === $range ? ' selected' : '';
            $rangeOpts .= "<option value='{$v}'{$sel}>{$l}</option>";
        }

        $sevOpts = '<option value="">All Severities</option>';
        foreach (['critical','high','medium','low'] as $s) {
            $sel = $s === $sev ? ' selected' : '';
            $sevOpts .= "<option value='{$s}'{$sel}>" . ucfirst($s) . "</option>";
        }

        $rows = '';
        if ($alerts->isEmpty()) {
            $rows = '<div class="empty-card"><div class="empty-icon">📭</div><p>No alerts match your filters.</p></div>';
        } else {
            foreach ($alerts as $a) {
                $s      = $a->change?->severity ?? 'low';
                [$color,$bg,$border] = $severityColor($s);
                $title  = htmlspecialchars($a->change?->title ?? '(untitled)');
                $src    = htmlspecialchars($a->change?->source?->name ?? '—');
                $type   = ucwords(str_replace('_', ' ', $a->change?->change_type ?? ''));
                $date   = $a->alerted_at ? date('M d, Y', strtotime($a->alerted_at)) : '—';
                $unread = is_null($a->dashboard_viewed_at) ? '<span class="new-badge">NEW</span>' : '';
                $eff    = $a->change?->effective_date ? ' · Effective: ' . date('M d, Y', strtotime($a->change->effective_date)) : '';
                $rows  .= <<<HTML
<a href="/portal/alerts/{$a->id}" class="alert-card" style="border-left:4px solid {$color}">
  <div class="alert-top">
    <span class="sev-badge" style="background:{$bg};color:{$color};border:1px solid {$border}">{$s}</span>
    {$unread}
    <span class="alert-src">{$src}</span>
    <span class="alert-date">{$date}{$eff}</span>
  </div>
  <div class="alert-title">{$title}</div>
  <div class="alert-type">{$type}</div>
</a>
HTML;
            }
        }

        $filterJs = "function applyFilter(){const r=document.getElementById('r').value,s=document.getElementById('s').value,sv=document.getElementById('sv').value;location.href='/portal/alerts?range='+r+'&source='+s+'&severity='+sv;}";
        $alertsTotal = $alerts->total();

        $body = <<<HTML
<div class="portal-header">
  <div><h1>Regulatory Alerts</h1><p class="sub">All compliance notices sent to your organisation</p></div>
</div>
<script>{$filterJs}</script>
<div class="filter-bar">
  <select id="r" onchange="applyFilter()" style="padding:7px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;background:#fff">{$rangeOpts}</select>
  <select id="s" onchange="applyFilter()" style="padding:7px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;background:#fff">{$sourceOpts}</select>
  <select id="sv" onchange="applyFilter()" style="padding:7px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;background:#fff">{$sevOpts}</select>
  <span style="color:#64748b;font-size:13px">{$alertsTotal} alerts</span>
</div>
<div class="alert-list">{$rows}</div>
HTML;
        return response($this->layout('Alerts', 'alerts', $body));
    }

    // ── Alert Detail ──────────────────────────────────────────────────────────

    public function showAlert(Request $request, $id)
    {
        $mtoId = session('portal_mto_id');
        $alert = MtoAlert::with(['change.source', 'change.actionItems', 'actionProgress'])
            ->where('mto_id', $mtoId)
            ->findOrFail($id);

        if (is_null($alert->dashboard_viewed_at)) {
            $alert->update(['dashboard_viewed_at' => now()]);
        }

        $change      = $alert->change;
        $progressMap = $alert->actionProgress->keyBy('action_item_id');
        $csrfToken   = csrf_token();

        $severityColors = [
            'critical' => ['#dc2626','#fee2e2'],
            'high'     => ['#ea580c','#fff7ed'],
            'medium'   => ['#ca8a04','#fefce8'],
            'low'      => ['#2563eb','#eff6ff'],
        ];
        $sev = $change->severity ?? 'low';
        [$sevColor, $sevBg] = $severityColors[$sev] ?? ['#2563eb','#eff6ff'];

        $jurisdictions = is_array($change->affected_jurisdictions)
            ? implode(' · ', $change->affected_jurisdictions) : '—';
        $corridors = is_array($change->affected_corridors)
            ? implode(' · ', $change->affected_corridors) : '—';

        // Action items
        $actionItems = '';
        foreach ($change->actionItems ?? [] as $item) {
            $prog   = $progressMap[$item->id] ?? null;
            $status = $prog?->status ?? 'pending';
            $statusColors = ['completed'=>'#22c55e','in_progress'=>'#3b82f6','skipped'=>'#94a3b8','pending'=>'#f59e0b'];
            $sc = $statusColors[$status] ?? '#f59e0b';
            $catColors = ['due_diligence'=>'#7c3aed','account_closure'=>'#dc2626','reporting'=>'#2563eb','verification'=>'#059669'];
            $cc = $catColors[$item->category] ?? '#64748b';
            $reqBadge = $item->is_required ? "<span style='background:#fee2e2;color:#dc2626;padding:2px 7px;border-radius:10px;font-size:11px;font-weight:600'>Required</span>" : '';
            $dlNote = $item->deadline_days ? "<span style='color:#ea580c;font-size:12px'>⏰ Within {$item->deadline_days} days</span>" : '';
            $notes  = htmlspecialchars($prog?->notes ?? '');
            $progId = $prog?->id ?? '';
            $options = '';
            foreach (['pending'=>'To Do','in_progress'=>'In Progress','completed'=>'Completed','skipped'=>'N/A'] as $v=>$l) {
                $sel = $v === $status ? ' selected' : '';
                $options .= "<option value='{$v}'{$sel}>{$l}</option>";
            }
            $actionItems .= <<<HTML
<div class="action-item" id="action-{$item->id}">
  <div class="action-num" style="background:{$sc}">{$item->action_order}</div>
  <div class="action-body">
    <div class="action-meta">
      <span class="cat-badge" style="background:{$cc}22;color:{$cc}">{$item->category}</span>
      {$reqBadge} {$dlNote}
    </div>
    <div class="action-text">{$item->action_text}</div>
    <form method="POST" action="/portal/actions/{$item->id}/update" class="action-form">
      <input type="hidden" name="_token" value="{$csrfToken}">
      <input type="hidden" name="alert_id" value="{$alert->id}">
      <input type="hidden" name="progress_id" value="{$progId}">
      <div style="display:flex;gap:10px;align-items:center;margin-top:8px">
        <select name="status" class="form-select">{$options}</select>
        <input type="text" name="notes" value="{$notes}" placeholder="Add notes..." class="form-notes">
        <button class="btn-save">Save</button>
      </div>
    </form>
  </div>
</div>
HTML;
        }

        if (!$actionItems) {
            $actionItems = '<div style="color:#94a3b8;font-size:14px;padding:16px 0">No specific action items for this change.</div>';
        }

        $effDate  = $change->effective_date  ? date('M d, Y', strtotime($change->effective_date))  : 'Immediate';
        $deadline = $change->deadline        ? date('M d, Y', strtotime($change->deadline))         : 'No deadline set';
        $srcRef   = $change->source_reference ?? '—';
        $srcUrl   = $change->source_url ?? '';
        $srcLink  = $srcUrl ? "<a href='{$srcUrl}' target='_blank' style='color:#3b82f6'>{$srcRef}</a>" : $srcRef;

        $completed = $progressMap->where('status', 'completed')->count();
        $total     = $change->actionItems ? $change->actionItems->count() : 0;
        $pct       = $total > 0 ? round(($completed / $total) * 100) : 0;

        $body = <<<HTML
<a href="/portal/alerts" style="color:#64748b;font-size:13px;text-decoration:none;display:inline-block;margin-bottom:16px">← Back to alerts</a>

<div class="alert-detail-header" style="background:{$sevBg};border:1.5px solid {$sevColor}22;border-radius:14px;padding:24px 28px;margin-bottom:24px">
  <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
    <span style="background:{$sevColor};color:#fff;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700;text-transform:uppercase">{$sev}</span>
    <span style="color:#64748b;font-size:13px">{$change->source?->name}</span>
    <span style="color:#94a3b8;font-size:13px">·</span>
    <span style="color:#64748b;font-size:13px">{$alert->alerted_at}</span>
  </div>
  <h2 style="font-size:22px;font-weight:800;color:#0f172a;line-height:1.3;margin-bottom:10px">{$change->title}</h2>
  <p style="color:#475569;font-size:15px;line-height:1.7">{$change->plain_english_summary}</p>
</div>

<div class="detail-grid">
  <div class="detail-meta-box">
    <div class="meta-row"><span class="meta-label">Effective Date</span><span class="meta-val">{$effDate}</span></div>
    <div class="meta-row"><span class="meta-label">Compliance Deadline</span><span class="meta-val" style="color:#ea580c">{$deadline}</span></div>
    <div class="meta-row"><span class="meta-label">Affected Jurisdictions</span><span class="meta-val">{$jurisdictions}</span></div>
    <div class="meta-row"><span class="meta-label">Affected Corridors</span><span class="meta-val">{$corridors}</span></div>
    <div class="meta-row"><span class="meta-label">Source Reference</span><span class="meta-val">{$srcLink}</span></div>
  </div>
  <div class="progress-box">
    <div style="font-size:13px;font-weight:600;color:#0f172a;margin-bottom:10px">Action Progress</div>
    <div style="font-size:28px;font-weight:800;color:#0f172a">{$completed}<span style="font-size:16px;font-weight:500;color:#94a3b8"> / {$total}</span></div>
    <div style="color:#64748b;font-size:13px;margin-bottom:12px">actions completed</div>
    <div style="background:#e2e8f0;border-radius:8px;height:8px"><div style="background:#22c55e;width:{$pct}%;height:8px;border-radius:8px;transition:width 0.3s"></div></div>
    <div style="color:#22c55e;font-size:13px;font-weight:600;margin-top:6px">{$pct}% complete</div>
  </div>
</div>

<div class="section-header" style="margin-top:28px"><span>Required Actions</span></div>
<div class="actions-list">{$actionItems}</div>
HTML;
        return response($this->layout($change->title ?? 'Alert Detail', 'alerts', $body));
    }

    // ── Update Action ─────────────────────────────────────────────────────────

    public function updateAction(Request $request, $itemId)
    {
        $data    = $request->validate(['status' => 'required|in:pending,in_progress,completed,skipped', 'notes' => 'nullable|string', 'alert_id' => 'required|integer', 'progress_id' => 'nullable|integer']);
        $mtoId   = session('portal_mto_id');
        $alertId = $data['alert_id'];

        // Verify this alert belongs to this MTO
        MtoAlert::where('mto_id', $mtoId)->findOrFail($alertId);

        $updates = ['status' => $data['status'], 'notes' => $data['notes'] ?? null];
        if ($data['status'] === 'completed') $updates['completed_at'] = now();
        if ($data['status'] === 'in_progress' && !isset($data['started_at'])) $updates['started_at'] = now();

        if ($data['progress_id']) {
            MtoActionProgress::find($data['progress_id'])?->update($updates);
        } else {
            MtoActionProgress::create(array_merge($updates, ['mto_alert_id' => $alertId, 'action_item_id' => $itemId]));
        }

        return redirect("/portal/alerts/{$alertId}")->with('success', 'Action updated.');
    }

    // ── Layout ────────────────────────────────────────────────────────────────

    private function layout(string $title, string $activeTab, string $body): string
    {
        $userName = session('portal_user_name', 'User');
        $mtoName  = session('portal_mto_name', '');
        $tabs     = [
            'dashboard' => ['◉', 'Dashboard', '/portal'],
            'alerts'    => ['⚡', 'Alerts',    '/portal/alerts'],
        ];
        $nav = '';
        foreach ($tabs as $key => [$icon, $label, $url]) {
            $a = $activeTab === $key ? 'nav-active' : '';
            $nav .= "<a href='{$url}' class='nav-item {$a}'>{$icon} {$label}</a>";
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{$title} — RegTracker Portal</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f1f5f9;color:#1e293b;display:flex;min-height:100vh}
.sidebar{width:220px;background:#1e293b;display:flex;flex-direction:column;flex-shrink:0;position:fixed;top:0;left:0;height:100vh}
.sidebar-logo{padding:20px;border-bottom:1px solid #334155}
.logo-name{color:#fff;font-size:16px;font-weight:800}
.logo-mto{color:#94a3b8;font-size:12px;margin-top:2px}
.nav-item{display:flex;align-items:center;gap:10px;padding:11px 20px;color:#94a3b8;text-decoration:none;font-size:14px;font-weight:500;border-left:3px solid transparent;transition:all .15s}
.nav-item:hover{color:#e2e8f0;background:#334155}
.nav-active{color:#fff;background:#334155;border-left-color:#3b82f6}
.sidebar-footer{padding:16px 20px;border-top:1px solid #334155;margin-top:auto}
.user-info{color:#94a3b8;font-size:12px;margin-bottom:8px}
.sidebar-footer a{color:#64748b;font-size:12px;text-decoration:none}
.sidebar-footer a:hover{color:#94a3b8}
.main{margin-left:220px;flex:1;padding:28px 32px}
.portal-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:24px}
.portal-header h1{font-size:22px;font-weight:800;color:#0f172a}
.portal-header .sub{color:#64748b;font-size:14px;margin-top:2px}
.header-controls{display:flex;gap:10px;align-items:center}
.stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:28px}
.stat-card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:16px 18px}
.stat-num{font-size:26px;font-weight:800;line-height:1;margin-bottom:3px}
.stat-label{font-size:12px;color:#64748b;font-weight:500}
.section-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px}
.section-header span{font-size:15px;font-weight:700;color:#0f172a}
.link-more{color:#3b82f6;font-size:13px;text-decoration:none;font-weight:600}
.alert-list{display:flex;flex-direction:column;gap:10px}
.alert-card{display:block;background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:16px 20px;text-decoration:none;color:inherit;transition:box-shadow .15s}
.alert-card:hover{box-shadow:0 4px 16px rgba(0,0,0,.08)}
.alert-top{display:flex;align-items:center;gap:8px;margin-bottom:8px;flex-wrap:wrap}
.sev-badge{font-size:11px;font-weight:700;padding:3px 9px;border-radius:20px;text-transform:uppercase}
.new-badge{background:#dbeafe;color:#1d4ed8;font-size:11px;font-weight:700;padding:2px 8px;border-radius:10px}
.alert-src{color:#64748b;font-size:12px;font-weight:500}
.alert-date{color:#94a3b8;font-size:12px;margin-left:auto}
.alert-title{font-size:15px;font-weight:700;color:#0f172a;margin-bottom:4px}
.alert-type{font-size:12px;color:#94a3b8;text-transform:capitalize}
.empty-card{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:40px;text-align:center}
.empty-icon{font-size:36px;margin-bottom:12px}
.empty-card p{color:#94a3b8;font-size:14px}
.filter-bar{display:flex;align-items:center;gap:10px;margin-bottom:20px;flex-wrap:wrap}
.flash-success{background:#dcfce7;color:#16a34a;border:1px solid #bbf7d0;padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:16px}
.detail-grid{display:grid;grid-template-columns:1fr 220px;gap:16px}
.detail-meta-box{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:20px}
.meta-row{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f8fafc;font-size:13px}
.meta-row:last-child{border-bottom:0}
.meta-label{color:#64748b;font-weight:500}
.meta-val{color:#0f172a;font-weight:600;text-align:right;max-width:60%}
.progress-box{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:20px}
.actions-list{display:flex;flex-direction:column;gap:12px;margin-top:4px}
.action-item{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:16px 20px;display:flex;gap:14px;align-items:flex-start}
.action-num{width:28px;height:28px;border-radius:50%;color:#fff;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;flex-shrink:0;margin-top:2px}
.action-body{flex:1}
.action-meta{display:flex;align-items:center;gap:8px;margin-bottom:8px;flex-wrap:wrap}
.cat-badge{font-size:11px;font-weight:600;padding:2px 8px;border-radius:10px}
.action-text{font-size:14px;color:#1e293b;line-height:1.6;font-weight:500;margin-bottom:6px}
.action-form{margin-top:8px}
.form-select{padding:6px 10px;border:1.5px solid #e2e8f0;border-radius:7px;font-size:13px;background:#fff;cursor:pointer}
.form-notes{flex:1;padding:6px 10px;border:1.5px solid #e2e8f0;border-radius:7px;font-size:13px;min-width:150px}
.btn-save{padding:6px 14px;background:#3b82f6;color:#fff;border:none;border-radius:7px;font-size:13px;font-weight:600;cursor:pointer}
.btn-save:hover{background:#2563eb}
@media(max-width:700px){.sidebar{display:none}.main{margin-left:0}.detail-grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="sidebar">
  <div class="sidebar-logo">
    <div class="logo-name">RegTracker</div>
    <div class="logo-mto">{$mtoName}</div>
  </div>
  {$nav}
  <div class="sidebar-footer">
    <div class="user-info">Logged in as {$userName}</div>
    <a href="/portal/logout">Sign out</a>
  </div>
</div>
<div class="main">
  {$body}
</div>
</body>
</html>
HTML;
    }

    // ── Login Page ────────────────────────────────────────────────────────────

    private function loginPage(string $error, string $csrfToken): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>MTO Portal Login — RegTracker</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#1e293b;display:flex;align-items:center;justify-content:center;min-height:100vh}
.box{background:#fff;border-radius:16px;padding:40px;width:100%;max-width:400px;box-shadow:0 20px 60px rgba(0,0,0,.4)}
.logo{font-size:22px;font-weight:800;color:#0f172a;margin-bottom:4px}
.subtitle{font-size:14px;color:#64748b;margin-bottom:28px;line-height:1.5}
label{display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px}
input{width:100%;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;outline:none;margin-bottom:16px;transition:border .15s}
input:focus{border-color:#3b82f6}
button{width:100%;padding:11px;background:#1e293b;color:#fff;font-size:14px;font-weight:600;border:none;border-radius:8px;cursor:pointer}
button:hover{background:#0f172a}
.flash{padding:10px 14px;border-radius:8px;margin-bottom:16px;font-size:13px;font-weight:500;background:#fee2e2;color:#dc2626;border:1px solid #fecaca}
.footer-note{margin-top:20px;color:#94a3b8;font-size:12px;text-align:center}
</style>
</head>
<body>
<div class="box">
  <div class="logo">📋 MTO Compliance Portal</div>
  <div class="subtitle">Sign in to view regulatory alerts and manage your compliance actions</div>
  {$error}
  <form method="POST" action="/portal/login">
    <input type="hidden" name="_token" value="{$csrfToken}">
    <label>Email Address</label>
    <input type="email" name="email" placeholder="your@email.com" autofocus required>
    <label>Password</label>
    <input type="password" name="password" placeholder="Your password" required>
    <button type="submit">Sign In →</button>
  </form>
  <div class="footer-note">Contact your compliance administrator if you need access</div>
</div>
</body>
</html>
HTML;
    }
}
