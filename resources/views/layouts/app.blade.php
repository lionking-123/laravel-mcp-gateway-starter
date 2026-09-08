<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('mcp.server.title'))</title>
    <style>
        :root { --ink:#1c2430; --muted:#5b6673; --line:#dfe4ea; --bg:#f6f8fa; --card:#fff; --accent:#0f5c8a; --ok:#1a7f4b; --warn:#b4540a; --bad:#b42318; }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--bg); color:var(--ink); font:15px/1.55 -apple-system,"Segoe UI",Inter,Roboto,Helvetica,Arial,sans-serif; }
        header { background:var(--card); border-bottom:1px solid var(--line); }
        header .wrap { display:flex; align-items:center; justify-content:space-between; }
        header a.brand { font-weight:600; color:var(--ink); text-decoration:none; }
        .wrap { max-width:880px; margin:0 auto; padding:16px 20px; }
        main.wrap { padding-top:28px; padding-bottom:60px; }
        h1 { font-size:26px; margin:0 0 8px; } h2 { font-size:18px; margin:32px 0 10px; }
        p.lede { color:var(--muted); margin:0 0 20px; }
        .card { background:var(--card); border:1px solid var(--line); border-radius:10px; padding:18px 20px; margin-bottom:16px; }
        code, pre { font-family:ui-monospace,"Cascadia Code",Consolas,Menlo,monospace; font-size:13px; }
        pre { background:#f0f3f6; border-radius:8px; padding:12px 14px; overflow-x:auto; margin:8px 0 0; }
        table { width:100%; border-collapse:collapse; font-size:14px; } th, td { text-align:left; padding:8px 6px; border-bottom:1px solid var(--line); vertical-align:top; }
        th { color:var(--muted); font-weight:600; font-size:12px; text-transform:uppercase; letter-spacing:.04em; }
        .pill { display:inline-block; font-size:12px; padding:2px 8px; border-radius:999px; background:#e3eff7; color:var(--accent); font-weight:600; }
        .pill.ok { background:#e3f5ea; color:var(--ok);} .pill.warn { background:#fdeeda; color:var(--warn);} .pill.bad { background:#fde8e6; color:var(--bad);}
        label { display:block; font-size:13px; color:var(--muted); margin:12px 0 4px; }
        input[type=email], input[type=password] { width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:8px; font-size:15px; }
        button, .btn { display:inline-block; border:0; border-radius:8px; padding:10px 16px; font-size:15px; font-weight:600; cursor:pointer; text-decoration:none; }
        button.primary { background:var(--accent); color:#fff; } button.ghost { background:transparent; color:var(--muted); border:1px solid var(--line); }
        .error { color:var(--bad); font-size:14px; margin-top:8px; }
        .muted { color:var(--muted); } .small { font-size:13px; }
        .actions { display:flex; gap:10px; margin-top:18px; }
    </style>
</head>
<body>
<header>
    <div class="wrap">
        <a class="brand" href="{{ route('home') }}">{{ config('mcp.server.title') }}</a>
        <div class="small">
            @auth
                {{ auth()->user()->email }}
                <form method="POST" action="{{ route('logout') }}" style="display:inline">@csrf <button class="ghost" style="padding:4px 10px;font-size:13px">Sign out</button></form>
            @else
                <a href="{{ route('login') }}">Sign in</a>
            @endauth
        </div>
    </div>
</header>
<main class="wrap">
    @yield('content')
</main>
</body>
</html>
