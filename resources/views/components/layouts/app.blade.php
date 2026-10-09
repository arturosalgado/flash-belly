<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Flashcards' }}</title>

    <style>
        :root {
            --bg: #09090b;
            --surface: #131316;
            --surface-2: #1c1c21;
            --border: #2a2a31;
            --text: #f4f4f5;
            --muted: #a1a1aa;
            --accent: #f59e0b;
            --success: #22c55e;
            --danger: #f43f5e;
            --warning: #eab308;
        }

        @media (prefers-color-scheme: light) {
            :root {
                --bg: #f4f4f5;
                --surface: #ffffff;
                --surface-2: #f4f4f5;
                --border: #e4e4e7;
                --text: #18181b;
                --muted: #52525b;
            }
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            background: var(--bg);
            color: var(--text);
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        .shell {
            max-width: 52rem;
            margin: 0 auto;
            padding: 1.5rem 1.25rem 4rem;
        }

        .topbar {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
        }

        .brand {
            font-size: 1.05rem;
            font-weight: 700;
            letter-spacing: -0.01em;
            text-decoration: none;
            color: var(--text);
        }

        .brand span { color: var(--accent); }

        .link {
            color: var(--muted);
            text-decoration: none;
            font-size: 0.875rem;
            border: 1px solid var(--border);
            padding: 0.4rem 0.75rem;
            border-radius: 0.5rem;
            transition: color .15s, border-color .15s;
        }

        .link:hover { color: var(--text); border-color: var(--muted); }

        select {
            width: 100%;
            background: var(--surface);
            color: var(--text);
            border: 1px solid var(--border);
            border-radius: 0.625rem;
            padding: 0.6rem 0.75rem;
            font-size: 0.925rem;
            font-family: inherit;
            cursor: pointer;
        }

        select:focus { outline: 2px solid var(--accent); outline-offset: 1px; }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 1px 3px rgb(0 0 0 / 0.25);
        }

        .meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
            margin-bottom: 1.5rem;
        }

        .badge {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.2rem 0.6rem;
            border-radius: 999px;
            background: var(--surface-2);
            color: var(--muted);
            border: 1px solid var(--border);
            white-space: nowrap;
        }

        .badge.success { color: var(--success); border-color: color-mix(in srgb, var(--success) 40%, transparent); }
        .badge.danger  { color: var(--danger);  border-color: color-mix(in srgb, var(--danger) 40%, transparent); }
        .badge.warning { color: var(--warning); border-color: color-mix(in srgb, var(--warning) 40%, transparent); }

        .card-image {
            display: block;
            max-width: 100%;
            max-height: 280px;
            margin: 0 auto 1.25rem;
            border-radius: 0.75rem;
            object-fit: contain;
            background: #111;
        }

        .image-choices {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 0.6rem;
            margin-top: 1rem;
        }

        .image-choices button {
            padding: 0.25rem;
            height: auto;
        }

        .image-choices img {
            width: 100%;
            height: 120px;
            object-fit: contain;
            display: block;
            background: #111;
            border-radius: 0.4rem;
        }

        .question {
            font-size: 1.65rem;
            line-height: 1.35;
            font-weight: 650;
            margin: 0;
            letter-spacing: -0.015em;
        }

        .answer {
            margin: 1.5rem 0 0;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border);
            font-size: 1.2rem;
            line-height: 1.55;
            color: var(--muted);
        }

        .controls {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-top: 2rem;
        }

        button {
            font-family: inherit;
            font-size: 0.95rem;
            font-weight: 600;
            padding: 0.75rem 1.25rem;
            border-radius: 0.625rem;
            border: 1px solid var(--border);
            background: var(--surface-2);
            color: var(--text);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: transform .08s, filter .15s;
        }

        button:hover { filter: brightness(1.15); }
        button:active { transform: translateY(1px); }

        button.primary { background: var(--accent); border-color: var(--accent); color: #1c1300; }
        button.success { background: var(--success); border-color: var(--success); color: #04240f; }
        button.danger  { background: var(--danger);  border-color: var(--danger);  color: #2b0410; }
        button.ghost   { background: transparent; color: var(--muted); }

        .hint {
            margin: 0.9rem 0 0;
            font-size: 0.775rem;
            color: var(--muted);
        }

        kbd {
            background: var(--surface-2);
            border: 1px solid var(--border);
            border-bottom-width: 2px;
            border-radius: 0.3rem;
            padding: 0.05rem 0.35rem;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 0.75rem;
            color: var(--text);
        }

        .deck {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
            margin-top: 1.25rem;
            justify-content: center;
        }

        .empty { text-align: center; padding: 2.5rem 1rem; }
        .empty h2 { margin: 0 0 0.5rem; font-size: 1.15rem; }
        .empty p { margin: 0 0 1.5rem; color: var(--muted); }
    </style>

    @livewireStyles
</head>
<body>
    {{ $slot }}

    @livewireScripts
</body>
</html>
