<?php
declare(strict_types=1);

$page_title = 'Resources';
$page_description = 'Browse the CreditSoft guides, demos, product pages, pricing, migration, and client portal information in one organized place.';
$page_hero = true;
$hero_title = 'CreditSoft resource center.';
$hero_subtitle = 'Start with the pages that answer the real buying questions: what the product does, what it costs, how migration works, and what customers see.';

$resource_groups = [
    [
        'label' => 'Start here',
        'items' => [
            [
                'title' => 'Features',
                'href' => '/features',
                'snippet' => 'See the actual product screens, workflow, and what the software looks like today.',
            ],
            [
                'title' => 'Pricing',
                'href' => '/pricing',
                'snippet' => 'See the software plans, annual pricing, and multi-office license path.',
            ],
            [
                'title' => 'Migration',
                'href' => '/migration',
                'snippet' => 'See how a company can move from another platform with a practical migration plan.',
            ],
            [
                'title' => 'Client Portal',
                'href' => '/client-portal',
                'snippet' => 'See what customers actually log into, upload through, and read.',
            ],
            [
                'title' => 'Website API Bridge',
                'href' => '/api-bridge',
                'snippet' => 'See how public domains carry Meta callbacks, portal reads, lead intake, and WordPress integration without exposing localhost.',
            ],
        ],
    ],
    [
        'label' => 'Product depth',
        'items' => [
            [
                'title' => 'Metro2 Review',
                'href' => '/metro2',
                'snippet' => 'Browse the Metro2 code catalog and the reporting patterns CreditSoft helps review.',
            ],
            [
                'title' => 'Compliance',
                'href' => '/compliance',
                'snippet' => 'See how compliance context can stay close to the work instead of sitting outside the system.',
            ],
            [
                'title' => 'Disputes',
                'href' => '/disputes',
                'snippet' => 'See how report review, letters, and next actions stay tied to the same file.',
            ],
            [
                'title' => 'Reporting',
                'href' => '/reporting',
                'snippet' => 'See cycle comparison and progress reporting built around the client record.',
            ],
            [
                'title' => 'Built-In Automation',
                'href' => '/built-in-automation',
                'snippet' => 'See native automation options designed to reduce extra connector tools.',
            ],
            [
                'title' => 'Security',
                'href' => '/security',
                'snippet' => 'See how the local-first model changes the data and hosting story.',
            ],
            [
                'title' => 'Requirements',
                'href' => '/requirements',
                'snippet' => 'See hardware, storage, and system guidance for a CreditSoft installation.',
            ],
            [
                'title' => 'Videos',
                'href' => '/videos',
                'snippet' => 'Watch walkthroughs, demos, setup help, and product training.',
            ],
        ],
    ],
    [
        'label' => 'Business guides',
        'items' => [
            [
                'title' => 'Quiz',
                'href' => '/quiz',
                'snippet' => 'Use fit-check and educational quizzes to choose the next best page.',
            ],
            [
                'title' => 'Start Repairing Credit',
                'href' => '/start-repairing-credit',
                'snippet' => 'See a beginner-friendly path for understanding credit repair workflow.',
            ],
            [
                'title' => 'Run a Credit Repair Business',
                'href' => '/run-a-credit-repair-business',
                'snippet' => 'See how the business side can stay organized around clients, tasks, billing, and follow-up.',
            ],
            [
                'title' => 'Scale Your Credit Repair Business',
                'href' => '/scale-your-credit-repair-business',
                'snippet' => 'See how growth can tighten operations instead of adding disconnected tools.',
            ],
            [
                'title' => 'Outsourcing',
                'href' => '/outsourcing',
                'snippet' => 'See where outsourcing can help and where better workflows may reduce dependency.',
            ],
            [
                'title' => 'Options Roadmap',
                'href' => '/options',
                'snippet' => 'See communications, fulfillment, and company support options on the product roadmap.',
            ],
            [
                'title' => 'Roadmap',
                'href' => '/roadmap',
                'snippet' => 'See what is available now, what is in progress, and what is still on deck.',
            ],
            [
                'title' => 'Managed Websites',
                'href' => '/websites',
                'snippet' => 'See how branded websites tie back into intake, portal access, and the rest of the CreditSoft setup.',
            ],
        ],
    ],
];

require __DIR__ . '/header.php';
?>
<style>
    .resources-wrap { max-width: 1160px; margin: 0 auto; padding: 46px 20px 0; display: grid; gap: 28px; }
    .resources-intro {
        display: grid;
        grid-template-columns: minmax(0, 1.1fr) minmax(320px, 0.9fr);
        gap: 24px;
        align-items: start;
    }
    .resources-panel,
    .resources-note,
    .resources-group {
        background: white;
        border: 1px solid var(--border);
        border-radius: 24px;
        box-shadow: 0 18px 42px rgba(15,23,42,.06);
    }
    .resources-panel {
        padding: 30px 28px;
        display: grid;
        gap: 16px;
    }
    .resources-kicker {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .1em;
        text-transform: uppercase;
        background: #dbeafe;
        color: #1d4ed8;
    }
    .resources-panel h2 { font-size: 34px; line-height: 1.08; margin: 0; }
    .resources-panel p { color: var(--gray); margin: 0; font-size: 17px; }
    .resources-points {
        list-style: none;
        margin: 0;
        padding: 0;
        display: grid;
        gap: 12px;
    }
    .resources-points li {
        padding-left: 20px;
        position: relative;
        color: var(--dark);
    }
    .resources-points li::before {
        content: '';
        position: absolute;
        left: 0;
        top: 11px;
        width: 8px;
        height: 8px;
        border-radius: 999px;
        background: var(--success);
    }
    .resources-note {
        padding: 26px 24px;
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        color: white;
    }
    .resources-note h3 { font-size: 22px; margin: 0 0 10px; }
    .resources-note p { color: rgba(255,255,255,.82); margin: 0 0 12px; }
    .resources-note .mini { font-size: 13px; color: rgba(255,255,255,.68); }
    .resources-group {
        padding: 26px 24px;
        display: grid;
        gap: 20px;
    }
    .resources-group__header {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
    }
    .resources-group__header h2 { font-size: 28px; margin: 0; }
    .resources-group__header span {
        color: var(--gray);
        font-size: 13px;
        font-weight: 700;
        letter-spacing: .12em;
        text-transform: uppercase;
    }
    .resources-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px;
    }
    .resource-card {
        display: grid;
        gap: 10px;
        padding: 20px 20px 18px;
        border-radius: 20px;
        border: 1px solid var(--border);
        background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
        color: inherit;
        text-decoration: none;
        transition: transform .18s, border-color .18s, box-shadow .18s;
    }
    .resource-card:hover {
        transform: translateY(-3px);
        border-color: #93c5fd;
        box-shadow: 0 18px 36px rgba(37,99,235,.09);
        text-decoration: none;
    }
    .resource-card strong {
        font-size: 20px;
        line-height: 1.15;
        color: var(--dark);
    }
    .resource-card span {
        color: var(--gray);
        line-height: 1.6;
    }
    .resource-card em {
        color: #1d4ed8;
        font-style: normal;
        font-weight: 700;
        font-size: 14px;
    }
    @media (max-width: 920px) {
        .resources-intro,
        .resources-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="resources-wrap">
    <section class="resources-intro">
        <div class="resources-panel">
            <span class="resources-kicker">Resources hub</span>
            <h2>Find the right page without guessing.</h2>
            <p>This page organizes the main CreditSoft product pages, buyer guides, demos, and setup information so a company can move from overview to decision without bouncing around the site.</p>
            <ul class="resources-points">
                <li>Start here when comparing CreditSoft, checking product fit, or sending someone a clear overview of the system.</li>
                <li>Each link includes a short summary so the next click is obvious before someone leaves the page.</li>
                <li>The goal is simple: show what is live, what it costs, how setup works, and where the deeper guides live.</li>
            </ul>
        </div>
        <aside class="resources-note">
            <h3>Start here first</h3>
            <p>New to CreditSoft? Open Features, Pricing, Migration, and Client Portal first. Those four pages explain the product, the cost, the move-over process, and the customer-facing experience.</p>
            <p class="mini">After that, use the deeper guides when you want details on compliance, reporting, automation, security, websites, and business growth.</p>
        </aside>
    </section>

    <?php foreach ($resource_groups as $group): ?>
        <section class="resources-group">
            <div class="resources-group__header">
                <h2><?= htmlspecialchars((string) $group['label'], ENT_QUOTES, 'UTF-8') ?></h2>
                <span><?= count($group['items']) ?> pages</span>
            </div>
            <div class="resources-grid">
                <?php foreach ($group['items'] as $item): ?>
                    <a class="resource-card" href="<?= htmlspecialchars((string) $item['href'], ENT_QUOTES, 'UTF-8') ?>">
                        <em>Open page</em>
                        <strong><?= htmlspecialchars((string) $item['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                        <span><?= htmlspecialchars((string) $item['snippet'], ENT_QUOTES, 'UTF-8') ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
</div>

<?php require __DIR__ . '/shared-footer.php'; ?>
