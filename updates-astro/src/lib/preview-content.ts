type PreviewHero = {
  eyebrow: string;
  title: string;
  summary: string;
  primaryCta: { label: string; href: string };
  secondaryCta: { label: string; href: string };
  stats: string[];
};

type PreviewPlansIntro = {
  eyebrow: string;
  title: string;
  text: string;
};

type PreviewCompareRow = {
  feature: string;
  starter: string;
  plus: string;
  pro: string;
};

type PreviewFeature = {
  title: string;
  text: string;
};

type PreviewTestimonial = {
  title: string;
  quote: string;
  name: string;
};

type PreviewFaq = {
  question: string;
  answer: string;
};

type PreviewCta = {
  eyebrow: string;
  title: string;
  text: string;
  primaryCta: { label: string; href: string };
  secondaryCta: { label: string; href: string };
};

type PreviewFooter = {
  summary: string;
  contact: string;
};

export type MarketingThemePage = {
  topbarText: string;
  topbarEmail: string;
  hero: PreviewHero;
  plansIntro: PreviewPlansIntro;
  compareRows: PreviewCompareRow[];
  features: PreviewFeature[];
  testimonials: PreviewTestimonial[];
  faqs: PreviewFaq[];
  cta: PreviewCta;
  footer: PreviewFooter;
};

export type SitePlan = {
  id: string;
  name: string;
  badge: string | null;
  description: string | null;
  monthlyPrice: number;
  saleMonthlyPrice: number | null;
  annualPrice: number;
  annualDiscountPercent: number | null;
  highlighted: boolean;
  featureList: string[];
};

export type SiteReview = {
  id: string;
  fullName: string;
  company: string | null;
  quote: string;
  rating: number;
  status: string;
};

const fallbackThemePage: MarketingThemePage = {
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
    stats: ["Intranet based", "PWA-ready", "Migration aware", "Brand control"],
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

const fallbackPlans: SitePlan[] = [
  {
    id: "starter",
    name: "Starter",
    badge: "Best for small offices",
    description:
      "A light launch lane for offices that want the public site, intake, and a clean start.",
    monthlyPrice: 149,
    saleMonthlyPrice: 119,
    annualPrice: 1188,
    annualDiscountPercent: 34,
    highlighted: false,
    featureList: ["Public site", "Lead intake", "Simple admin review", "Update feed access"],
  },
  {
    id: "growth",
    name: "Growth",
    badge: "Most popular",
    description:
      "For offices ready for stronger automation, sales follow-up, and team coordination.",
    monthlyPrice: 249,
    saleMonthlyPrice: 199,
    annualPrice: 1980,
    annualDiscountPercent: 34,
    highlighted: true,
    featureList: ["Client workspaces", "Letters", "Automation lanes", "Website admin tools"],
  },
  {
    id: "enterprise",
    name: "Enterprise",
    badge: "For larger teams",
    description:
      "For multi-user operations that want the full office lane with more controls and oversight.",
    monthlyPrice: 399,
    saleMonthlyPrice: 329,
    annualPrice: 3288,
    annualDiscountPercent: 31,
    highlighted: false,
    featureList: ["Team routing", "Billing control", "Priority support", "Rollout help"],
  },
];

const fallbackReviews: SiteReview[] = [
  {
    id: "review-1",
    fullName: "Marilyn Perry",
    company: "Marilyn Perry Credit",
    quote:
      "We finally have one place for the office instead of juggling five tools and a spreadsheet.",
    rating: 5,
    status: "approved",
  },
  {
    id: "review-2",
    fullName: "Avery Cole",
    company: "Cole Credit Works",
    quote:
      "The intake and review flow feels like it was designed for credit repair work, not retrofitted from CRM software.",
    rating: 5,
    status: "approved",
  },
];

function env(name: string): string | null {
  const value = import.meta.env?.[name] ?? process.env[name];
  return typeof value === "string" && value.trim() !== "" ? value.trim() : null;
}

async function querySupabase<T>(table: string, params: Record<string, string>): Promise<T | null> {
  const url = env("SUPABASE_URL");
  const key = env("SUPABASE_SECRET_KEY");

  if (!url || !key) {
    return null;
  }

  const endpoint = new URL(`${url.replace(/\/+$/, "")}/rest/v1/${table}`);

  Object.entries(params).forEach(([k, v]) => endpoint.searchParams.set(k, v));

  const response = await fetch(endpoint, {
    headers: {
      apikey: key,
      Authorization: `Bearer ${key}`,
      Accept: "application/json",
    },
  });

  if (!response.ok) {
    return null;
  }

  return (await response.json()) as T;
}

function toNumber(value: unknown, fallback = 0): number {
  const numeric = typeof value === "string" ? Number(value) : Number(value);
  return Number.isFinite(numeric) ? numeric : fallback;
}

function toStringArray(value: unknown): string[] {
  if (Array.isArray(value)) {
    return value.map((item) => String(item)).filter(Boolean);
  }

  if (typeof value === "string") {
    const trimmed = value.trim();

    if (!trimmed) {
      return [];
    }

    try {
      const parsed = JSON.parse(trimmed);
      if (Array.isArray(parsed)) {
        return parsed.map((item) => String(item)).filter(Boolean);
      }
    } catch {
      // Fall back to plain-text splitting below.
    }

    return trimmed
      .split(/\r?\n|,/)
      .map((item) => item.replace(/^[\s*-]+/, "").trim())
      .filter(Boolean);
  }

  return [];
}

export async function loadThemePage(slug = "updates-clone-home"): Promise<MarketingThemePage> {
  const rows = await querySupabase<Array<{ data: MarketingThemePage }>>("marketing_theme_pages", {
    slug: `eq.${slug}`,
    select: "data",
    limit: "1",
  });

  return rows?.[0]?.data ?? fallbackThemePage;
}

export async function loadPlans(): Promise<SitePlan[]> {
  const rows = await querySupabase<Array<Record<string, unknown>>>("pricing_plans", {
    select: "*",
    order: "sort_order.asc",
  });

  if (!rows || rows.length === 0) {
    return fallbackPlans;
  }

  return rows.map((row) => ({
    id: String(row.id ?? crypto.randomUUID()),
    name: String(row.name ?? "Plan"),
    badge: row.badge ? String(row.badge) : null,
    description: row.description ? String(row.description) : null,
    monthlyPrice: toNumber(row.monthly_price ?? row.monthlyPrice),
    saleMonthlyPrice:
      row.sale_monthly_price ?? row.saleMonthlyPrice ?? row.list_monthly_price ?? row.listMonthlyPrice
        ? toNumber(row.sale_monthly_price ?? row.saleMonthlyPrice ?? row.list_monthly_price ?? row.listMonthlyPrice)
        : null,
    annualPrice: toNumber(row.annual_price ?? row.annualPrice ?? row.yearly_price ?? row.yearlyPrice),
    annualDiscountPercent:
      row.annual_discount_percent ?? row.annualDiscountPercent
        ? toNumber(row.annual_discount_percent ?? row.annualDiscountPercent)
        : null,
    highlighted:
      row.highlighted === true ||
      row.highlighted === "true" ||
      row.highlighted === 1,
    featureList: toStringArray(row.feature_list ?? row.featureList ?? row.features),
  }));
}

export async function loadReviews(): Promise<SiteReview[]> {
  const rows = await querySupabase<Array<Record<string, unknown>>>("reviews", {
    select: "*",
    status: "neq.hidden",
    order: "created_at.desc",
    limit: "3",
  });

  if (!rows || rows.length === 0) {
    return fallbackReviews;
  }

  return rows.map((row) => ({
    id: String(row.id ?? crypto.randomUUID()),
    fullName: String(row.reviewer_name ?? row.full_name ?? row.fullName ?? "Customer"),
    company: row.company ? String(row.company) : null,
    quote: String(row.quote ?? ""),
    rating: Math.max(1, Math.min(5, Math.round(toNumber(row.rating, 5)))),
    status: String(row.status ?? "approved"),
  }));
}
