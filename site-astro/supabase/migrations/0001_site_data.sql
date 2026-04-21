create extension if not exists pgcrypto;

create table if not exists public.site_plans (
  id uuid primary key default gen_random_uuid(),
  slug text not null unique,
  name text not null,
  badge text,
  description text,
  monthly_price numeric(10,2) not null default 0,
  sale_monthly_price numeric(10,2),
  annual_price numeric(10,2) not null default 0,
  annual_list_price numeric(10,2),
  annual_discount_percent integer,
  features jsonb not null default '[]'::jsonb,
  cta_label text not null default 'Get started',
  highlighted boolean not null default false,
  active boolean not null default true,
  sort_order integer not null default 0,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table if not exists public.site_reviews (
  id uuid primary key default gen_random_uuid(),
  reviewer_name text not null,
  reviewer_title text,
  company text,
  quote text not null,
  rating integer not null default 5,
  source text,
  active boolean not null default true,
  sort_order integer not null default 0,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table if not exists public.site_payments (
  id uuid primary key default gen_random_uuid(),
  customer_name text not null,
  amount numeric(10,2) not null default 0,
  status text not null default 'paid',
  method text not null default 'manual',
  memo text,
  paid_at timestamptz not null default now(),
  source text,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table if not exists public.site_leads (
  id uuid primary key default gen_random_uuid(),
  name text not null,
  email text not null,
  phone text,
  company text,
  source text not null default 'site',
  status text not null default 'new',
  plan_interest text,
  current_software text,
  client_count integer,
  outsourcing text,
  merchant_provider text,
  payment_methods text,
  website_status text,
  roi_visibility text,
  notes jsonb not null default '{}'::jsonb,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table if not exists public.site_update_feed (
  id text primary key default 'default',
  current_version text not null default '0.1.0',
  current_build text not null default 'astro-sales-site',
  latest_version text not null default '0.1.0',
  latest_build text not null default 'astro-sales-site',
  download_url text not null default 'https://updates.creditsoft.app/',
  browser_companion_url text,
  notes text,
  support_url text,
  updated_at timestamptz not null default now()
);

create table if not exists public.site_install_ads (
  id uuid primary key default gen_random_uuid(),
  eyebrow text not null default 'Featured',
  title text not null,
  copy text not null,
  cta_label text not null default 'Learn more',
  link_url text not null,
  image_url text,
  active boolean not null default true,
  sort_order integer not null default 0,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

alter table public.site_plans enable row level security;
alter table public.site_reviews enable row level security;
alter table public.site_payments enable row level security;
alter table public.site_leads enable row level security;
alter table public.site_update_feed enable row level security;
alter table public.site_install_ads enable row level security;

do $$
begin
  if not exists (
    select 1
    from pg_policies
    where schemaname = 'public'
      and tablename = 'site_plans'
      and policyname = 'Public read site_plans'
  ) then
    create policy "Public read site_plans" on public.site_plans for select using (true);
  end if;

  if not exists (
    select 1
    from pg_policies
    where schemaname = 'public'
      and tablename = 'site_reviews'
      and policyname = 'Public read site_reviews'
  ) then
    create policy "Public read site_reviews" on public.site_reviews for select using (true);
  end if;

  if not exists (
    select 1
    from pg_policies
    where schemaname = 'public'
      and tablename = 'site_payments'
      and policyname = 'Public read site_payments'
  ) then
    create policy "Public read site_payments" on public.site_payments for select using (true);
  end if;

  if not exists (
    select 1
    from pg_policies
    where schemaname = 'public'
      and tablename = 'site_update_feed'
      and policyname = 'Public read site_update_feed'
  ) then
    create policy "Public read site_update_feed" on public.site_update_feed for select using (true);
  end if;

  if not exists (
    select 1
    from pg_policies
    where schemaname = 'public'
      and tablename = 'site_install_ads'
      and policyname = 'Public read site_install_ads'
  ) then
    create policy "Public read site_install_ads" on public.site_install_ads for select using (true);
  end if;

  if not exists (
    select 1
    from pg_policies
    where schemaname = 'public'
      and tablename = 'site_leads'
      and policyname = 'Public insert site_leads'
  ) then
    create policy "Public insert site_leads" on public.site_leads for insert with check (true);
  end if;
end $$;
