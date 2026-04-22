import type {
    SiteInstallAd,
    SiteLead,
    SiteLicense,
    SitePayment,
    SitePlan,
    SitePrivacyRequest,
    SiteReview,
    SiteUpdateFeed,
} from './types';

export const fallbackPlans: SitePlan[] = [
    {
        id: 'plan-starter',
        slug: 'starter',
        name: 'Starter',
        badge: 'Best for small offices',
        description:
            'A light launch lane for offices that want the public site, intake, and a clean start.',
        monthlyPrice: 149,
        saleMonthlyPrice: 119,
        annualPrice: 1188,
        annualListPrice: 1788,
        annualDiscountPercent: 34,
        features: [
            'Public site',
            'Lead intake',
            'Simple admin review',
            'Update feed access',
        ],
        ctaLabel: 'Start with Starter',
        highlighted: false,
        active: true,
        sortOrder: 1,
    },
    {
        id: 'plan-growth',
        slug: 'growth',
        name: 'Growth',
        badge: 'Most popular',
        description:
            'For offices ready for stronger automation, sales follow-up, and team coordination.',
        monthlyPrice: 249,
        saleMonthlyPrice: 199,
        annualPrice: 1980,
        annualListPrice: 2988,
        annualDiscountPercent: 34,
        features: [
            'Everything in Starter',
            'Client workspaces',
            'Billing and revenue context',
            'Website admin tools',
        ],
        ctaLabel: 'Choose Growth',
        highlighted: true,
        active: true,
        sortOrder: 2,
    },
    {
        id: 'plan-enterprise',
        slug: 'enterprise',
        name: 'Enterprise',
        badge: 'For larger teams',
        description:
            'For multi-user operations that want the full office lane with more controls and oversight.',
        monthlyPrice: 399,
        saleMonthlyPrice: 329,
        annualPrice: 3288,
        annualListPrice: 4788,
        annualDiscountPercent: 31,
        features: [
            'Everything in Growth',
            'Advanced revenue tracking',
            'Team routing',
            'Custom admin options',
        ],
        ctaLabel: 'Talk Enterprise',
        highlighted: false,
        active: true,
        sortOrder: 3,
    },
];

export const fallbackReviews: SiteReview[] = [
    {
        id: 'review-1',
        reviewerName: 'Marilyn Perry',
        reviewerTitle: 'Owner',
        company: 'Marilyn Perry Credit',
        quote: 'We finally have one place for the office instead of juggling five tools and a spreadsheet.',
        rating: 5,
        source: 'Manual import',
        active: true,
        sortOrder: 1,
    },
    {
        id: 'review-2',
        reviewerName: 'Avery Cole',
        reviewerTitle: 'Operations',
        company: 'Cole Workflow Group',
        quote: 'The intake and review flow feels like it was designed for credit repair work, not retrofitted from CRM software.',
        rating: 5,
        source: 'Manual import',
        active: true,
        sortOrder: 2,
    },
    {
        id: 'review-3',
        reviewerName: 'Matthew Murphy',
        reviewerTitle: 'Owner Admin',
        company: 'CreditSoft',
        quote: 'The product should guide the next move. That is what we are building into every screen.',
        rating: 5,
        source: 'Internal',
        active: true,
        sortOrder: 3,
    },
];

export const fallbackPayments: SitePayment[] = [
    {
        id: 'payment-1',
        customerName: 'Marilyn Perry',
        amount: 249,
        status: 'paid',
        method: 'Zelle',
        memo: 'Growth monthly',
        paidAt: new Date('2026-04-12T00:00:00.000Z').toISOString(),
        source: 'Checkout notice',
    },
    {
        id: 'payment-2',
        customerName: 'Avery Cole',
        amount: 1188,
        status: 'paid',
        method: 'ACH',
        memo: 'Starter annual',
        paidAt: new Date('2026-04-10T00:00:00.000Z').toISOString(),
        source: 'Checkout notice',
    },
];

export const fallbackLicenses: SiteLicense[] = [
    {
        id: 'license-1',
        email: 'marilyn@example.com',
        customerName: 'Marilyn Perry',
        planKey: 'growth',
        licenseKey: 'LIC-1042',
        status: 'active',
        expiresAt: new Date('2026-05-12T00:00:00.000Z').toISOString(),
        issuedAt: new Date('2026-04-12T00:00:00.000Z').toISOString(),
        notes: 'Growth monthly license',
    },
    {
        id: 'license-2',
        email: 'avery@example.com',
        customerName: 'Avery Cole',
        planKey: 'starter',
        licenseKey: 'LIC-1048',
        status: 'active',
        expiresAt: new Date('2026-06-01T00:00:00.000Z').toISOString(),
        issuedAt: new Date('2026-04-10T00:00:00.000Z').toISOString(),
        notes: 'Starter annual license',
    },
    {
        id: 'license-3',
        email: 'jordan@example.com',
        customerName: 'Jordan Blake',
        planKey: 'enterprise',
        licenseKey: 'LIC-1051',
        status: 'trial',
        expiresAt: new Date('2026-04-30T00:00:00.000Z').toISOString(),
        issuedAt: new Date('2026-04-11T00:00:00.000Z').toISOString(),
        notes: 'Pending upgrade decision',
    },
];

export const fallbackPrivacyRequests: SitePrivacyRequest[] = [
    {
        id: 'privacy-1',
        email: 'avery@example.com',
        requestType: 'Delete my info',
        details: 'Requested deletion of sales-site contact history.',
        status: 'open',
        createdAt: new Date('2026-04-12T00:00:00.000Z').toISOString(),
    },
    {
        id: 'privacy-2',
        email: 'marilyn@example.com',
        requestType: 'Privacy export',
        details:
            'Needs a copy of the website intake answers and billing history.',
        status: 'processing',
        createdAt: new Date('2026-04-10T00:00:00.000Z').toISOString(),
    },
];

export const fallbackLeads: SiteLead[] = [];

export const fallbackUpdateFeed: SiteUpdateFeed = {
    currentVersion: '0.9.0',
    currentBuild: '2026.04.19.072951',
    latestVersion: '0.9.25',
    latestBuild: '2026.04.20.051105',
    downloadUrl:
        'https://updates.creditsoft.app/downloads/creditsoft-office-v0.9.25.zip',
    browserCompanionUrl:
        'https://update.creditsoft.app/downloads/creditsoft-browser-companion-v0.5.11.zip',
    notes: 'CreditSoft Office 0.9.25 with browser companion 0.5.11, Clients + files DisputeFox profile/document import, recovery-sweep queueing, report-aware document capture, guarded provider queueing, and the 7-day companion trial.',
    supportUrl: 'https://creditsoft.app/login',
};

export const fallbackInstallAds: SiteInstallAd[] = [
    {
        id: 'net30hosting',
        eyebrow: 'Featured partner',
        title: 'Net30Hosting',
        copy: 'Build business credit with hosting, email, and domain infrastructure. Payments reported to Equifax and PayNet.',
        ctaLabel: 'View Net30Hosting',
        linkUrl: 'https://net30hosting.com/',
        imageUrl: '/branding/install-ads/net30hosting.svg',
        logoUrl: '/branding/install-ads/net30hosting-logo.png',
        durationMs: 20000,
        active: true,
        sortOrder: 1,
    },
    {
        id: 'creditsoft',
        eyebrow: 'CreditSoft',
        title: 'Run the office from one lane',
        copy: 'Leads, plans, billing, and review operations in one sales site backed by Supabase and Astro.',
        ctaLabel: 'See CreditSoft',
        linkUrl: 'https://creditsoft.app/',
        imageUrl: null,
        active: true,
        sortOrder: 2,
    },
];
