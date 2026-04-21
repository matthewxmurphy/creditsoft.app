<?php

function creditsoft_feature_detail_pages(): array
{
    return [
        'crm' => [
            'title' => 'Local-First CRM + Office Workspace',
            'description' => 'See how CreditSoft gives credit repair organizations a local-first CRM for leads, clients, follow-up, billing signals, portal handoff, and office workflow.',
            'hero_title' => 'A CRM built for credit repair operations, not generic SaaS limits.',
            'hero_subtitle' => 'CreditSoft keeps leads, active clients, provider access, billing signals, notes, tasks, portal handoff, and staff follow-up inside the same local-first office workspace.',
            'eyebrow' => 'CRM workspace',
            'image' => '/assets/images/product-proof/crm-clients-workspace-expanded-20260419.png',
            'image_alt' => 'CreditSoft CRM clients workspace with billing and provider signals',
            'image_caption' => 'Current CRM workspace with billing health, provider access, leads, and client actions.',
            'gallery' => [
                [
                    'image' => '/assets/images/product-proof/crm-leads-workspace-expanded-20260419.png',
                    'alt' => 'CreditSoft CRM leads workspace with conversion actions',
                    'label' => 'Lead conversion',
                    'copy' => 'Lead rows stay separate until staff promote them to active clients.',
                ],
                [
                    'image' => '/assets/images/product-proof/crm-client-actions-expanded-20260419.png',
                    'alt' => 'CreditSoft CRM client action menu with fire and graduate options',
                    'label' => 'Client actions',
                    'copy' => 'Fire/archive and graduate actions keep ended relationships searchable.',
                ],
                [
                    'image' => '/assets/images/product-proof/crm-add-client-expanded-20260419.png',
                    'alt' => 'CreditSoft CRM add client dialog with autosave-ready fields',
                    'label' => 'Fast intake',
                    'copy' => 'Add-client intake stays light, with deeper CRM fields behind More.',
                ],
            ],
            'lead' => 'The CRM lane is where the office decides who needs attention, who is active, who is late, which provider logins need help, and what the next staff action should be. It is designed around credit repair work instead of forcing the company to pay for a separate generic CRM and glue it together.',
            'summary' => [
                'Lead, client, affiliate, fired, canceled, and graduated states can live in one searchable workspace.',
                'Billing signals, provider-login status, notes, tasks, and report workflow stay attached to the customer record.',
                'Website intake, client portal activity, browser companion capture, and staff follow-up route back into the office CRM lane.',
            ],
            'sections' => [
                [
                    'title' => 'What the CRM tracks',
                    'items' => [
                        ['label' => 'Lead-to-client flow', 'copy' => 'New intake can become a client record without retyping the same customer data.'],
                        ['label' => 'Client health signals', 'copy' => 'Payment history, late behavior, current standing, and VIP signals can follow the profile.'],
                        ['label' => 'Provider access status', 'copy' => 'SmartCredit, IdentityIQ, and other login issues can be dated, noted, and routed to staff.'],
                    ],
                ],
                [
                    'title' => 'Why it replaces more tools',
                    'items' => [
                        ['label' => 'Portal handoff', 'copy' => 'Client uploads, customer updates, and support needs come back to the same workspace.'],
                        ['label' => 'Automation without extra glue', 'copy' => 'CRM work can be queued from website, companion, Meta, Zelle, Cash App, and other lanes.'],
                        ['label' => 'Local-first ownership', 'copy' => 'The office runs on its own infrastructure, so scale is tied to hardware instead of rented contact limits.'],
                    ],
                ],
            ],
            'cta_copy' => 'This is the dedicated CRM page for buyers comparing CreditSoft against generic CRM stacks. Client system details stay separate for casework, reports, files, and letters.',
        ],
        'client-system' => [
            'title' => 'Client System + Casework',
            'description' => 'See how CreditSoft keeps client records, case notes, Metro2 review, letters, portal activity, and office follow-up tied together.',
            'hero_title' => 'Client records, casework, and follow-up in one working lane.',
            'hero_subtitle' => 'CreditSoft keeps the file, the review work, the letters, the portal activity, and staff follow-up together instead of spreading them across a CRM, folders, spreadsheets, and memory.',
            'eyebrow' => 'Client operations',
            'image' => '/assets/images/product-proof/clients-roster-expanded-20260419.png',
            'image_alt' => 'CreditSoft client roster screen',
            'lead' => 'The client system is the daily workspace. It is where the office sees who owns the file, what is due, which report work needs review, and what the customer still needs to provide.',
            'summary' => [
                'Client workspace keeps notes, status, cycles, files, letters, and provider capture tied to the same record.',
                'Metro2 and comparison review help staff find mismatches, inaccurate late payments, invalid collections, and bureau conflicts before drafting.',
                'Portal uploads and client-facing updates feed back into the office workflow instead of becoming a disconnected inbox.',
            ],
            'sections' => [
                [
                    'title' => 'What the office works from',
                    'items' => [
                        ['label' => 'Roster context', 'copy' => 'Owner, status, billing signal, provider access, and next action belong beside the client name.'],
                        ['label' => 'Case notes', 'copy' => 'Staff notes, cycle context, and review decisions stay searchable on the client timeline.'],
                        ['label' => 'Documents and files', 'copy' => 'Uploads, letter outputs, screenshots, and report captures stay attached to the right client.'],
                    ],
                ],
                [
                    'title' => 'Where automation helps',
                    'items' => [
                        ['label' => 'Provider capture', 'copy' => 'The browser companion can feed report and login status back into the same client record.'],
                        ['label' => 'Review queue', 'copy' => 'High-severity issues, missing owners, and due work surface before the file stalls.'],
                        ['label' => 'Customer follow-up', 'copy' => 'Provider-login problems can queue CRM work for staff review before any customer message is sent.'],
                    ],
                ],
            ],
            'cta_copy' => 'Use this page when you want the client workflow details. The overview page stays lighter so buyers can understand the full stack faster.',
        ],
        'email-delivery' => [
            'title' => 'SMTP + Email Delivery',
            'description' => 'CreditSoft supports Microsoft 365, Google Workspace, Amazon SES, SendGrid, Mailgun, Zoho Mail, Postmark, Brevo, SMTP.com, and custom SMTP.',
            'hero_title' => 'Email delivery belongs inside the office settings lane.',
            'hero_subtitle' => 'Configure the sender your company already trusts, keep secrets local to the installation, and queue CRM email work for staff review instead of handing notifications to another automation bill.',
            'eyebrow' => 'Email infrastructure',
            'image' => '/assets/images/product-proof/api-settings.png',
            'image_alt' => 'CreditSoft API and settings screen',
            'lead' => 'Email should be a company control, not a mystery box. CreditSoft keeps provider choices, sender identity, local relay options, and queued customer follow-up in the same settings area as the rest of the office stack.',
            'summary' => [
                'Support for common business email and transactional providers gives each office a practical sender path.',
                'The companion and CRM can flag invalid provider logins without sending customer emails directly.',
                'Local relay support lets the container send through localhost when the installation calls for it.',
            ],
            'providers' => true,
            'sections' => [
                [
                    'title' => 'Supported provider lanes',
                    'items' => [
                        ['label' => 'Business email', 'copy' => 'Microsoft 365, Google Workspace, and Zoho Mail fit offices already using hosted business mail.'],
                        ['label' => 'Transactional delivery', 'copy' => 'Amazon SES, SendGrid, Mailgun, Postmark, Brevo, and SMTP.com cover scalable system email.'],
                        ['label' => 'Custom SMTP', 'copy' => 'Any other mail server can be connected when the provider is not listed yet.'],
                    ],
                ],
                [
                    'title' => 'How it should behave',
                    'items' => [
                        ['label' => 'Staff-reviewed queue', 'copy' => 'Prepared emails sit in CRM work for review instead of firing from the companion.'],
                        ['label' => 'Saved sender lanes', 'copy' => 'More than one provider can be stored so the company can switch without rebuilding the setup.'],
                        ['label' => 'Local-first secrets', 'copy' => 'SMTP passwords and API keys belong in server settings, not public website files.'],
                    ],
                ],
            ],
            'cta_copy' => 'This is the detailed email provider page. The features overview now links here instead of trying to explain every provider inline.',
        ],
        'office-nodes' => [
            'title' => 'Multi-Office Nodes',
            'description' => 'See how CreditSoft can support multiple office nodes, local-first access, manager visibility, and routing to the best available server.',
            'hero_title' => 'Server nodes should feel like one company system.',
            'hero_subtitle' => 'CreditSoft is designed around local office nodes that can sit in different offices, stay reachable through approved private routes, and keep the same product experience for staff.',
            'eyebrow' => 'Office cluster',
            'image' => '/assets/images/product-proof/connectivity-settings.png',
            'image_alt' => 'CreditSoft connectivity settings screen',
            'lead' => 'A bigger credit repair organization should not have to run every worker through one exposed machine. The node model lets each office run close to the staff using it while still fitting into the company control layer.',
            'summary' => [
                'Each office can have a local node so staff are not dependent on one distant machine for every click.',
                'Approved access can use local routes, Tailscale, ngrok, or a public bridge depending on the installation.',
                'The product direction is one logical company system with better routing, visibility, and redundancy underneath.',
            ],
            'sections' => [
                [
                    'title' => 'Node responsibilities',
                    'items' => [
                        ['label' => 'Local office access', 'copy' => 'Workers use the closest approved office node instead of forcing every action through one public endpoint.'],
                        ['label' => 'Manager visibility', 'copy' => 'Owners and managers can be structured around the offices and staff they supervise.'],
                        ['label' => 'Health and routing', 'copy' => 'The system should show which node is serving the client and whether peers are reachable.'],
                    ],
                ],
                [
                    'title' => 'Connectivity choices',
                    'items' => [
                        ['label' => 'Local network', 'copy' => 'Same-office devices should prefer the local route when it is available.'],
                        ['label' => 'Private tailnet', 'copy' => 'Approved remote office systems can use Tailscale without requiring a public IP.'],
                        ['label' => 'Public callbacks', 'copy' => 'Website, portal, Meta, and remote callback needs can use a bridge or tunnel when public access is required.'],
                    ],
                ],
            ],
            'cta_copy' => 'This page expands the multi-office node model so the overview can stay simple for buyers scanning the product.',
        ],
        'office-backup' => [
            'title' => 'Office Backup Redundancy',
            'description' => 'CreditSoft office nodes can be designed to back each other up with queued sync, peer delivery, and recovery paths.',
            'hero_title' => 'Backups should not depend on one machine behaving forever.',
            'hero_subtitle' => 'CreditSoft is moving toward office-to-office redundancy where nodes can protect each other, queue work while peers are offline, and make recovery visible instead of hopeful.',
            'eyebrow' => 'Resilience',
            'image' => '/assets/images/product-proof/dashboard-in-browser.png',
            'image_alt' => 'CreditSoft dashboard in browser',
            'lead' => 'The backup model should be obvious to the owner: which node is primary, which node has the latest copy, what is queued, and what will happen when an offline node returns.',
            'summary' => [
                'Peer backup makes one office node a recovery path for another instead of leaving everything on one box.',
                'Queue-first delivery keeps API keys, settings, and database updates from disappearing when a server is temporarily offline.',
                'The portal should fail gracefully when every approved office route is unavailable, then retry when a lead returns.',
            ],
            'sections' => [
                [
                    'title' => 'Backup behavior',
                    'items' => [
                        ['label' => 'Peer replication', 'copy' => 'Server nodes should keep current company data available across approved office peers.'],
                        ['label' => 'Encrypted outbox', 'copy' => 'Failed delivery should be queued and retried automatically instead of being treated as a permanent failure.'],
                        ['label' => 'Recovery visibility', 'copy' => 'Owners need to see primary node, backup node, last sync, and pending queue status.'],
                    ],
                ],
                [
                    'title' => 'When systems are offline',
                    'items' => [
                        ['label' => 'Server down', 'copy' => 'Updates wait in the queue and replay when the peer is reachable again.'],
                        ['label' => 'Internet down', 'copy' => 'Local office work should continue where possible and sync when service returns.'],
                        ['label' => 'Portal unavailable', 'copy' => 'Public intake should show maintenance instead of losing customer information silently.'],
                    ],
                ],
            ],
            'cta_copy' => 'This is now its own resilience page instead of being buried in the general feature overview.',
        ],
        'tech-stack' => [
            'title' => 'Managed Tech Stack',
            'description' => 'CreditSoft is built around PostgreSQL, PHP 8.5, OPcache, Dockerized office services, public callbacks, and managed setup.',
            'hero_title' => 'The install should feel managed, not improvised.',
            'hero_subtitle' => 'CreditSoft is packaged around the stack a real office needs: PostgreSQL data, PHP 8.5, OPcache, queues, local routing, container services, and controlled public callback paths.',
            'eyebrow' => 'Installation stack',
            'image' => '/assets/images/product-proof/api-settings.png',
            'image_alt' => 'CreditSoft settings screen',
            'lead' => 'The point is not making owners become system administrators. The point is that CreditSoft can be installed, updated, backed up, and connected as a business system when access is provided.',
            'summary' => [
                'PostgreSQL gives the intranet a stronger base for multi-node, CRM, billing, and reporting workloads.',
                'PHP 8.5 and OPcache are part of the expected runtime direction for the office stack.',
                'Dockerized services give the installer a repeatable path for intranet, queue, scheduler, router, database, and CRM sidecar pieces.',
            ],
            'sections' => [
                [
                    'title' => 'Core runtime',
                    'items' => [
                        ['label' => 'PostgreSQL', 'copy' => 'The data layer is aimed at stronger multi-node and reporting behavior than local file databases.'],
                        ['label' => 'PHP 8.5', 'copy' => 'The server runtime should match the modern PHP direction with OPcache enabled.'],
                        ['label' => 'Queues and scheduler', 'copy' => 'Background sync, email prep, backups, and automation need workers, not fragile page-load hacks.'],
                    ],
                ],
                [
                    'title' => 'Managed installation',
                    'items' => [
                        ['label' => 'Container services', 'copy' => 'The office stack groups intranet, router, queue, scheduler, database, and CRM services.'],
                        ['label' => 'Public bridge', 'copy' => 'Website callbacks and portal traffic use approved public routes without exposing private casework.'],
                        ['label' => 'Supportable setup', 'copy' => 'The installer should make repeatable choices so AnyDesk-style installs do not depend on AI help.'],
                    ],
                ],
            ],
            'cta_copy' => 'This page holds the technical setup detail so the features page can stay a clean buyer overview.',
        ],
    ];
}

function creditsoft_feature_detail_page(string $slug): ?array
{
    $pages = creditsoft_feature_detail_pages();

    return $pages[$slug] ?? null;
}

function creditsoft_email_provider_cards(): array
{
    return [
        ['label' => 'Microsoft 365', 'logo' => '/assets/images/email-providers/microsoft-365.svg'],
        ['label' => 'Google Workspace', 'logo' => '/assets/images/email-providers/google-workspace.svg'],
        ['label' => 'Amazon SES', 'logo' => '/assets/images/email-providers/amazon-ses.svg'],
        ['label' => 'SendGrid', 'logo' => '/assets/images/email-providers/sendgrid.svg'],
        ['label' => 'Mailgun', 'logo' => '/assets/images/email-providers/mailgun.svg'],
        ['label' => 'Zoho Mail', 'logo' => '/assets/images/email-providers/zoho-mail.svg'],
        ['label' => 'Postmark', 'logo' => '/assets/images/email-providers/postmark.svg'],
        ['label' => 'Brevo', 'logo' => '/assets/images/email-providers/brevo.svg'],
        ['label' => 'SMTP.com', 'logo' => '/assets/images/email-providers/smtp-com.svg'],
        ['label' => 'Custom SMTP', 'logo' => '/assets/images/email-providers/smtp.svg'],
    ];
}
