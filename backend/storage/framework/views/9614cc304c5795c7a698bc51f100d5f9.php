<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cloud by NasLabs API</title>
    <style>
        :root { --primary:#3B52E2; --ink:#0F172A; --muted:#64748B; --surface:#fff; --line:#E2E8F0; --bg:#F8FAFC; }
        * { box-sizing:border-box; } body { margin:0; background:var(--bg); color:var(--ink); font:16px/1.6 Inter,ui-sans-serif,system-ui,sans-serif; }
        .wrap { width:min(1080px,calc(100% - 40px)); margin:auto; } header { padding:26px 0; display:flex; align-items:center; justify-content:space-between; }
        .brand { display:flex; gap:12px; align-items:center; font-weight:750; } .mark { width:34px; height:34px; display:grid; place-items:center; border-radius:10px; background:var(--primary); color:#fff; font-weight:800; }
        .label { color:var(--muted); font-size:14px; } .hero { padding:74px 0 58px; max-width:780px; } h1 { font-size:clamp(40px,7vw,72px); line-height:1.02; letter-spacing:-.06em; margin:14px 0 22px; } h2 { letter-spacing:-.03em; margin:0 0 10px; }
        p { color:var(--muted); margin:0 0 24px; } .eyebrow { color:var(--primary); font-size:13px; font-weight:750; letter-spacing:.12em; text-transform:uppercase; }
        .actions { display:flex; flex-wrap:wrap; gap:12px; } a.button { display:inline-block; padding:12px 18px; border-radius:9px; text-decoration:none; font-weight:700; } .primary { color:#fff; background:var(--primary); } .secondary { color:var(--ink); background:var(--surface); border:1px solid var(--line); }
        .status, .cards, .steps { display:grid; gap:14px; grid-template-columns:repeat(4,1fr); } .status { margin:0 0 70px; } .card { background:var(--surface); border:1px solid var(--line); border-radius:14px; padding:20px; } .card strong { display:block; margin-top:5px; } .section { padding:42px 0; border-top:1px solid var(--line); } .steps { grid-template-columns:repeat(3,1fr); } .step b { color:var(--primary); font-size:13px; } code, pre { font:13px/1.6 ui-monospace,SFMono-Regular,Menlo,monospace; } pre { overflow:auto; background:#0F172A; color:#E2E8F0; border-radius:12px; padding:18px; }
        footer { padding:48px 0; color:var(--muted); font-size:14px; } @media (max-width:720px) { .status,.cards,.steps { grid-template-columns:1fr 1fr; } header { align-items:flex-start; gap:10px; } .hero { padding-top:48px; } } @media (max-width:460px) { .status,.cards,.steps { grid-template-columns:1fr; } .wrap { width:min(100% - 28px,1080px); } }
    </style>
</head>
<body>
<div class="wrap">
    <header><div class="brand"><span class="mark">C</span><span>Cloud by NasLabs</span></div><span class="label">API Documentation · REST API</span></header>
    <main>
        <section class="hero"><span class="eyebrow">Developer portal</span><h1>Build integrations with your private cloud.</h1><p>REST API for authentication, file management, storage, sharing, notifications, and administration.</p><div class="actions"><a class="button primary" href="<?php echo e(url('/docs/api')); ?>">Open API Reference</a><a class="button secondary" href="<?php echo e(url('/docs/api.json')); ?>">OpenAPI Specification</a></div></section>
        <section class="status"><div class="card"><span class="label">API version</span><strong><?php echo e(config('docs.version')); ?></strong></div><div class="card"><span class="label">Authentication</span><strong>Sanctum Session</strong></div><div class="card"><span class="label">Database</span><strong>PostgreSQL</strong></div><div class="card"><span class="label">Format</span><strong>JSON / REST</strong></div></section>
        <section class="section"><h2>Getting started</h2><p>Browser clients use first-party session cookies. Include credentials on every request.</p><div class="steps"><div class="step"><b>01</b><h3>Initialize CSRF</h3><p>Request <code>/sanctum/csrf-cookie</code>.</p></div><div class="step"><b>02</b><h3>Sign in</h3><p>Post credentials to <code>/api/auth/login</code>.</p></div><div class="step"><b>03</b><h3>Use the API</h3><p>Send the session cookies with protected requests.</p></div></div><pre>curl -c cookies.txt <?php echo e(rtrim(config('app.url'), '/')); ?>/sanctum/csrf-cookie
curl -b cookies.txt -c cookies.txt -X POST <?php echo e(rtrim(config('app.url'), '/')); ?>/api/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"user@example.com","password":"password"}'</pre></section>
        <section class="section"><h2>API capabilities</h2><div class="cards"><div class="card"><h3>Authentication</h3><p>Sanctum session login and account recovery.</p></div><div class="card"><h3>Files &amp; folders</h3><p>Browse, upload, move, star, preview, and download.</p></div><div class="card"><h3>Sharing</h3><p>Internal permissions and token-scoped public links.</p></div><div class="card"><h3>Administration</h3><p>Users, quotas, notifications, and system settings.</p></div></div></section>
        <section class="section"><h2>Access model</h2><p>Most endpoints require authenticated Sanctum session cookies. <code>/api/public/shares/*</code> uses a scoped share token. <code>/api/admin/*</code> is restricted to administrators.</p></section>
    </main>
    <footer>Cloud by NasLabs · Private cloud infrastructure by NasLabs · © <?php echo e(date('Y')); ?> NasLabs</footer>
</div>
</body>
</html>
<?php /**PATH /Users/rifkinasss/Documents/workspace/Startup/NasLabs/internal-projects/Cloud-V2-Backend/resources/views/docs.blade.php ENDPATH**/ ?>