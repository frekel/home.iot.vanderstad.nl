<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Kisten op zolder') · Van der Stad</title>
    <style>
        :root { color-scheme: dark; font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background:#0b0f14; color:#f5f7fa; }
        * { box-sizing:border-box; }
        body { margin:0; background:#0b0f14; color:#f5f7fa; }
        a { color:inherit; }
        button,input,textarea,select { font:inherit; }
        .shell { width:min(760px, 100%); margin:0 auto; padding:20px 16px 48px; }
        .topbar { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:20px; }
        .topbar a { text-decoration:none; color:#aeb8c4; }
        h1 { font-size:clamp(28px, 8vw, 42px); line-height:1; margin:0 0 8px; }
        h2 { font-size:20px; margin:0 0 14px; }
        p { color:#aeb8c4; line-height:1.5; }
        .card { background:#151b23; border:1px solid #26303c; border-radius:18px; padding:16px; margin-bottom:14px; }
        .stack { display:grid; gap:12px; }
        .row { display:flex; gap:10px; align-items:center; }
        .row > * { min-width:0; }
        label { display:grid; gap:6px; color:#cbd3dc; font-size:14px; }
        input,textarea,select { width:100%; color:#fff; background:#0f141b; border:1px solid #344152; border-radius:12px; padding:13px 14px; outline:none; }
        input:focus,textarea:focus,select:focus { border-color:#7aa7ff; box-shadow:0 0 0 3px rgba(122,167,255,.14); }
        input[type=number] { font-variant-numeric:tabular-nums; }
        input[type=checkbox],input[type=radio] { width:auto; padding:0; accent-color:#f5f7fa; }
        textarea { min-height:76px; resize:vertical; }
        .button { border:0; border-radius:12px; padding:13px 16px; background:#f5f7fa; color:#0b0f14; font-weight:700; cursor:pointer; text-decoration:none; text-align:center; display:inline-flex; justify-content:center; align-items:center; }
        .button.secondary { background:#26303c; color:#fff; }
        .button.danger { background:#452329; color:#ffd8df; }
        .button.full { width:100%; }
        .button[disabled] { opacity:.45; cursor:not-allowed; }
        .muted { color:#8d99a7; }
        .status { padding:12px 14px; border-radius:12px; margin-bottom:14px; background:#173621; color:#bff2cc; border:1px solid #285537; }
        .error { padding:12px 14px; border-radius:12px; margin-bottom:14px; background:#3b1e23; color:#ffd7dd; border:1px solid #6b313b; }
        .box-list { display:grid; gap:10px; }
        .box-link { display:flex; justify-content:space-between; align-items:center; text-decoration:none; padding:16px; border-radius:14px; background:#151b23; border:1px solid #26303c; }
        .box-number { font-size:28px; font-weight:800; letter-spacing:-.03em; }
        .box-name { margin:4px 0 0; color:#f5f7fa; font-size:18px; font-weight:700; }
        .box-list-name { margin-top:4px; color:#cbd3dc; font-weight:700; }
        .item { display:grid; gap:10px; }
        .item-header { display:flex; justify-content:space-between; gap:12px; align-items:center; }
        .item-header strong { font-size:18px; min-width:0; }
        .item-actions { display:flex; gap:8px; flex-shrink:0; }
        .button.compact { padding:8px 10px; border-radius:10px; font-size:13px; }
        .item-panel { padding-top:12px; border-top:1px solid #26303c; }
        .inline-actions { display:flex; gap:8px; margin-top:10px; }
        .inline-actions > * { flex:1; }
        .photo-input { padding:12px; background:#0f141b; border:1px dashed #46566a; border-radius:12px; }
        .photo-input input { border:0; padding:0; background:transparent; }
        .photo-count { font-size:13px; color:#8d99a7; }
        .subheading { margin:18px 0 10px; font-size:16px; }
        .photo-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; }
        .photo-card { overflow:hidden; border:1px solid #26303c; border-radius:12px; background:#0f141b; text-decoration:none; }
        .photo-card img { display:block; width:100%; aspect-ratio:4/3; object-fit:cover; }
        .photo-card span { display:block; padding:8px 10px; color:#8d99a7; font-size:12px; }
        .empty { text-align:center; padding:24px 14px; color:#8d99a7; }
        .number-entry { font-size:30px; text-align:center; font-weight:800; letter-spacing:.06em; }
        .choice { display:flex; grid-template-columns:none; flex-direction:row; align-items:flex-start; gap:10px; padding:12px; background:#0f141b; border:1px solid #344152; border-radius:12px; }
        .choice span { display:grid; gap:3px; }
        .choice small { color:#8d99a7; line-height:1.35; }
        .confidence { font-size:12px; color:#8d99a7; text-transform:uppercase; letter-spacing:.05em; }
        @media (min-width:640px) { .shell { padding-top:36px; } .card { padding:20px; } }
    </style>
</head>
<body>
<main class="shell">
    @yield('content')
</main>
</body>
</html>
