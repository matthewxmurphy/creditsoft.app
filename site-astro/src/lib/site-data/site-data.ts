import type { SupabaseClient } from '@supabase/supabase-js';
import { randomUUID } from 'node:crypto';
import { createSiteSupabaseServerClient } from '../supabase';
import {
    fallbackInstallAds,
    fallbackLeads,
    fallbackLicenses,
    fallbackPayments,
    fallbackPlans,
    fallbackPrivacyRequests,
    fallbackReviews,
    fallbackUpdateFeed,
} from './fallbacks';
import type {
    LeadCaptureInput,
    LicenseBillingIntelligenceInput,
    SiteDataSnapshot,
    SiteInstallAd,
    SiteLead,
    SiteLicenseBillingIntelligence,
    SiteLicense,
    SitePayment,
    SitePlan,
    SitePrivacyRequest,
    SiteReview,
    SiteUpdateFeed,
} from './types';
import { appendLocalLead, appendLocalLicenseBillingIntelligence, readLocalInstallAds, readLocalLeads } from './store';

type SourceResult<T> = {
    source: 'supabase' | 'fallback';
    data: T;
};

function toNumber(value: unknown, fallback = 0): number {
    const numberValue =
        typeof value === 'string' ? Number(value) : Number(value);
    return Number.isFinite(numberValue) ? numberValue : fallback;
}

function toNullableNumber(value: unknown): number | null {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const numberValue =
        typeof value === 'string' ? Number(value) : Number(value);
    return Number.isFinite(numberValue) ? numberValue : null;
}

function toString(value: unknown, fallback = ''): string {
    return typeof value === 'string' && value.trim() !== ''
        ? value.trim()
        : fallback;
}

function toNullableString(value: unknown): string | null {
    if (value === null || value === undefined) {
        return null;
    }

    const stringValue = toString(value, '');
    return stringValue === '' ? null : stringValue;
}

function toBoolean(value: unknown, fallback = false): boolean {
    if (typeof value === 'boolean') {
        return value;
    }

    if (typeof value === 'string') {
        return ['1', 'true', 'yes', 'on'].includes(value.toLowerCase());
    }

    if (typeof value === 'number') {
        return value !== 0;
    }

    return fallback;
}

function toStringArray(value: unknown): string[] {
    if (Array.isArray(value)) {
        return value
            .map((item) => toString(item, ''))
            .filter((item) => item !== '');
    }

    if (typeof value === 'string' && value.trim() !== '') {
        return value
            .split('\n')
            .map((item) => item.trim())
            .filter(Boolean);
    }

    return [];
}

async function withSupabase<T>(
    cookies: Parameters<typeof createSiteSupabaseServerClient>[0],
    query: (client: SupabaseClient) => Promise<T>,
): Promise<T | null> {
    const client = createSiteSupabaseServerClient(cookies);

    if (!client) {
        return null;
    }

    try {
        return await query(client);
    } catch {
        return null;
    }
}

export async function loadPlans(
    cookies?: Parameters<typeof createSiteSupabaseServerClient>[0],
): Promise<SourceResult<SitePlan[]>> {
    const supabasePlans = cookies
        ? await withSupabase(cookies, async (client) => {
              const { data, error } = await client
                  .from('pricing_plans')
                  .select('*')
                  .order('sort_order', { ascending: true });

              if (error || !data) {
                  return null;
              }

              return data.map((row) => ({
                  id: toString(row.id, randomUUID()),
                  slug: toString(row.slug, toString(row.name, 'plan')),
                  name: toString(row.name, 'Plan'),
                  badge: toNullableString(row.badge),
                  description: toNullableString(row.description),
                  monthlyPrice: toNumber(row.monthly_price ?? row.monthlyPrice),
                  saleMonthlyPrice: toNullableNumber(
                      row.sale_monthly_price ??
                          row.saleMonthlyPrice ??
                          row.list_monthly_price ??
                          row.listMonthlyPrice,
                  ),
                  annualPrice: toNumber(
                      row.annual_price ??
                          row.annualPrice ??
                          row.yearly_price ??
                          row.yearlyPrice,
                  ),
                  annualListPrice: toNullableNumber(
                      row.annual_list_price ??
                          row.annualListPrice ??
                          row.list_yearly_price ??
                          row.listYearlyPrice,
                  ),
                  annualDiscountPercent: toNullableNumber(
                      row.annual_discount_percent ?? row.annualDiscountPercent,
                  ),
                  features: toStringArray(
                      row.feature_list ?? row.featureList ?? row.features,
                  ),
                  ctaLabel: toString(
                      row.cta_label ?? row.ctaLabel,
                      'Get started',
                  ),
                  highlighted: toBoolean(row.highlighted),
                  active: true,
                  sortOrder: toNumber(row.sort_order ?? row.sortOrder),
              })) as SitePlan[];
          })
        : null;

    return {
        source:
            supabasePlans && supabasePlans.length > 0 ? 'supabase' : 'fallback',
        data:
            supabasePlans && supabasePlans.length > 0
                ? supabasePlans
                : fallbackPlans,
    };
}

export async function loadReviews(
    cookies?: Parameters<typeof createSiteSupabaseServerClient>[0],
): Promise<SourceResult<SiteReview[]>> {
    const supabaseReviews = cookies
        ? await withSupabase(cookies, async (client) => {
              const { data, error } = await client
                  .from('reviews')
                  .select('*')
                  .order('created_at', { ascending: false });

              if (error || !data) {
                  return null;
              }

              return data.map((row) => ({
                  id: toString(row.id, randomUUID()),
                  reviewerName: toString(
                      row.reviewer_name ?? row.reviewerName,
                      'Customer',
                  ),
                  reviewerTitle: toNullableString(
                      row.reviewer_title ?? row.reviewerTitle,
                  ),
                  company: toNullableString(row.company),
                  quote: toString(row.quote, ''),
                  rating: Math.max(
                      1,
                      Math.min(5, Math.round(toNumber(row.rating, 5))),
                  ),
                  source: toNullableString(row.source),
                  active: toString(row.status, 'pending') !== 'hidden',
                  sortOrder: 0,
              })) as SiteReview[];
          })
        : null;

    return {
        source:
            supabaseReviews && supabaseReviews.length > 0
                ? 'supabase'
                : 'fallback',
        data:
            supabaseReviews && supabaseReviews.length > 0
                ? supabaseReviews
                : fallbackReviews,
    };
}

export async function loadPayments(
    cookies?: Parameters<typeof createSiteSupabaseServerClient>[0],
): Promise<SourceResult<SitePayment[]>> {
    const supabasePayments = cookies
        ? await withSupabase(cookies, async (client) => {
              const { data, error } = await client
                  .from('payments')
                  .select('*')
                  .order('created_at', { ascending: false });

              if (error || !data) {
                  return null;
              }

              return data.map((row) => ({
                  id: toString(row.id, randomUUID()),
                  customerName: toString(
                      row.customer_name ?? row.customerName,
                      'Customer',
                  ),
                  amount: toNumber(row.amount, 0),
                  status: toString(row.status, 'unknown'),
                  method: toString(row.method ?? row.provider, 'manual'),
                  memo: toNullableString(row.memo ?? row.external_reference),
                  paidAt: new Date(
                      row.paid_at ?? row.paidAt ?? row.created_at ?? Date.now(),
                  ).toISOString(),
                  source: toNullableString(row.source ?? row.provider),
              })) as SitePayment[];
          })
        : null;

    return {
        source:
            supabasePayments && supabasePayments.length > 0
                ? 'supabase'
                : 'fallback',
        data:
            supabasePayments && supabasePayments.length > 0
                ? supabasePayments
                : fallbackPayments,
    };
}

export async function loadLicenses(
    cookies?: Parameters<typeof createSiteSupabaseServerClient>[0],
): Promise<SourceResult<SiteLicense[]>> {
    const supabaseLicenses = cookies
        ? await withSupabase(cookies, async (client) => {
              const { data, error } = await client
                  .from('licenses')
                  .select('*')
                  .order('issued_at', { ascending: false });

              if (error || !data) {
                  return null;
              }

              return data.map((row) => ({
                  id: toString(row.id, randomUUID()),
                  email: toString(row.email, ''),
                  customerName: toNullableString(
                      row.customer_name ?? row.customerName,
                  ),
                  planKey: toString(row.plan_key ?? row.planKey, 'starter'),
                  licenseKey: toString(
                      row.license_key ?? row.licenseKey,
                      'LIC',
                  ),
                  status: toString(row.status, 'active'),
                  expiresAt:
                      (row.expires_at ?? row.expiresAt)
                          ? new Date(
                                row.expires_at ?? row.expiresAt,
                            ).toISOString()
                          : null,
                  issuedAt: new Date(
                      row.issued_at ?? row.issuedAt ?? Date.now(),
                  ).toISOString(),
                  notes: toNullableString(row.notes),
              })) as SiteLicense[];
          })
        : null;

    return {
        source:
            supabaseLicenses && supabaseLicenses.length > 0
                ? 'supabase'
                : 'fallback',
        data:
            supabaseLicenses && supabaseLicenses.length > 0
                ? supabaseLicenses
                : fallbackLicenses,
    };
}

export async function loadPrivacyRequests(
    cookies?: Parameters<typeof createSiteSupabaseServerClient>[0],
): Promise<SourceResult<SitePrivacyRequest[]>> {
    const supabaseRequests = cookies
        ? await withSupabase(cookies, async (client) => {
              const { data, error } = await client
                  .from('privacy_requests')
                  .select('*')
                  .order('created_at', { ascending: false });

              if (error || !data) {
                  return null;
              }

              return data.map((row) => ({
                  id: toString(row.id, randomUUID()),
                  email: toString(row.email, ''),
                  requestType: toString(
                      row.request_type ?? row.requestType,
                      'Request',
                  ),
                  details: toNullableString(row.details),
                  status: toString(row.status, 'open'),
                  createdAt: new Date(
                      row.created_at ?? row.createdAt ?? Date.now(),
                  ).toISOString(),
              })) as SitePrivacyRequest[];
          })
        : null;

    return {
        source:
            supabaseRequests && supabaseRequests.length > 0
                ? 'supabase'
                : 'fallback',
        data:
            supabaseRequests && supabaseRequests.length > 0
                ? supabaseRequests
                : fallbackPrivacyRequests,
    };
}

export async function loadLeads(
    cookies?: Parameters<typeof createSiteSupabaseServerClient>[0],
): Promise<SourceResult<SiteLead[]>> {
    const supabaseLeads = cookies
        ? await withSupabase(cookies, async (client) => {
              const { data, error } = await client
                  .from('leads')
                  .select('*')
                  .order('created_at', { ascending: false });

              if (error || !data) {
                  return null;
              }

              return data.map((row) => ({
                  id: toString(row.id, randomUUID()),
                  name: toString(row.name ?? row.full_name, 'Lead'),
                  email: toString(row.email, ''),
                  phone: toNullableString(row.phone),
                  company: toNullableString(row.company),
                  source: toString(row.source, 'site'),
                  status: toString(row.status, 'new'),
                  planInterest: toNullableString(
                      row.plan_interest ?? row.planInterest,
                  ),
                  currentSoftware: toNullableString(
                      row.current_software ?? row.currentSoftware,
                  ),
                  clientCount: toNullableNumber(
                      row.client_count ?? row.clientCount,
                  ),
                  outsourcing: toNullableString(row.outsourcing),
                  merchantProvider: toNullableString(
                      row.merchant_provider ?? row.merchantProvider,
                  ),
                  paymentMethods: toNullableString(
                      row.payment_methods ?? row.paymentMethods,
                  ),
                  websiteStatus: toNullableString(
                      row.website_status ?? row.websiteStatus,
                  ),
                  roiVisibility: toNullableString(
                      row.roi_visibility ?? row.roiVisibility,
                  ),
                  notes:
                      typeof row.notes === 'object' && row.notes !== null
                          ? (row.notes as Record<string, unknown>)
                          : typeof row.notes === 'string' &&
                              row.notes.trim() !== ''
                            ? { value: row.notes }
                            : {},
                  createdAt: new Date(
                      row.created_at ?? row.createdAt ?? Date.now(),
                  ).toISOString(),
                  updatedAt: new Date(
                      row.updated_at ?? row.updatedAt ?? Date.now(),
                  ).toISOString(),
              })) as SiteLead[];
          })
        : null;

    if (supabaseLeads && supabaseLeads.length > 0) {
        return { source: 'supabase', data: supabaseLeads };
    }

    return {
        source: 'fallback',
        data: await readLocalLeads(fallbackLeads),
    };
}

export async function captureLead(
    lead: LeadCaptureInput,
    cookies?: Parameters<typeof createSiteSupabaseServerClient>[0],
): Promise<SourceResult<SiteLead>> {
    const now = new Date().toISOString();
    const payload: SiteLead = {
        id: randomUUID(),
        name: toString(lead.name, 'Lead'),
        email: toString(lead.email, ''),
        phone: toNullableString(lead.phone),
        company: toNullableString(lead.company),
        source: toString(lead.source, 'site'),
        status: toString(lead.status, 'new'),
        planInterest: toNullableString(lead.planInterest),
        currentSoftware: toNullableString(lead.currentSoftware),
        clientCount: lead.clientCount ?? null,
        outsourcing: toNullableString(lead.outsourcing),
        merchantProvider: toNullableString(lead.merchantProvider),
        paymentMethods: toNullableString(lead.paymentMethods),
        websiteStatus: toNullableString(lead.websiteStatus),
        roiVisibility: toNullableString(lead.roiVisibility),
        notes: lead.notes ?? {},
        createdAt: now,
        updatedAt: now,
    };

    const inserted = cookies
        ? await withSupabase(cookies, async (client) => {
              const { data, error } = await client
                  .from('leads')
                  .insert({
                      full_name: payload.name,
                      email: payload.email,
                      phone: payload.phone,
                      company: payload.company,
                      source: payload.source,
                      status: payload.status,
                      plan_interest: payload.planInterest,
                      notes: Object.keys(payload.notes).length
                          ? JSON.stringify(payload.notes)
                          : null,
                  })
                  .select('*')
                  .single();

              if (error || !data) {
                  return null;
              }

              await client.from('lead_intake_answers').insert({
                  lead_id: data.id,
                  client_count:
                      payload.clientCount === null
                          ? null
                          : String(payload.clientCount),
                  monitoring_systems: Array.isArray(
                      payload.notes.monitoringSystems,
                  )
                      ? payload.notes.monitoringSystems
                      : [],
                  current_workflow: payload.currentSoftware,
                  merchant_provider: payload.merchantProvider,
                  payment_methods: payload.paymentMethods,
                  website_status: payload.websiteStatus,
                  outsourcing_status: payload.outsourcing,
                  roi_visibility: payload.roiVisibility,
                  team_size:
                      typeof payload.notes.teamSize === 'string'
                          ? payload.notes.teamSize
                          : null,
                  additional_notes:
                      typeof payload.notes.notes === 'string'
                          ? payload.notes.notes
                          : null,
              });

              return {
                  ...payload,
                  id: toString(data.id, payload.id),
                  createdAt: new Date(
                      data.created_at ?? payload.createdAt,
                  ).toISOString(),
                  updatedAt: new Date(
                      data.updated_at ?? payload.updatedAt,
                  ).toISOString(),
              } satisfies SiteLead;
          })
        : null;

    if (inserted) {
        return { source: 'supabase', data: inserted };
    }

    const leads = await appendLocalLead(payload);
    return { source: 'fallback', data: leads[0] ?? payload };
}

export async function captureLicenseBillingIntelligence(
    intelligence: LicenseBillingIntelligenceInput,
    cookies?: Parameters<typeof createSiteSupabaseServerClient>[0],
): Promise<SourceResult<SiteLicenseBillingIntelligence>> {
    const now = new Date().toISOString();
    const payload: SiteLicenseBillingIntelligence = {
        id: randomUUID(),
        licenseCode: toString(intelligence.licenseCode, ''),
        idempotencyKey: toString(intelligence.idempotencyKey, randomUUID()),
        sourceSystem: toString(intelligence.sourceSystem, 'unknown'),
        captureType: toString(intelligence.captureType, 'legacy_billing'),
        customerName: toNullableString(intelligence.customerName),
        clientCuid: toNullableString(intelligence.clientCuid),
        amount: toNullableNumber(intelligence.amount),
        status: toNullableString(intelligence.status),
        gatewayName: toNullableString(intelligence.gatewayName),
        reference: toNullableString(intelligence.reference),
        paidAt: intelligence.paidAt ? new Date(intelligence.paidAt).toISOString() : null,
        payload: intelligence.payload,
        createdAt: now,
    };

    const inserted = cookies
        ? await withSupabase(cookies, async (client) => {
              const { data, error } = await client
                  .from('license_billing_intelligence')
                  .upsert(
                      {
                          license_code: payload.licenseCode,
                          idempotency_key: payload.idempotencyKey,
                          source_system: payload.sourceSystem,
                          capture_type: payload.captureType,
                          customer_name: payload.customerName,
                          client_cuid: payload.clientCuid,
                          amount: payload.amount,
                          status: payload.status,
                          gateway_name: payload.gatewayName,
                          reference: payload.reference,
                          paid_at: payload.paidAt,
                          payload: payload.payload,
                      },
                      { onConflict: 'idempotency_key' },
                  )
                  .select('*')
                  .single();

              if (error || !data) {
                  return null;
              }

              return {
                  ...payload,
                  id: toString(data.id, payload.id),
                  createdAt: new Date(
                      data.created_at ?? payload.createdAt,
                  ).toISOString(),
              } satisfies SiteLicenseBillingIntelligence;
          })
        : null;

    if (inserted) {
        return { source: 'supabase', data: inserted };
    }

    const entries = await appendLocalLicenseBillingIntelligence(payload);
    return { source: 'fallback', data: entries[0] ?? payload };
}

export async function loadInstallAds(
    cookies?: Parameters<typeof createSiteSupabaseServerClient>[0],
): Promise<SourceResult<SiteInstallAd[]>> {
    const localAds = await readLocalInstallAds(fallbackInstallAds);

    const supabaseAds = cookies
        ? await withSupabase(cookies, async (client) => {
              const { data, error } = await client
                  .from('site_install_ads')
                  .select('*')
                  .eq('active', true)
                  .order('sort_order', { ascending: true });

              if (error || !data) {
                  return null;
              }

              return data.map((row) => ({
                  id: toString(row.id, randomUUID()),
                  eyebrow: toString(row.eyebrow, 'Featured'),
                  title: toString(row.title, 'Advertisement'),
                  summary: toNullableString(row.summary),
                  copy: toString(row.copy, ''),
                  ctaLabel: toString(
                      row.cta_label ?? row.ctaLabel,
                      'Learn more',
                  ),
                  linkUrl: toString(row.link_url ?? row.linkUrl, '/'),
                  imageUrl: toNullableString(row.image_url ?? row.imageUrl),
                  logoUrl: toNullableString(row.logo_url ?? row.logoUrl),
                  durationMs: toNumber(
                      row.duration_ms ?? row.durationMs ?? 20000,
                  ),
                  disclaimer: toNullableString(row.disclaimer),
                  active: toBoolean(row.active, true),
                  sortOrder: toNumber(row.sort_order ?? row.sortOrder),
              })) as SiteInstallAd[];
          })
        : null;

    return {
        source: supabaseAds && supabaseAds.length > 0 ? 'supabase' : 'fallback',
        data: supabaseAds && supabaseAds.length > 0 ? supabaseAds : localAds,
    };
}

export async function loadUpdateFeed(
    cookies?: Parameters<typeof createSiteSupabaseServerClient>[0],
): Promise<SourceResult<SiteUpdateFeed>> {
    const supabaseFeed = cookies
        ? await withSupabase(cookies, async (client) => {
              const { data, error } = await client
                  .from('site_update_feed')
                  .select('*')
                  .eq('id', 'default')
                  .maybeSingle();

              if (error || !data) {
                  return null;
              }

              return {
                  currentVersion: toString(
                      data.current_version ?? data.currentVersion,
                      fallbackUpdateFeed.currentVersion,
                  ),
                  currentBuild: toString(
                      data.current_build ?? data.currentBuild,
                      fallbackUpdateFeed.currentBuild,
                  ),
                  latestVersion: toString(
                      data.latest_version ?? data.latestVersion,
                      fallbackUpdateFeed.latestVersion,
                  ),
                  latestBuild: toString(
                      data.latest_build ?? data.latestBuild,
                      fallbackUpdateFeed.latestBuild,
                  ),
                  downloadUrl: toString(
                      data.download_url ?? data.downloadUrl,
                      fallbackUpdateFeed.downloadUrl,
                  ),
                  browserCompanionUrl: toNullableString(
                      data.browser_companion_url ?? data.browserCompanionUrl,
                  ),
                  notes: toNullableString(data.notes),
                  supportUrl: toNullableString(
                      data.support_url ?? data.supportUrl,
                  ),
              } satisfies SiteUpdateFeed;
          })
        : null;

    return {
        source: supabaseFeed ? 'supabase' : 'fallback',
        data: supabaseFeed ?? fallbackUpdateFeed,
    };
}

export async function loadSiteData(
    cookies?: Parameters<typeof createSiteSupabaseServerClient>[0],
): Promise<SiteDataSnapshot> {
    const [
        plans,
        reviews,
        payments,
        licenses,
        privacyRequests,
        leads,
        updateFeed,
        installAds,
    ] = await Promise.all([
        loadPlans(cookies),
        loadReviews(cookies),
        loadPayments(cookies),
        loadLicenses(cookies),
        loadPrivacyRequests(cookies),
        loadLeads(cookies),
        loadUpdateFeed(cookies),
        loadInstallAds(cookies),
    ]);

    return {
        plans: plans.data,
        reviews: reviews.data,
        payments: payments.data,
        licenses: licenses.data,
        privacyRequests: privacyRequests.data,
        leads: leads.data,
        updateFeed: updateFeed.data,
        installAds: installAds.data,
    };
}
