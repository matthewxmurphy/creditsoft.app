export const ADMIN_LOGIN_EMAIL = (
	import.meta.env.ADMIN_LOGIN_EMAIL ||
	import.meta.env.SITE_ADMIN_EMAIL ||
	'mmurphy@creditsoft.app'
)
	.trim()
	.toLowerCase();

export const ADMIN_PASSWORD_HASH =
	import.meta.env.ADMIN_LOGIN_PASSWORD_HASH ||
	"1f7b59d0147858b46b2161793618917ac8e91b40186ceb8ed45e3dae28419b79";

export const ADMIN_LOGIN_PASSWORD_HASH = ADMIN_PASSWORD_HASH;

export const ADMIN_AUTH_MODE = import.meta.env.ADMIN_AUTH_MODE || "fallback";
export const ADMIN_SESSION_KEY = "creditsoft-admin-session";
export const ADMIN_SESSION_TTL_MS = 1000 * 60 * 60 * 12;

export type AdminPlan = {
	name: string;
	price: string;
	listPrice: string;
	discount: string;
	label: string;
	features: string[];
};

export type AdminLead = {
	name: string;
	email: string;
	company: string;
	status: string;
	source: string;
	lastTouch: string;
};

export type AdminLicense = {
	id: string;
	holder: string;
	plan: string;
	status: string;
	expires: string;
	action: string;
};

export type AdminPayment = {
	when: string;
	customer: string;
	amount: string;
	status: string;
	method: string;
};

export type AdminRequest = {
	type: string;
	who: string;
	status: string;
	due: string;
};

export type AdminReview = {
	name: string;
	source: string;
	rating: string;
	status: string;
	reply: string;
};

export const adminNavigation = [
	{ id: "overview", label: "Overview", note: "Daily ops" },
	{ id: "plans", label: "Plans", note: "Pricing and sales" },
	{ id: "leads", label: "Leads", note: "Pipeline and intake" },
	{ id: "licenses", label: "Licenses", note: "Issue and extend" },
	{ id: "payments", label: "Payments", note: "History and status" },
	{ id: "legal", label: "Legal / privacy", note: "Requests and exports" },
	{ id: "reviews", label: "Reviews", note: "Queue and replies" },
] as const;

export const adminPlans: AdminPlan[] = [
	{
		name: "Starter",
		price: "$89.95",
		listPrice: "$119.95",
		discount: "25% off",
		label: "Entry plan",
		features: ["One workspace", "Lead intake", "Website support"],
	},
	{
		name: "Pro",
		price: "$199.95",
		listPrice: "$266.60",
		discount: "25% off",
		label: "Most popular",
		features: ["Client ops", "Letters", "Automation lanes"],
	},
	{
		name: "Enterprise",
		price: "$299.95",
		listPrice: "$399.95",
		discount: "25% off",
		label: "High-volume",
		features: ["Multi-staff", "Billing control", "Priority support"],
	},
];

export const adminLeads: AdminLead[] = [
	{
		name: "Marilyn Perry",
		email: "marilyn@example.com",
		company: "Perry File Ops",
		status: "Qualified",
		source: "Homepage",
		lastTouch: "2h ago",
	},
	{
		name: "Avery Cole",
		email: "avery@example.com",
		company: "Cole Credit Works",
		status: "New",
		source: "Pricing",
		lastTouch: "14m ago",
	},
	{
		name: "Jordan Blake",
		email: "jordan@example.com",
		company: "Blake Financial",
		status: "Needs follow-up",
		source: "Subscribe",
		lastTouch: "Today",
	},
];

export const adminLicenses: AdminLicense[] = [
	{
		id: "LIC-1042",
		holder: "Marilyn Perry",
		plan: "Pro",
		status: "Active",
		expires: "May 12, 2026",
		action: "Extend 30 days",
	},
	{
		id: "LIC-1048",
		holder: "Avery Cole",
		plan: "Starter",
		status: "Active",
		expires: "Jun 01, 2026",
		action: "Issue renewal",
	},
	{
		id: "LIC-1051",
		holder: "Jordan Blake",
		plan: "Enterprise",
		status: "Pending payment",
		expires: "Pending",
		action: "Send invoice",
	},
];

export const adminPayments: AdminPayment[] = [
	{ when: "Today", customer: "Marilyn Perry", amount: "$199.95", status: "Captured", method: "Card" },
	{ when: "Yesterday", customer: "Avery Cole", amount: "$89.95", status: "Pending", method: "ACH" },
	{ when: "Apr 11", customer: "Jordan Blake", amount: "$299.95", status: "Failed", method: "Card" },
];

export const adminRequests: AdminRequest[] = [
	{ type: "Delete my info", who: "Avery Cole", status: "Open", due: "24h" },
	{ type: "Privacy export", who: "Marilyn Perry", status: "Ready", due: "Now" },
	{ type: "Legal review", who: "Jordan Blake", status: "Needs attention", due: "Today" },
];

export const adminReviews: AdminReview[] = [
	{
		name: "CreditSoft",
		source: "Google",
		rating: "5.0",
		status: "Waiting reply",
		reply: "Draft a short gratitude response and link to support.",
	},
	{
		name: "Avery Cole",
		source: "Trustpilot",
		rating: "4.0",
		status: "Published",
		reply: "Thank the reviewer and note the turnaround improvement.",
	},
	{
		name: "Marilyn Perry",
		source: "Google",
		rating: "5.0",
		status: "Queued",
		reply: "Suggest a case-study permission follow-up.",
	},
];

export const adminSummary = {
	plans: adminPlans.length,
	leads: adminLeads.length,
	licenses: adminLicenses.filter((entry) => entry.status === "Active").length,
	payments: adminPayments.length,
	requests: adminRequests.filter((entry) => entry.status !== "Ready").length,
	reviews: adminReviews.length,
};

export function adminSessionPayload(email: string) {
	return {
		email,
		issuedAt: Date.now(),
		expiresAt: Date.now() + ADMIN_SESSION_TTL_MS,
		mode: ADMIN_AUTH_MODE,
	};
}
