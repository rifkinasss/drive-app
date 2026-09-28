<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#F8FAFC">
    <title>Drive by NasLabs API Gateway</title>
    <style>
        :root { --blue:#3B52E2; --ink:#0F172A; --secondary:#1E293B; --muted:#64748B; --line:#DCE3EF; --surface:#fff; --page:#F8FAFC; }
        * { box-sizing:border-box; }
        body { margin:0; min-height:100vh; background:var(--page); color:var(--ink); font:15px/1.55 Geist,Inter,ui-sans-serif,system-ui,-apple-system,sans-serif; }
        a { color:inherit; }
        a:focus-visible { outline:3px solid #8290FF; outline-offset:3px; }
        .shell { width:min(1120px,calc(100% - 48px)); min-height:100vh; margin:0 auto; display:flex; flex-direction:column; }
        .topbar { min-height:82px; display:flex; align-items:center; justify-content:space-between; border-bottom:1px solid var(--line); }
        .brand { display:flex; align-items:center; gap:12px; text-decoration:none; }
        .brand-icon { width:42px; height:42px; display:grid; place-items:center; border:1px solid #D7DEFF; border-radius:13px; color:var(--blue); background:#EEF1FF; }
        .brand-name { font-size:16px; line-height:1.2; font-weight:750; letter-spacing:-.03em; }
        .brand-name small { display:block; margin-top:4px; color:var(--muted); font-size:11px; font-weight:600; letter-spacing:.08em; text-transform:uppercase; }
        .nav { display:flex; align-items:center; gap:24px; color:var(--secondary); font-size:13px; font-weight:650; }
        .nav a { text-decoration:none; } .nav a:hover { color:var(--blue); }
        .status { display:inline-flex; align-items:center; gap:8px; color:#176B48; white-space:nowrap; }
        .dot { width:8px; height:8px; border-radius:50%; background:#24A36A; box-shadow:0 0 0 3px #DDF5E9; }
        main { flex:1; display:flex; flex-direction:column; justify-content:center; padding:48px 0 56px; }
        .hero-grid { display:grid; grid-template-columns:minmax(0,1.25fr) minmax(310px,.75fr); gap:56px; align-items:center; }
        .eyebrow { display:inline-flex; align-items:center; gap:8px; color:var(--blue); font-size:12px; font-weight:750; letter-spacing:.1em; text-transform:uppercase; }
        h1 { max-width:660px; margin:16px 0 16px; font-size:clamp(42px,6vw,66px); line-height:1.04; letter-spacing:-.065em; }
        .intro { max-width:570px; margin:0; color:var(--muted); font-size:17px; line-height:1.7; }
        .actions { display:flex; flex-wrap:wrap; gap:12px; margin-top:28px; }
        .button { min-height:46px; display:inline-flex; align-items:center; justify-content:center; gap:9px; padding:0 17px; border:1px solid transparent; border-radius:9px; text-decoration:none; font-size:14px; font-weight:700; transition:background .15s,border-color .15s,transform .15s; }
        .button:hover { transform:translateY(-1px); } .button-primary { color:white; background:var(--blue); } .button-primary:hover { background:#3046D1; }
        .button-secondary { color:var(--secondary); background:white; border-color:var(--line); } .button-secondary:hover { border-color:#AAB7D0; }
        .spec-link { display:inline-block; margin-top:16px; color:var(--muted); font-size:13px; text-decoration:underline; text-underline-offset:3px; }
        .panel { overflow:hidden; border:1px solid var(--line); border-radius:16px; background:var(--surface); box-shadow:0 12px 34px rgb(15 23 42 / 5%); }
        .panel-head { display:flex; align-items:center; justify-content:space-between; padding:19px 20px; border-bottom:1px solid var(--line); }
        .panel-head strong { font-size:14px; letter-spacing:-.01em; } .panel-head span { color:var(--muted); font:12px ui-monospace,SFMono-Regular,Menlo,monospace; }
        .info-list { margin:0; padding:4px 20px; list-style:none; }
        .info-list li { display:flex; align-items:center; justify-content:space-between; gap:16px; min-height:49px; border-bottom:1px solid #EDF1F6; font-size:13px; }
        .info-list li:last-child { border-bottom:0; } .info-list li span:first-child { color:var(--muted); } .info-list b { color:var(--secondary); font-size:13px; font-weight:650; }
        .auth-note { display:flex; gap:12px; align-items:flex-start; margin-top:14px; padding:15px 17px; border:1px solid #DCE3EF; border-radius:12px; background:#F1F4FA; color:var(--secondary); font-size:12px; }
        .auth-note svg { flex:none; color:var(--blue); } .auth-note p { margin:0; } .auth-note strong { display:block; margin-bottom:3px; font-size:13px; }
        .capabilities { display:flex; flex-wrap:wrap; gap:9px; margin-top:38px; padding-top:24px; border-top:1px solid var(--line); }
        .capability { display:inline-flex; align-items:center; gap:7px; padding:8px 11px; border:1px solid var(--line); border-radius:999px; background:#fff; color:#334155; font-size:12px; font-weight:620; }
        .capability svg { color:var(--blue); }
        footer { min-height:64px; display:flex; align-items:center; justify-content:space-between; gap:16px; border-top:1px solid var(--line); color:var(--muted); font-size:12px; }
        footer strong { color:var(--secondary); font-weight:650; }
        @media (max-width:760px) { .shell { width:min(100% - 36px,600px); } .topbar { min-height:72px; } .nav { gap:16px; } .nav .status { display:none; } main { justify-content:flex-start; padding:48px 0 38px; } .hero-grid { grid-template-columns:1fr; gap:32px; } h1 { font-size:clamp(42px,10vw,58px); } .capabilities { margin-top:30px; } }
        @media (max-width:420px) { .shell { width:calc(100% - 28px); } .nav { gap:12px; font-size:12px; } .brand-icon { width:38px; height:38px; } .actions { display:grid; grid-template-columns:1fr; } .button { width:100%; } footer { align-items:flex-start; flex-direction:column; justify-content:center; padding:15px 0; gap:3px; } }
    </style>
</head>
<body>
<div class="shell">
    <header class="topbar">
        <a class="brand" href="{{ route('backend.home') }}" aria-label="Drive by NasLabs API Gateway home">
            <span class="brand-icon" aria-hidden="true"><svg width="25" height="25" viewBox="0 0 24 24" fill="none"><path d="M7.2 18.2h9.25a4.05 4.05 0 0 0 .55-8.06 5.45 5.45 0 0 0-10.46-1.2 4.66 4.66 0 0 0 .66 9.26Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
            <span class="brand-name">Drive <small>by NasLabs</small></span>
        </a>
        <nav class="nav" aria-label="Backend resources">
            <span class="status"><span class="dot" aria-hidden="true"></span>Operational</span>
            @if (config('docs.enabled'))
                <a href="{{ route('docs.landing') }}">Documentation</a>
                <a href="{{ route('scramble.docs.ui') }}">API Reference</a>
            @endif
        </nav>
    </header>
    <main>
        <div class="hero-grid">
            <section aria-labelledby="page-title">
                <span class="eyebrow"><span class="dot" aria-hidden="true"></span>Backend · REST API</span>
                <h1 id="page-title">Drive by NasLabs API</h1>
                <p class="intro">Private file storage backend for file management, sharing, storage, and administration. The backend powering Drive by NasLabs.</p>
                <div class="actions">
                    @if (config('docs.enabled'))
                        <a class="button button-primary" href="{{ route('docs.landing') }}">Explore Documentation <span aria-hidden="true">→</span></a>
                        <a class="button button-secondary" href="{{ route('scramble.docs.ui') }}">API Reference</a>
                    @else
                        <span class="button button-secondary" aria-disabled="true">Documentation unavailable</span>
                    @endif
                </div>
                @if (config('docs.enabled'))
                    <a class="spec-link" href="{{ route('scramble.docs.document') }}">OpenAPI Specification</a>
                @endif
            </section>
            <aside aria-label="API information">
                <section class="panel">
                    <div class="panel-head"><strong>API Overview</strong><span>STATUS / READY</span></div>
                    <ul class="info-list">
                        <li><span>API status</span><b class="status"><span class="dot" aria-hidden="true"></span>Operational</b></li>
                    <li><span>API version</span><b>v{{ config('docs.version', '2.0.0') }}</b></li>
                        <li><span>Framework</span><b>Laravel</b></li>
                        <li><span>Database</span><b>PostgreSQL</b></li>
                        <li><span>Authentication</span><b>Sanctum Session</b></li>
                        <li><span>API reference</span><b>{{ config('docs.enabled') ? 'Scramble' : 'Disabled' }}</b></li>
                    </ul>
                </section>
                <div class="auth-note">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2" stroke="currentColor" stroke-width="1.7"/><path d="M8 10V7a4 4 0 1 1 8 0v3m-4 5v2" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                    <p><strong>Session-based authentication</strong>This API primarily serves the first-party Drive app using Laravel Sanctum cookies.</p>
                </div>
            </aside>
        </div>
        <section class="capabilities" aria-label="API capabilities">
            @foreach (['Files & Folders', 'Storage', 'Sharing', 'Activity', 'Notifications', 'Administration'] as $capability)
                <span class="capability"><svg width="14" height="14" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="m3.5 8.2 2.8 2.7 6.2-6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>{{ $capability }}</span>
            @endforeach
        </section>
    </main>
    <footer><strong>Drive by NasLabs</strong><span>Private file infrastructure by NasLabs · © {{ date('Y') }} NasLabs · API v{{ config('docs.version', '2.0.0') }}</span></footer>
</div>
</body>
</html>
