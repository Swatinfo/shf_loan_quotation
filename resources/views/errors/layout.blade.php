<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Shared error shell. Standalone by design: error pages render when the
         app is in maintenance mode, a backing service (DB) is down, auth is
         unavailable, or a request failed — so it must NOT depend on the app
         layout, auth, the database, or named routes. Static assets under
         /images and /fonts are served by the web server directly. --}}
    @yield('head')
    <title>@yield('title', 'Error') · SHF World</title>
    <style>
        :root {
            --primary-dark: #3a3536;
            --accent: #f15a29;
            --accent-warm: #f47929;
            --bg: #f8f8f8;
            --bg-alt: #e6e7e8;
            --text: #1a1a1a;
            --text-muted: #6b7280;
            --border: #bcbec0;
            --white: #fff;
        }

        * { box-sizing: border-box; }

        html, body { height: 100%; margin: 0; }

        body {
            font-family: 'Archivo', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg);
            background-image: radial-gradient(circle at 50% 0, var(--bg-alt), var(--bg) 60%);
            color: var(--text);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            -webkit-font-smoothing: antialiased;
        }

        .card {
            width: 100%;
            max-width: 480px;
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(58, 53, 54, 0.08);
            padding: 40px 28px;
            text-align: center;
        }

        .logo { height: 40px; width: auto; margin: 0 0 24px; }

        .icon {
            width: 76px;
            height: 76px;
            margin: 0 auto 22px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, rgba(241, 90, 41, 0.12), rgba(244, 121, 41, 0.12));
            color: var(--accent);
        }

        h1 {
            font-family: 'Jost', 'Archivo', system-ui, sans-serif;
            font-size: 1.55rem;
            font-weight: 600;
            margin: 0 0 8px;
            color: var(--primary-dark);
        }

        .code {
            font-size: 0.72rem;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: 16px;
        }

        p {
            color: var(--text-muted);
            font-size: 0.95rem;
            line-height: 1.6;
            margin: 0 auto 10px;
            max-width: 380px;
        }

        .detail { font-size: 0.85rem; }

        .gu { font-size: 0.85rem; color: var(--text-muted); margin: 6px auto 0; max-width: 380px; }

        .actions {
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 24px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid transparent;
            cursor: pointer;
            font: inherit;
            font-weight: 600;
            font-size: 0.9rem;
            padding: 10px 22px;
            border-radius: 999px;
            color: var(--white);
            background: linear-gradient(135deg, var(--accent), var(--accent-warm));
            text-decoration: none;
            transition: transform 0.12s ease, box-shadow 0.12s ease;
        }

        .btn:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(241, 90, 41, 0.3); }

        .btn-outline {
            background: transparent;
            color: var(--accent);
            border-color: var(--accent);
        }

        .btn-outline:hover { box-shadow: 0 6px 16px rgba(241, 90, 41, 0.15); }

        .footer { margin-top: 26px; font-size: 0.75rem; color: var(--text-muted); }

        @media (max-width: 480px) {
            .card { padding: 32px 20px; border-radius: 14px; }
            h1 { font-size: 1.35rem; }
        }
    </style>
</head>

<body>
    <main class="card" role="main">
        <img class="logo" src="/images/logo3.png" alt="SHF World" onerror="this.style.display='none'">

        <div class="icon" aria-hidden="true">
            @yield('icon')
        </div>

        <h1>@yield('title', 'Something went wrong')</h1>
        <div class="code">@yield('code')</div>

        @yield('body')

        <div class="actions">
            @hasSection('actions')
                @yield('actions')
            @else
                <a class="btn btn-outline" href="javascript:history.back()">Go Back</a>
                <a class="btn" href="/dashboard">Go to Dashboard</a>
            @endif
        </div>

        <div class="footer">&copy; {{ date('Y') }} Shreenathji Home Finance</div>
    </main>
</body>

</html>
