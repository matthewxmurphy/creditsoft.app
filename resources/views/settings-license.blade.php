<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>CreditSoft | License & Renewal</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #f7f3eb;
            --panel: rgba(255,255,255,0.92);
            --border: rgba(120,113,108,0.18);
            --text: #1c1917;
            --muted: #57534e;
            --soft: #78716c;
            --accent: #15803d;
            --accent-ink: #14532d;
            --warn-bg: #fff7ed;
            --warn-border: #fdba74;
            --warn-text: #9a3412;
            --bad-bg: #fff1f2;
            --bad-border: #fda4af;
            --bad-text: #9f1239;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at top left, rgba(21,128,61,0.1), transparent 28%),
                linear-gradient(180deg, rgba(255,251,235,0.96), rgba(247,243,235,1));
            min-height: 100vh;
        }

        .wrap {
            max-width: 1180px;
            margin: 0 auto;
            padding: 32px 20px 48px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            gap: 18px;
            align-items: center;
            margin-bottom: 28px;
        }

        .eyebrow {
            margin: 0 0 8px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.26em;
            text-transform: uppercase;
            color: var(--soft);
        }

        h1 {
            margin: 0;
            font-size: clamp(2rem, 5vw, 3.4rem);
            line-height: 0.96;
            letter-spacing: -0.04em;
        }

        .lede {
            max-width: 720px;
            margin: 12px 0 0;
            color: var(--muted);
            font-size: 1rem;
            line-height: 1.65;
        }

        .nav-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            color: var(--text);
            border-bottom: 1px solid var(--border);
            padding: 6px 0;
            font-weight: 600;
        }

        .grid {
            display: grid;
            grid-template-columns: 1.05fr 0.95fr;
            gap: 22px;
        }

        .panel {
            border: 1px solid var(--border);
            background: var(--panel);
            backdrop-filter: blur(14px);
            border-radius: 24px;
            padding: 24px;
            box-shadow: 0 24px 60px rgba(28,25,23,0.08);
        }

        .section-title {
            margin: 0;
            font-size: clamp(1.8rem, 3vw, 2.7rem);
            line-height: 1;
            letter-spacing: -0.04em;
        }

        .release-panel {
            display: grid;
            gap: 20px;
            margin-bottom: 24px;
        }

        .release-head {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 22px;
            align-items: start;
        }

        .release-copy {
            display: grid;
            gap: 12px;
        }

        .license-head {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 18px;
            align-items: start;
            margin-bottom: 14px;
        }

        .release-message {
            max-width: 840px;
            color: var(--muted);
            line-height: 1.65;
            margin: 0;
        }

        .release-actions,
        .form-actions {
            display: flex;
            gap: 22px;
            flex-wrap: wrap;
            align-items: center;
        }

        .release-actions form,
        .form-actions form {
            margin: 0;
        }

        .status-band {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            padding: 0;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            margin-bottom: 18px;
        }

        .release-head .status-band {
            margin-top: 4px;
        }

        .release-panel .stats {
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 0;
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
            padding: 5px 0;
        }

        .release-panel .stat {
            border: 0;
            border-radius: 0;
            background: transparent;
            padding: 13px 18px 13px 0;
        }

        .release-panel .stat + .stat {
            border-left: 1px solid var(--border);
            padding-left: 18px;
        }

        .status-band::before {
            content: '';
            width: 9px;
            height: 9px;
            border-radius: 999px;
            background: currentColor;
            box-shadow: 0 0 0 5px color-mix(in srgb, currentColor 13%, transparent);
        }

        .status-band.active, .status-band.current { color: #166534; }
        .status-band.grace { color: var(--warn-text); }
        .status-band.locked, .status-band.invalid, .status-band.pending { color: var(--bad-text); }

        .stats {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
            margin-top: 18px;
        }

        .stat {
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 16px;
            background: rgba(255,255,255,0.72);
        }

        .stat-label {
            margin: 0 0 8px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: var(--soft);
        }

        .stat-value {
            margin: 0;
            font-size: 1.05rem;
            font-weight: 700;
        }

        .message {
            margin: 0;
            padding: 16px 18px;
            border-radius: 18px;
            border: 1px solid var(--border);
            background: rgba(255,255,255,0.74);
            color: var(--muted);
            line-height: 1.6;
        }

        .license-panel {
            display: grid;
            align-content: start;
            gap: 18px;
        }

        .license-panel .status-band,
        .license-panel .message,
        .license-panel .stats,
        .license-panel .feature-list,
        .license-panel form {
            margin: 0;
        }

        .license-panel .message {
            border: 0;
            border-radius: 0;
            background: transparent;
            padding: 0;
        }

        .license-panel .stats {
            gap: 0 26px;
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
            padding: 4px 0;
        }

        .license-panel .stat {
            border: 0;
            border-radius: 0;
            background: transparent;
            padding: 13px 0;
        }

        .license-panel .feature-list {
            display: grid;
            gap: 0;
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
            padding: 2px 0;
        }

        .license-panel .feature-list li {
            display: grid;
            grid-template-columns: 1fr auto;
            align-items: center;
            gap: 18px;
            min-height: 46px;
            border: 0;
            border-bottom: 1px solid var(--border);
            border-radius: 0;
            background: transparent;
            padding: 12px 0;
            justify-content: stretch;
        }

        .license-panel .feature-list li:last-child {
            border-bottom: 0;
        }

        .license-panel label {
            gap: 10px;
        }

        .license-panel input {
            border: 0;
            border-bottom: 1px solid var(--border);
            border-radius: 0;
            background: transparent;
            padding: 12px 0;
        }

        .license-panel .profile-ledger {
            display: grid;
            gap: 0;
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
            padding: 2px 0;
        }

        .license-panel .profile-ledger .renewal-line {
            display: grid;
            grid-template-columns: minmax(120px, 0.36fr) 1fr;
            align-items: center;
            gap: 18px;
            border: 0;
            border-bottom: 1px solid var(--border);
            border-radius: 0;
            background: transparent;
            padding: 12px 0;
        }

        .license-panel .profile-ledger .renewal-line:last-child {
            border-bottom: 0;
        }

        .license-panel .profile-ledger .renewal-line strong {
            margin: 0;
        }

        .license-panel .profile-ledger .renewal-line div {
            min-width: 0;
            overflow-wrap: anywhere;
        }

        .section-kicker {
            margin: 0;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.22em;
            text-transform: uppercase;
            color: var(--soft);
        }

        .feature-list {
            display: grid;
            gap: 10px;
            margin: 18px 0 0;
            padding: 0;
            list-style: none;
        }

        .feature-list li {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            border: 1px solid var(--border);
            background: rgba(255,255,255,0.72);
            border-radius: 14px;
            padding: 12px 14px;
        }

        .feature-state {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 0;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .feature-state::before {
            content: '';
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: currentColor;
        }

        .feature-state.on { color: #166534; }
        .feature-state.off { color: #991b1b; }

        form {
            display: grid;
            gap: 14px;
            margin-top: 18px;
        }

        label {
            display: grid;
            gap: 8px;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--soft);
        }

        input {
            width: 100%;
            border: 1px solid var(--border);
            background: white;
            border-radius: 18px;
            padding: 16px 18px;
            font: inherit;
            color: var(--text);
        }

        .row {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
        }

        .icon-action {
            appearance: none;
            display: inline-grid;
            grid-template-columns: 24px auto;
            align-items: center;
            gap: 9px;
            min-height: 36px;
            border: 0;
            padding: 0;
            background: transparent;
            color: var(--text);
            font: inherit;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            line-height: 1.2;
        }

        .icon-action.primary {
            color: var(--accent-ink);
        }

        .icon-action svg {
            width: 22px;
            height: 22px;
            stroke: currentColor;
            stroke-width: 1.9;
            fill: none;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .icon-action:hover span:last-child {
            text-decoration: underline;
            text-underline-offset: 4px;
        }

        .icon-action:focus-visible {
            outline: 2px solid color-mix(in srgb, var(--accent) 55%, transparent);
            outline-offset: 6px;
        }

        .changelog {
            border-top: 1px solid var(--border);
            padding-top: 16px;
        }

        .changelog summary {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            list-style: none;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: var(--soft);
        }

        .changelog summary::-webkit-details-marker {
            display: none;
        }

        .changelog-arrow {
            display: inline-grid;
            place-items: center;
            width: 18px;
            height: 18px;
            color: var(--text);
            font-size: 16px;
            transition: transform 0.16s ease;
        }

        .changelog[open] > summary .changelog-arrow {
            transform: rotate(90deg);
        }

        .changelog-count {
            margin-left: auto;
            color: var(--muted);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0;
            text-transform: none;
        }

        .changelog-list {
            display: grid;
            gap: 10px;
            margin: 16px 0 0;
            padding: 0;
            list-style: none;
        }

        .changelog-list li {
            position: relative;
            padding-left: 20px;
            color: var(--muted);
            line-height: 1.55;
        }

        .changelog-list li::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0.7em;
            width: 7px;
            height: 7px;
            border-radius: 999px;
            background: var(--accent);
        }

        .changelog-body {
            display: grid;
            gap: 10px;
            margin-top: 16px;
        }

        .changelog-version {
            border-top: 1px solid var(--border);
            padding-top: 12px;
        }

        .changelog-version:first-child {
            border-top: 0;
            padding-top: 0;
        }

        .changelog-version summary {
            letter-spacing: 0.14em;
            color: var(--text);
        }

        .changelog-version .changelog-arrow {
            color: var(--soft);
        }

        .changelog-version[open] > summary .changelog-arrow {
            transform: rotate(90deg);
        }

        .changelog-version-label {
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .changelog-latest {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: var(--accent-ink);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.14em;
            text-transform: uppercase;
        }

        .changelog-latest::before {
            content: '';
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: currentColor;
        }

        .form-note,
        .muted {
            color: var(--muted);
            font-size: 14px;
            line-height: 1.6;
        }

        .errors, .flash {
            border-radius: 18px;
            padding: 14px 16px;
            margin-bottom: 16px;
        }

        .errors {
            background: var(--bad-bg);
            border: 1px solid var(--bad-border);
            color: var(--bad-text);
        }

        .flash {
            background: var(--warn-bg);
            border: 1px solid var(--warn-border);
            color: var(--warn-text);
        }

        .renewal-card {
            display: grid;
            grid-template-columns: 1fr;
            gap: 18px;
            align-items: start;
            justify-items: center;
        }

        .renewal-panel {
            align-content: start;
        }

        .qr-shell {
            width: min(100%, 520px);
            border-radius: 22px;
            border: 1px solid var(--border);
            background: rgba(255,255,255,0.8);
            padding: 20px;
            text-align: center;
        }

        .qr-shell img {
            width: 100%;
            max-width: 340px;
            height: auto;
            display: block;
            margin: 0 auto;
            border-radius: 14px;
            background: white;
        }

        .renewal-meta {
            width: 100%;
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0 28px;
            border-top: 1px solid var(--border);
            padding-top: 5px;
        }

        .renewal-line {
            border: 0;
            border-bottom: 1px solid var(--border);
            border-radius: 0;
            background: transparent;
            padding: 14px 0;
        }

        .renewal-line strong {
            display: block;
            margin-bottom: 4px;
            font-size: 12px;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--soft);
        }

        @media (max-width: 980px) {
            .grid,
            .renewal-card,
            .release-head,
            .license-head,
            .release-panel .stats,
            .renewal-meta {
                grid-template-columns: 1fr;
            }

            .release-panel .stat + .stat {
                border-left: 0;
                padding-left: 0;
            }

            .topbar,
            .row,
            .stats {
                grid-template-columns: 1fr;
            }

            .topbar {
                display: grid;
            }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="topbar">
            <div>
                <p class="eyebrow">Office license</p>
                <h1>License, renewal, and companion access.</h1>
                <p class="lede">This is the lane to re-check the saved office key, paste a replacement key, unlock an expired install, or renew access before the browser companion and workspace features go dark.</p>
            </div>
            <a href="/dashboard" class="nav-link">Back to dashboard</a>
        </div>

        @if ($errors->any())
            <div class="errors">{{ $errors->first() }}</div>
        @endif

        @if (session('toast'))
            <div class="flash">{{ session('toast.message') }}</div>
        @endif

        <div class="panel release-panel">
            <div class="release-head">
                <div class="release-copy">
                    <p class="eyebrow">Office updates</p>
                    <h2 class="section-title">
                        {{ !empty($updates['update_available']) ? 'Install the latest CreditSoft build.' : 'CreditSoft is on the current build.' }}
                    </h2>
                    <p class="release-message">{{ $updates['summary'] ?? 'The office can check the remote update lane for the next package.' }}</p>
                </div>
                <div class="status-band {{ !empty($updates['update_available']) ? 'active' : 'current' }}">
                    {{ !empty($updates['update_available']) ? 'Update available' : 'Up to date' }}
                    @if (!empty($updates['latest_version']))
                        <span>Latest {{ $updates['latest_version'] }}</span>
                    @endif
                    @if (!empty($updates['local_build_ahead']) && !empty($updates['published_latest_version']))
                        <span>Published feed {{ $updates['published_latest_version'] }}</span>
                    @endif
                </div>
            </div>

            <div class="release-actions">
                @if (!empty($updates['update_available']))
                    <form method="POST" action="{{ route('internal.updates.apply') }}" target="_top">
                        @csrf
                        <button type="submit" class="icon-action primary">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12"></path><path d="m7 10 5 5 5-5"></path><path d="M5 21h14"></path></svg>
                            <span>Apply update</span>
                        </button>
                    </form>
                @endif
                <form method="POST" action="{{ route('internal.updates.check') }}" target="_top">
                    @csrf
                    <button type="submit" class="icon-action">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 11a8 8 0 0 0-14.6-4.5"></path><path d="M4 5v5h5"></path><path d="M4 13a8 8 0 0 0 14.6 4.5"></path><path d="M20 19v-5h-5"></path></svg>
                        <span>Check for updates</span>
                    </button>
                </form>
                @if (!empty($updates['download_url']))
                    <a href="{{ route('internal.updates.download') }}" class="icon-action">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v10"></path><path d="m8 10 4 4 4-4"></path><path d="M5 20h14"></path></svg>
                        <span>Download package</span>
                    </a>
                @endif
                @if (!empty($updates['renewal_url']))
                    <a href="{{ $updates['renewal_url'] }}" class="icon-action">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 7h8"></path><path d="M8 12h8"></path><path d="M8 17h5"></path><path d="M5 3h14v18H5z"></path></svg>
                        <span>Open renewal lane</span>
                    </a>
                @endif
                @if (!empty($updates['support_url']))
                    <a href="{{ $updates['support_url'] }}" class="icon-action">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"></path></svg>
                        <span>Contact support</span>
                    </a>
                @endif
            </div>

            <div class="stats">
                <div class="stat">
                    <p class="stat-label">Current version</p>
                    <p class="stat-value">{{ $updates['current_version'] ?? 'Unknown' }}</p>
                </div>
                <div class="stat">
                    <p class="stat-label">Latest version</p>
                    <p class="stat-value">{{ $updates['latest_version'] ?? 'Unknown' }}</p>
                </div>
                <div class="stat">
                    <p class="stat-label">Latest build</p>
                    <p class="stat-value">{{ $updates['latest_build'] ?? 'Not set' }}</p>
                </div>
                <div class="stat">
                    <p class="stat-label">{{ !empty($updates['local_build_ahead']) ? 'Published feed' : 'Published' }}</p>
                    <p class="stat-value">
                        @if (!empty($updates['local_build_ahead']) && !empty($updates['published_latest_version']))
                            {{ $updates['published_latest_version'] }}
                        @else
                            {{ !empty($updates['published_at']) ? \Illuminate\Support\Carbon::parse($updates['published_at'])->timezone(config('app.timezone'))->format('M j, Y g:i A') : 'Not published yet' }}
                        @endif
                    </p>
                </div>
            </div>
            @if (!empty($changelog['versions']))
                <details class="changelog">
                    <summary>
                        <span class="changelog-arrow" aria-hidden="true">&gt;</span>
                        <span>Changelog</span>
                        <span class="changelog-count">{{ count($changelog['versions']) }} versions · {{ $changelog['total'] }} updates</span>
                    </summary>
                    <div class="changelog-body">
                        @foreach ($changelog['versions'] as $index => $release)
                            <details class="changelog-version" @if ($index === 0) open @endif>
                                <summary>
                                    <span class="changelog-arrow" aria-hidden="true">&gt;</span>
                                    <span class="changelog-version-label">
                                        Version {{ $release['version'] }}
                                        @if ($index === 0)
                                            <span class="changelog-latest">Latest changes</span>
                                        @endif
                                    </span>
                                    <span class="changelog-count">{{ count($release['notes']) }} updates</span>
                                </summary>
                                <ul class="changelog-list">
                                    @foreach ($release['notes'] as $note)
                                        <li>{{ $note }}</li>
                                    @endforeach
                                </ul>
                            </details>
                        @endforeach
                    </div>
                </details>
            @endif
        </div>

        <div class="grid">
            <section class="panel license-panel">
                <div class="license-head">
                    <div>
                        <p class="eyebrow">Office access</p>
                        <h2 class="section-title">{{ $license['plan_label'] ?? $license['plan'] ?? 'License status' }}</h2>
                    </div>
                    <div class="status-band {{ e((string) ($license['access_state'] ?? 'pending')) }}">
                        {{ strtoupper((string) ($license['access_state'] ?? 'pending')) }}
                        @if (!empty($license['countdown_label']))
                            <span>{{ $license['countdown_label'] }}</span>
                        @endif
                    </div>
                </div>

                <p class="message">{{ $license['message'] ?? 'License status is waiting for the next check.' }}</p>

                <div class="stats">
                    <div class="stat">
                        <p class="stat-label">Plan</p>
                        <p class="stat-value">{{ $license['plan_label'] ?? $license['plan'] ?? 'Not assigned yet' }}</p>
                    </div>
                    <div class="stat">
                        <p class="stat-label">Saved key</p>
                        <p class="stat-value">{{ $license['masked_key'] ?? ($savedLicenseKey ? 'Stored locally' : 'Not saved') }}</p>
                    </div>
                    <div class="stat">
                        <p class="stat-label">Checked</p>
                        <p class="stat-value">{{ !empty($license['checked_at']) ? \Illuminate\Support\Carbon::parse($license['checked_at'])->timezone(config('app.timezone'))->format('M j, Y g:i A') : 'Not checked yet' }}</p>
                    </div>
                    <div class="stat">
                        <p class="stat-label">Expires</p>
                        <p class="stat-value">{{ $license['expires_label'] ?? $license['grace_ends_label'] ?? 'No date saved' }}</p>
                    </div>
                </div>

                <p class="section-kicker">Included access</p>
                <ul class="feature-list">
                    @foreach (($license['features'] ?? []) as $feature => $enabled)
                        <li>
                            <span>{{ config("creditsoft.licensing.features.{$feature}.label", str($feature)->replace('_', ' ')->title()) }}</span>
                            <span class="feature-state {{ $enabled ? 'on' : 'off' }}">{{ $enabled ? 'Included' : 'Not included' }}</span>
                        </li>
                    @endforeach
                </ul>

                <form method="POST" action="{{ route('settings.license.update') }}" target="_top">
                    @csrf
                    <label>
                        Office license key
                        <input
                            type="text"
                            name="license_key"
                            value="{{ old('license_key') }}"
                            placeholder="{{ $savedLicenseKey ? 'Leave blank to re-check saved key.' : 'Paste office key to activate.' }}"
                            autocomplete="off"
                        >
                    </label>

                    <p class="section-kicker">Office profile</p>
                    <div class="profile-ledger">
                        <div class="renewal-line">
                            <strong>Company</strong>
                            <div>{{ $officeProfile['company_name'] ?: 'CreditSoft office' }}</div>
                        </div>
                        <div class="renewal-line">
                            <strong>Admin email</strong>
                            <div>{{ $officeProfile['admin_email'] ?: 'Not saved yet' }}</div>
                        </div>
                        <div class="renewal-line">
                            <strong>Tailscale host</strong>
                            <div>{{ $officeProfile['tailscale_hostname'] ?: 'Not saved yet' }}</div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="icon-action primary">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6z"></path><path d="m9 12 2 2 4-5"></path></svg>
                            <span>Check license</span>
                        </button>
                        <a href="/browser-companion/download" class="icon-action">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 18h6"></path><path d="M10 22h4"></path><path d="M5 3h14v12H5z"></path></svg>
                            <span>Try browser companion download</span>
                        </a>
                        <a href="https://www.creditsoft.app/renewal/" class="icon-action">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 7h8"></path><path d="M8 12h8"></path><path d="M8 17h5"></path><path d="M5 3h14v18H5z"></path></svg>
                            <span>Open renewal page</span>
                        </a>
                    </div>
                    <p class="form-note">If the office is unlicensed or the browser companion is not included in the current plan, the companion lane will send you back here with the exact reason.</p>
                </form>
            </section>

            <section class="panel renewal-panel">
                <p class="eyebrow" style="display:flex; align-items:center; gap:10px;">
                    <img src="/assets/vendor-logos/zelle.svg" alt="Zelle" style="height:22px; width:auto;">
                    <span>Renew with Zelle</span>
                </p>
                <div class="renewal-card">
                    <div class="qr-shell">
                        @if (!empty($renewal['qr_image_url']) || !empty($renewal['qr_data_uri']))
                            <img src="{{ $renewal['qr_image_url'] ?? $renewal['qr_data_uri'] }}" alt="CreditSoft renewal Zelle QR code">
                            <p class="muted" style="margin:14px auto 0; max-width:340px;">{{ $renewal['pricing_placeholder_note'] ?? 'Scan the Zelle QR, then use the email address on your CreditSoft account/license as the memo for faster processing.' }}</p>
                        @else
                            <div class="muted">QR preview unavailable right now.</div>
                        @endif
                    </div>
                    <div class="renewal-meta">
                        @if (!empty($renewal['base_amount_label']))
                            <div class="renewal-line">
                                <strong>License price</strong>
                                <div>{{ $renewal['base_amount_label'] }}/{{ $renewal['pricing_interval_label'] ?? 'month' }}</div>
                            </div>
                            <div class="renewal-line">
                                <strong>Zelle discount</strong>
                                <div>{{ $renewal['discount_percent'] ?? 10 }}% off @if (!empty($renewal['discount_amount_label'])) (-{{ $renewal['discount_amount_label'] }}) @endif</div>
                            </div>
                        @endif
                        <div class="renewal-line">
                            <strong>Payee</strong>
                            <div>{{ $renewal['payee_name'] ?: 'CreditSoft billing' }}</div>
                        </div>
                        <div class="renewal-line">
                            <strong>Bank</strong>
                            <div>{{ $renewal['bank_name'] ?: 'Confirm bank in config' }}</div>
                        </div>
                        <div class="renewal-line">
                            <strong>Zelle destination</strong>
                            <div>{{ $renewal['zelle_contact'] ?: 'Add a Zelle destination in config.' }}</div>
                        </div>
                        <div class="renewal-line">
                            <strong>Memo</strong>
                            <div>{{ $renewal['memo'] ?: 'CreditSoft renewal' }}</div>
                        </div>
                        @if (!empty($renewal['amount']))
                            <div class="renewal-line">
                                <strong>Zelle total</strong>
                                <div>{{ $renewal['amount'] }}</div>
                            </div>
                        @endif
                        <div class="renewal-line">
                            <strong>Processing note</strong>
                            <div>{{ $renewal['payment_note'] }}</div>
                        </div>
                        <div class="renewal-line">
                            <strong>Email subject to trust</strong>
                            <div>{{ $renewal['expected_subject'] ?? 'You received money with Zelle®' }}</div>
                        </div>
                        <div class="renewal-line">
                            <strong>Dedicated inbox rule</strong>
                            <div>{{ $renewal['dedicated_mailbox_note'] ?? 'Use this inbox for Zelle payment notices only.' }}</div>
                        </div>
                        <div class="renewal-line">
                            <strong>Zelle email rule</strong>
                            <div>{{ $renewal['zelle_address_note'] ?? 'Use the bank-approved Zelle email. Some banks reject zelle@ addresses.' }}</div>
                        </div>
                        <div class="renewal-line">
                            <strong>Need help</strong>
                            <div>
                                {{ $renewal['support_email'] ?: 'hello@creditsoft.app' }}
                                @if (!empty($renewal['support_phone']))
                                    <br>{{ $renewal['support_phone'] }}
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                <p class="lede" style="margin-top:18px; max-width:none;">Use this page when the office license expires, when a new key is issued, or when someone needs the browser companion back online. Renewal processing can take up to 8 hours, so it is normal for the office to stay pending for a bit after payment lands.</p>
            </section>
        </div>
    </div>
</body>
</html>
