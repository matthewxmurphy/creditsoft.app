import postgres from "postgres";

const connectionString = process.env.DATABASE_URL;

if (!connectionString) {
  throw new Error("DATABASE_URL is required");
}

const sql = postgres(connectionString, { prepare: false });

const previewData = {
  topbarText:
    "Metro2-first credit repair software with local-first CRM, intranet, portal, and website lanes.",
  topbarEmail: "hello@creditsoft.app",
  hero: {
    eyebrow: "Welcome to CreditSoft",
    title:
      "Build, Launch, and Run a Credit Repair Office Without Looking Like Generic SaaS",
    summary:
      "CreditSoft gives offices a branded website, client portal, update lane, and local-first operations surface that feels like one real product instead of a stitched stack.",
    primaryCta: { label: "Get Started", href: "/contact-us" },
    secondaryCta: { label: "View Demo", href: "/videos" },
    stats: [
      "Intranet based",
      "PWA-ready",
      "Migration aware",
      "Brand control",
    ],
  },
  plansIntro: {
    eyebrow: "Pricing Plan",
    title: "Flexible Plans Built for Every Office Stage",
    text:
      "Choose the plan that fits your current office size, then grow into the website, portal, update, and office lanes without swapping systems again.",
  },
  compareRows: [
    {
      feature: "Intranet based workspace",
      starter: "Built in",
      plus: "Built in",
      pro: "Built in + team lanes",
    },
    {
      feature: "PWA support",
      starter: "Ready",
      plus: "Ready",
      pro: "Ready",
    },
    {
      feature: "Client portal and website",
      starter: "Included",
      plus: "Included",
      pro: "Included + branded support",
    },
    {
      feature: "Migration help",
      starter: "Import lane",
      plus: "Import + setup help",
      pro: "Priority migration lane",
    },
    {
      feature: "Browser companion",
      starter: "Optional",
      plus: "Included",
      pro: "Included + rollout support",
    },
  ],
  features: [
    {
      title: "Branded website and portal",
      text: "Keep the public site, client portal, intake, and update lane under one cleaner brand system.",
    },
    {
      title: "Local-first office operations",
      text: "Run the real office from a local-first intranet instead of forcing every workflow into a generic cloud CRM.",
    },
    {
      title: "Migration-aware from day one",
      text: "Imports, letters, updates, and browser rails are designed to help an office move without starting over.",
    },
    {
      title: "Admin and review control",
      text: "Manage leads, plan pricing, reviews, privacy requests, and licenses from a site-admin lane that matches the product.",
    },
  ],
  testimonials: [
    {
      title: "Excellent service",
      quote:
        "The site, portal, and office lanes finally look like one intentional product instead of three unrelated systems.",
      name: "Office admin placeholder",
    },
    {
      title: "Most relevant and useful",
      quote:
        "CreditSoft feels built for actual credit repair workflow, not retrofitted from a general CRM.",
      name: "Operations placeholder",
    },
    {
      title: "Cleaner migration path",
      quote:
        "The migration lane makes it feel possible to switch without throwing away our brand or workflow.",
      name: "Owner placeholder",
    },
  ],
  faqs: [
    {
      question: "Is CreditSoft only a website?",
      answer:
        "No. The website is just one lane. CreditSoft also includes the client portal, update lane, and local-first office workspace.",
    },
    {
      question: "Can this replace a stitched-together stack?",
      answer:
        "That is the goal. The product is designed to reduce the number of separate tools an office needs to look professional and stay operational.",
    },
    {
      question: "Do you help with migration?",
      answer:
        "Yes. Import, mapping, branded site setup, and workflow transition are all part of the migration direction.",
    },
    {
      question: "Is the browser companion included?",
      answer:
        "It is included on the higher lanes and can be part of the rollout plan for offices that want more automation help.",
    },
  ],
  cta: {
    eyebrow: "Try CreditSoft",
    title: "Start with the site, then move into the full office lane when you are ready.",
    text:
      "This preview shows the direction for a cleaner public site that matches the rest of the CreditSoft product story.",
    primaryCta: { label: "Start Intake", href: "/contact-us" },
    secondaryCta: { label: "See Pricing", href: "/pricing-plan" },
  },
  footer: {
    summary:
      "CreditSoft gives offices a cleaner website, portal, update lane, and operations surface without looking like generic software.",
    contact: "hello@creditsoft.app",
  },
};

await sql`
  create extension if not exists pgcrypto;
`;

await sql`
  create table if not exists marketing_theme_pages (
    id uuid primary key default gen_random_uuid(),
    slug text not null unique,
    title text not null,
    description text,
    data jsonb not null default '{}'::jsonb,
    updated_at timestamptz not null default now()
  );
`;

await sql`
  insert into marketing_theme_pages (slug, title, description, data)
  values (
    'updates-clone-home',
    'CreditSoft Zarex Clone Preview',
    'CreditSoft main-site clone preview content for the updates preview host.',
    ${sql.json(previewData)}
  )
  on conflict (slug) do update set
    title = excluded.title,
    description = excluded.description,
    data = excluded.data,
    updated_at = now();
`;

console.log("Seeded marketing_theme_pages: updates-clone-home");

await sql.end();
