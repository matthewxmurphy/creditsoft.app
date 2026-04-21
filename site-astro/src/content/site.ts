export const navLinks = [
    { href: '/features', label: 'Features' },
    { href: '/client-portal', label: 'Portal' },
    { href: '/migration', label: 'Migration' },
    { href: '/outsourcing', label: 'Outsourcing' },
    { href: '/videos', label: 'Videos' },
    { href: '/updates', label: 'Updates' },
    { href: '/pricing', label: 'Pricing' },
];

export const featureMenuItems = [
    {
        href: '/features',
        title: 'Feature overview',
        copy: 'Full product proof across CRM, portal, website, and operations.',
    },
    {
        href: '/client-system',
        title: 'Client system',
        copy: 'Client files, portal uploads, reports, letters, and follow-up.',
    },
    {
        href: '/client-portal',
        title: 'Client portal',
        copy: 'Show what customers actually see and upload.',
    },
    {
        href: '/email-delivery',
        title: 'Email providers',
        copy: 'Microsoft 365, Google Workspace, Amazon SES, SendGrid, Mailgun, Zoho Mail, Postmark, Brevo, SMTP.com, and custom SMTP.',
    },
    {
        href: '/office-nodes',
        title: 'Multi-office nodes',
        copy: 'Local installs, manager visibility, and office-to-office backup.',
    },
    {
        href: '/office-backup',
        title: 'Backup redundancy',
        copy: 'Office nodes can protect each other instead of relying on one box.',
    },
    {
        href: '/tech-stack',
        title: 'Tech stack and setup',
        copy: 'PostgreSQL, PHP 8.5, OPcache, callbacks, and handled installs.',
    },
    {
        href: '/updates',
        title: 'Update lane',
        copy: 'Office and browser companion packages live in the update system.',
    },
];

export const proofPoints = [
    {
        title: 'Intranet based',
        copy: 'The office work surface lives in a local-first lane instead of trusting the whole operation to a generic hosted CRM.',
    },
    {
        title: 'PWA ready',
        copy: 'Client-facing lanes can act like a real app without pretending every user needs a full native install path on day one.',
    },
    {
        title: 'Migration aware',
        copy: 'Sales pages, imports, updates, and browser lanes are designed to help offices move from what they already use.',
    },
    {
        title: 'Brand control',
        copy: 'The website, update server, portal, and admin surfaces can all look like one intentional product instead of four unrelated tools.',
    },
    {
        title: 'Email delivery',
        copy: 'Microsoft 365, Google Workspace, Amazon SES, SendGrid, Mailgun, Zoho Mail, Postmark, Brevo, SMTP.com, and custom SMTP lanes can be configured without hiding client notices inside another automation platform.',
    },
];

export const comparisonRows = [
    {
        label: 'Intranet based workspace',
        creditsoft: 'Built in',
        typical: 'Usually external or cloud-only',
    },
    {
        label: 'PWA support',
        creditsoft: 'Built in',
        typical: 'Rare or partial',
    },
    {
        label: 'Metro2 error detection',
        creditsoft: '30+ codes built in',
        typical: 'Basic or limited',
    },
    {
        label: '50-state CRO rules',
        creditsoft: 'Built in',
        typical: 'External or manual',
    },
    {
        label: 'SMTP/email providers',
        creditsoft:
            'Microsoft 365, Workspace, SES, SendGrid, Mailgun, Zoho, Postmark, Brevo, SMTP.com, and custom SMTP',
        typical: 'Often Zapier, manual SMTP, or another SaaS add-on',
    },
    {
        label: 'Browser companion automation',
        creditsoft: 'Available',
        typical: 'Usually missing',
    },
    {
        label: 'PII on public-facing sites',
        creditsoft: 'Never',
        typical: 'Often yes',
    },
    {
        label: 'Update server control',
        creditsoft: 'Your update lane',
        typical: 'Their server',
    },
    {
        label: 'Website and portal lane',
        creditsoft: 'Under one brand',
        typical: 'Often bolted on later',
    },
];

export const emailProviders = [
    {
        label: 'Microsoft 365',
        logo: '/branding/email-providers/microsoft-365.svg',
    },
    {
        label: 'Google Workspace',
        logo: '/branding/email-providers/google-workspace.svg',
    },
    { label: 'Amazon SES', logo: '/branding/email-providers/amazon-ses.svg' },
    { label: 'SendGrid', logo: '/branding/email-providers/sendgrid.svg' },
    { label: 'Mailgun', logo: '/branding/email-providers/mailgun.svg' },
    { label: 'Zoho Mail', logo: '/branding/email-providers/zoho-mail.svg' },
    { label: 'Postmark', logo: '/branding/email-providers/postmark.svg' },
    { label: 'Brevo', logo: '/branding/email-providers/brevo.svg' },
    { label: 'SMTP.com', logo: '/branding/email-providers/smtp-com.svg' },
    { label: 'Custom SMTP', logo: '/branding/email-providers/smtp.svg' },
];

export const productOverviewPages = [
    {
        href: '/client-system',
        title: 'Client system',
        copy: 'Client records, casework, report review, files, letters, and follow-up.',
    },
    {
        href: '/client-portal',
        title: 'Client portal',
        copy: 'Customer uploads, branded access, and status tied back to the office.',
    },
    {
        href: '/email-delivery',
        title: 'Email providers',
        copy: 'Microsoft 365, Google Workspace, SES, SendGrid, Mailgun, and more.',
    },
    {
        href: '/office-nodes',
        title: 'Multi-office nodes',
        copy: 'Local office servers, manager visibility, and approved private routes.',
    },
    {
        href: '/office-backup',
        title: 'Backup redundancy',
        copy: 'Peer backup, queued sync, and clearer recovery behavior.',
    },
    {
        href: '/tech-stack',
        title: 'Tech stack',
        copy: 'PostgreSQL, PHP 8.5, OPcache, queues, router, and managed setup.',
    },
];

export const productAreaDetails = {
    'client-system': {
        title: 'Client System + Casework',
        description:
            'See how CreditSoft keeps client records, case notes, Metro2 review, letters, portal activity, and office follow-up tied together.',
        eyebrow: 'Client operations',
        hero: 'Client records, casework, and follow-up in one working lane.',
        copy: 'The client system is the daily workspace for owners and staff: who owns the file, what is due, which report work needs review, and what the customer still needs to provide.',
        image: '/product-proof/clients-roster.png',
        sections: [
            {
                title: 'What the office works from',
                items: [
                    'Roster context with owner, status, billing signal, provider access, and next action.',
                    'Case notes, cycle context, and review decisions stay searchable on the client timeline.',
                    'Uploads, letter outputs, screenshots, and report captures stay attached to the right client.',
                ],
            },
            {
                title: 'Where automation helps',
                items: [
                    'Provider capture feeds report and login status back into the same client record.',
                    'Review queues surface high-severity issues, missing owners, and due work.',
                    'Provider-login problems can queue CRM work before any customer message is sent.',
                ],
            },
        ],
    },
    'email-delivery': {
        title: 'SMTP + Email Delivery',
        description:
            'CreditSoft supports Microsoft 365, Google Workspace, Amazon SES, SendGrid, Mailgun, Zoho Mail, Postmark, Brevo, SMTP.com, and custom SMTP.',
        eyebrow: 'Email infrastructure',
        hero: 'Email delivery belongs inside the office settings lane.',
        copy: 'Configure the sender your company already trusts, keep secrets local to the installation, and queue CRM email work for staff review.',
        image: '/product-proof/api-settings.png',
        providers: true,
        sections: [
            {
                title: 'Supported provider lanes',
                items: [
                    'Business email covers Microsoft 365, Google Workspace, and Zoho Mail.',
                    'Transactional delivery covers SES, SendGrid, Mailgun, Postmark, Brevo, and SMTP.com.',
                    'Custom SMTP connects mail servers that are not listed yet.',
                ],
            },
            {
                title: 'How it should behave',
                items: [
                    'Prepared emails sit in CRM work for staff review.',
                    'More than one provider can be stored for switching later.',
                    'SMTP passwords and API keys stay in server settings.',
                ],
            },
        ],
    },
    'office-nodes': {
        title: 'Multi-Office Nodes',
        description:
            'See how CreditSoft can support multiple office nodes, local-first access, manager visibility, and routing to the best available server.',
        eyebrow: 'Office cluster',
        hero: 'Server nodes should feel like one company system.',
        copy: 'A larger credit repair organization should not have to run every worker through one exposed machine. Each office can run close to the staff using it while fitting into the company control layer.',
        image: '/product-proof/connectivity-settings.png',
        sections: [
            {
                title: 'Node responsibilities',
                items: [
                    'Workers use the closest approved office node where possible.',
                    'Owners and managers see the offices and staff they supervise.',
                    'Health and routing show which node is serving the client.',
                ],
            },
            {
                title: 'Connectivity choices',
                items: [
                    'Same-office devices prefer the local route.',
                    'Approved remote systems can use Tailscale without a public IP.',
                    'Website and portal callbacks can use a bridge or tunnel.',
                ],
            },
        ],
    },
    'office-backup': {
        title: 'Office Backup Redundancy',
        description:
            'CreditSoft office nodes can be designed to back each other up with queued sync, peer delivery, and recovery paths.',
        eyebrow: 'Resilience',
        hero: 'Backups should not depend on one machine behaving forever.',
        copy: 'The backup model should show which node is primary, which node has the latest copy, what is queued, and what happens when an offline node returns.',
        image: '/product-proof/dashboard-in-browser.png',
        sections: [
            {
                title: 'Backup behavior',
                items: [
                    'Server nodes keep current company data available across approved peers.',
                    'Failed delivery is queued and retried automatically.',
                    'Owners can see primary node, backup node, last sync, and pending queue status.',
                ],
            },
            {
                title: 'When systems are offline',
                items: [
                    'Updates wait in the queue and replay when the peer returns.',
                    'Local office work continues where possible and syncs later.',
                    'Public intake should show maintenance instead of losing information.',
                ],
            },
        ],
    },
    'tech-stack': {
        title: 'Managed Tech Stack',
        description:
            'CreditSoft is built around PostgreSQL, PHP 8.5, OPcache, Dockerized office services, public callbacks, and managed setup.',
        eyebrow: 'Installation stack',
        hero: 'The install should feel managed, not improvised.',
        copy: 'CreditSoft is packaged around PostgreSQL data, PHP 8.5, OPcache, queues, local routing, container services, and controlled public callback paths.',
        image: '/product-proof/api-settings.png',
        sections: [
            {
                title: 'Core runtime',
                items: [
                    'PostgreSQL supports stronger multi-node and reporting behavior.',
                    'PHP 8.5 and OPcache are part of the expected runtime direction.',
                    'Queues and scheduler handle sync, email prep, backups, and automation.',
                ],
            },
            {
                title: 'Managed installation',
                items: [
                    'Container services group intranet, router, queue, database, and CRM pieces.',
                    'Website callbacks use approved public routes without exposing casework.',
                    'Repeatable installer choices reduce AnyDesk install risk.',
                ],
            },
        ],
    },
};

export const planCards = [
    {
        name: 'Enterprise',
        price: '$89.95 / mo',
        listPrice: '$119.95 / mo',
        badge: '25% off early adopter',
        copy: 'Packaged intranet without the browser plugin.',
        highlighted: false,
    },
    {
        name: 'Enterprise Pro',
        price: '$199.95 / mo',
        listPrice: '$266.60 / mo',
        badge: '25% off early adopter',
        copy: 'Includes the browser plugin and API lane.',
        highlighted: true,
    },
];

export const footerGroups = [
    {
        title: 'Product',
        links: [
            { href: '/features', label: 'Features' },
            { href: '/pricing', label: 'Pricing' },
            { href: '/migration', label: 'Migration' },
            { href: '/updates', label: 'Updates' },
        ],
    },
    {
        title: 'Resources',
        links: [
            { href: '/outsourcing', label: 'Outsourcing' },
            { href: '/roadmap', label: 'Roadmap' },
            { href: '/videos', label: 'Videos' },
            { href: '/client-portal', label: 'Portal' },
        ],
    },
    {
        title: 'Company',
        links: [
            { href: '/login', label: 'Login' },
            { href: '/subscribe', label: 'Office Fit Check' },
            {
                href: 'mailto:hello@creditsoft.app',
                label: 'hello@creditsoft.app',
            },
        ],
    },
];
