<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title }}</title>
        <meta name="description" content="{{ $description }}">
        @vite(['resources/js/swagger.js'])
        <style>
            :root {
                color-scheme: light;
                --api-bg: #f5f0e6;
                --api-surface: rgba(255, 255, 255, 0.86);
                --api-surface-strong: rgba(255, 255, 255, 0.94);
                --api-border: rgba(28, 25, 23, 0.12);
                --api-copy: #1c1917;
                --api-muted: #57534e;
                --api-accent: #31c214;
                --api-accent-deep: #15803d;
            }

            * {
                box-sizing: border-box;
            }

            html, body {
                min-height: 100%;
                margin: 0;
                background:
                    radial-gradient(circle at top left, rgba(49, 194, 20, 0.1), transparent 28rem),
                    radial-gradient(circle at top right, rgba(251, 191, 36, 0.1), transparent 24rem),
                    var(--api-bg);
                color: var(--api-copy);
                font-family: "Instrument Sans", ui-sans-serif, system-ui, sans-serif;
            }

            body {
                padding: 2rem;
            }

            .shell {
                max-width: 1420px;
                margin: 0 auto;
                border: 1px solid var(--api-border);
                border-radius: 32px;
                overflow: hidden;
                background: var(--api-surface);
                box-shadow: 0 32px 90px rgba(28, 25, 23, 0.12);
                backdrop-filter: blur(18px);
            }

            .masthead {
                display: grid;
                grid-template-columns: minmax(0, 1.3fr) minmax(320px, 0.7fr);
                gap: 2rem;
                padding: 2.25rem;
                border-bottom: 1px solid var(--api-border);
                background:
                    linear-gradient(135deg, rgba(255, 255, 255, 0.75), rgba(255, 255, 255, 0.45)),
                    linear-gradient(135deg, rgba(49, 194, 20, 0.07), rgba(245, 158, 11, 0.08));
            }

            .eyebrow {
                margin: 0 0 0.85rem;
                font-size: 0.72rem;
                font-weight: 700;
                letter-spacing: 0.28em;
                text-transform: uppercase;
                color: var(--api-accent-deep);
            }

            .masthead h1 {
                margin: 0;
                font-family: Georgia, "Times New Roman", serif;
                font-size: clamp(2.5rem, 5vw, 4.2rem);
                line-height: 0.94;
                letter-spacing: -0.06em;
            }

            .masthead p {
                max-width: 48rem;
                margin: 1rem 0 0;
                font-size: 1.02rem;
                line-height: 1.72;
                color: var(--api-muted);
            }

            .meta-grid {
                display: grid;
                gap: 1rem;
            }

            .meta-card {
                border: 1px solid var(--api-border);
                border-radius: 24px;
                background: var(--api-surface-strong);
                padding: 1rem 1.1rem;
            }

            .meta-label {
                margin: 0 0 0.45rem;
                font-size: 0.7rem;
                font-weight: 700;
                letter-spacing: 0.24em;
                text-transform: uppercase;
                color: var(--api-accent-deep);
            }

            .meta-value {
                margin: 0;
                word-break: break-word;
                font-size: 0.95rem;
                line-height: 1.5;
                color: var(--api-copy);
            }

            .meta-value a {
                color: var(--api-copy);
                text-decoration-color: rgba(28, 25, 23, 0.35);
                text-underline-offset: 0.22em;
            }

            .swagger-shell {
                padding: 1rem;
            }

            #swagger-ui {
                border: 1px solid var(--api-border);
                border-radius: 28px;
                overflow: hidden;
                background: rgba(255, 255, 255, 0.92);
            }

            .swagger-ui .topbar {
                display: none;
            }

            .swagger-ui .scheme-container {
                box-shadow: none;
                background: rgba(250, 250, 249, 0.9);
            }

            .swagger-ui .wrapper {
                max-width: none;
                padding-inline: 1.5rem;
            }

            .swagger-ui .opblock-tag {
                border-bottom-color: rgba(168, 162, 158, 0.35);
            }

            .swagger-ui .btn.authorize {
                border-color: var(--api-copy);
                color: var(--api-copy);
            }

            .swagger-ui .information-container {
                padding-top: 1rem;
            }

            @media (max-width: 980px) {
                body {
                    padding: 1rem;
                }

                .masthead {
                    grid-template-columns: 1fr;
                    padding: 1.5rem;
                }
            }
        </style>
    </head>
    <body>
        <div class="shell">
            <section class="masthead">
                <div>
                    <p class="eyebrow">Swagger UI</p>
                    <h1>{{ $title }}</h1>
                    <p>{{ $description }}</p>
                </div>

                <div class="meta-grid">
                    <section class="meta-card">
                        <p class="meta-label">Base URL</p>
                        <p class="meta-value">{{ $api_base_url }}</p>
                    </section>

                    <section class="meta-card">
                        <p class="meta-label">OpenAPI spec</p>
                        <p class="meta-value"><a href="{{ $spec_url }}">{{ $spec_url }}</a></p>
                    </section>

                    <section class="meta-card">
                        <p class="meta-label">Host</p>
                        <p class="meta-value">{{ $host }}</p>
                    </section>
                </div>
            </section>

            <section class="swagger-shell">
                <div id="swagger-ui" data-spec-url="{{ $spec_url }}"></div>
            </section>
        </div>
    </body>
</html>
