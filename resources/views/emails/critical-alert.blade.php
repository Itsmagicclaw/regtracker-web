<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  body { font-family: Arial, sans-serif; color: #333; margin: 0; padding: 0; background: #f5f5f5; }
  .container { max-width: 600px; margin: 30px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
  .header { background: #1B2A4A; padding: 24px 32px; }
  .header h1 { color: #fff; margin: 0; font-size: 20px; }
  .header p { color: #a0b4d0; margin: 6px 0 0; font-size: 14px; }
  .severity-badge { display: inline-block; padding: 4px 12px; border-radius: 4px; font-weight: bold; font-size: 13px; text-transform: uppercase; }
  .critical { background: #FDECEA; color: #C00000; }
  .high     { background: #FFF2CC; color: #ED7D31; }
  .medium   { background: #E2F0D9; color: #217346; }
  .body { padding: 32px; }
  .section { margin-bottom: 24px; }
  .section h2 { font-size: 16px; color: #1B2A4A; margin: 0 0 8px; }
  .summary { background: #D6E4F7; border-left: 4px solid #1E4A8C; padding: 14px 18px; border-radius: 4px; font-size: 14px; line-height: 1.6; }
  .action-list { list-style: none; padding: 0; margin: 0; }
  .action-list li { padding: 10px 0; border-bottom: 1px solid #eee; font-size: 14px; display: flex; align-items: flex-start; gap: 10px; }
  .action-list li:last-child { border-bottom: none; }
  .action-num { background: #1E4A8C; color: #fff; width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: bold; flex-shrink: 0; }
  .meta { background: #f9f9f9; border-radius: 4px; padding: 14px 18px; font-size: 13px; }
  .meta p { margin: 4px 0; }
  .deadline { background: #FDECEA; color: #C00000; padding: 10px 18px; border-radius: 4px; font-weight: bold; font-size: 14px; }
  .cta { text-align: center; margin-top: 24px; }
  .cta a { background: #1E4A8C; color: #fff; padding: 12px 28px; border-radius: 6px; text-decoration: none; font-weight: bold; font-size: 14px; }
  .footer { background: #f5f5f5; padding: 18px 32px; text-align: center; font-size: 12px; color: #999; }
</style>
</head>
<body>
<div class="container">
  <div class="header">
    <h1>⚠ Compliance Alert — Action Required</h1>
    <p>RegTracker | RemitSo Compliance Intelligence</p>
  </div>
  <div class="body">
    <div class="section">
      <span class="severity-badge {{ $change->severity }}">{{ strtoupper($change->severity) }}</span>
      <h2 style="margin-top:12px;">{{ $change->title }}</h2>
    </div>

    <div class="section">
      <h2>What Changed</h2>
      <div class="summary">{{ $change->plain_english_summary }}</div>
    </div>

    @if($change->deadline)
    <div class="deadline">⏰ Deadline: {{ \Carbon\Carbon::parse($change->deadline)->format('d M Y') }}</div>
    @endif

    <div class="section" style="margin-top:20px;">
      <h2>Action Checklist</h2>
      <ul class="action-list">
        @foreach($change->actionItems as $i => $action)
        <li>
          <span class="action-num">{{ $i + 1 }}</span>
          <span>{{ $action->action_text }}</span>
        </li>
        @endforeach
      </ul>
    </div>

    <div class="section">
      <div class="meta">
        <p><strong>Source:</strong> {{ $change->source_reference }}</p>
        <p><strong>Change Type:</strong> {{ str_replace('_', ' ', ucfirst($change->change_type)) }}</p>
        @if($change->effective_date)
        <p><strong>Effective Date:</strong> {{ \Carbon\Carbon::parse($change->effective_date)->format('d M Y') }}</p>
        @endif
        @if($change->source_url)
        <p><strong>Source:</strong> <a href="{{ $change->source_url }}">View official document</a></p>
        @endif
      </div>
    </div>

    <div class="cta">
      <a href="{{ config('app.url') }}/dashboard">Open Dashboard & Mark Actions Complete</a>
    </div>
  </div>
  <div class="footer">
    RegTracker by RemitSo &mdash; Live Regulatory Change Tracker for MTOs<br>
    This is an automated compliance alert. Do not reply to this email.
  </div>
</div>
</body>
</html>
