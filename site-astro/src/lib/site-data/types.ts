export type SiteDataSource = 'supabase' | 'fallback';

export interface SitePlan {
    id: string;
    slug: string;
    name: string;
    badge: string | null;
    description: string | null;
    monthlyPrice: number;
    saleMonthlyPrice: number | null;
    annualPrice: number;
    annualListPrice: number | null;
    annualDiscountPercent: number | null;
    features: string[];
    ctaLabel: string;
    highlighted: boolean;
    active: boolean;
    sortOrder: number;
}

export interface SiteReview {
    id: string;
    reviewerName: string;
    reviewerTitle: string | null;
    company: string | null;
    quote: string;
    rating: number;
    source: string | null;
    active: boolean;
    sortOrder: number;
}

export interface SitePayment {
    id: string;
    customerName: string;
    amount: number;
    status: string;
    method: string;
    memo: string | null;
    paidAt: string;
    source: string | null;
}

export interface SiteLicenseBillingIntelligence {
    id: string;
    licenseCode: string;
    idempotencyKey: string;
    sourceSystem: string;
    captureType: string;
    customerName: string | null;
    clientCuid: string | null;
    amount: number | null;
    status: string | null;
    gatewayName: string | null;
    reference: string | null;
    paidAt: string | null;
    payload: Record<string, unknown>;
    createdAt: string;
}

export interface LicenseBillingIntelligenceInput {
    licenseCode: string;
    idempotencyKey: string;
    sourceSystem: string;
    captureType: string;
    customerName?: string | null;
    clientCuid?: string | null;
    amount?: number | null;
    status?: string | null;
    gatewayName?: string | null;
    reference?: string | null;
    paidAt?: string | null;
    payload: Record<string, unknown>;
}

export interface SiteLicense {
    id: string;
    email: string;
    customerName: string | null;
    planKey: string;
    licenseKey: string;
    status: string;
    expiresAt: string | null;
    issuedAt: string;
    notes: string | null;
}

export interface SitePrivacyRequest {
    id: string;
    email: string;
    requestType: string;
    details: string | null;
    status: string;
    createdAt: string;
}

export interface SiteLead {
    id: string;
    name: string;
    email: string;
    phone: string | null;
    company: string | null;
    source: string;
    status: string;
    planInterest: string | null;
    currentSoftware: string | null;
    clientCount: number | null;
    outsourcing: string | null;
    merchantProvider: string | null;
    paymentMethods: string | null;
    websiteStatus: string | null;
    roiVisibility: string | null;
    notes: Record<string, unknown>;
    createdAt: string;
    updatedAt: string;
}

export interface SiteUpdateFeed {
    currentVersion: string;
    currentBuild: string;
    latestVersion: string;
    latestBuild: string;
    downloadUrl: string;
    browserCompanionUrl: string | null;
    notes: string | null;
    supportUrl: string | null;
}

export interface SiteInstallAd {
    id: string;
    eyebrow: string;
    title: string;
    summary?: string | null;
    copy: string;
    ctaLabel: string;
    linkUrl: string;
    imageUrl: string | null;
    logoUrl?: string | null;
    durationMs?: number;
    disclaimer?: string | null;
    active: boolean;
    sortOrder: number;
}

export interface SiteDataSnapshot {
    plans: SitePlan[];
    reviews: SiteReview[];
    payments: SitePayment[];
    licenses: SiteLicense[];
    privacyRequests: SitePrivacyRequest[];
    leads: SiteLead[];
    updateFeed: SiteUpdateFeed;
    installAds: SiteInstallAd[];
}

export interface LeadCaptureInput {
    name: string;
    email: string;
    phone?: string | null;
    company?: string | null;
    source?: string | null;
    status?: string | null;
    planInterest?: string | null;
    currentSoftware?: string | null;
    clientCount?: number | null;
    outsourcing?: string | null;
    merchantProvider?: string | null;
    paymentMethods?: string | null;
    websiteStatus?: string | null;
    roiVisibility?: string | null;
    notes?: Record<string, unknown>;
}
