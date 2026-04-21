import fs from 'node:fs';
import path from 'node:path';
import postgres from 'postgres';

const root = path.resolve(process.cwd());
const envPath = path.join(root, '.env.local');

if (fs.existsSync(envPath)) {
	for (const rawLine of fs.readFileSync(envPath, 'utf8').split('\n')) {
		const line = rawLine.trim();
		if (!line || line.startsWith('#')) continue;
		const separatorIndex = line.indexOf('=');
		if (separatorIndex === -1) continue;
		const key = line.slice(0, separatorIndex).trim();
		let value = line.slice(separatorIndex + 1).trim();
		if ((value.startsWith('"') && value.endsWith('"')) || (value.startsWith("'") && value.endsWith("'"))) {
			value = value.slice(1, -1);
		}
		if (!(key in process.env)) {
			process.env[key] = value;
		}
	}
}

if (!process.env.DATABASE_URL) {
	throw new Error('DATABASE_URL is missing from .env.local');
}

const sql = postgres(process.env.DATABASE_URL, { prepare: false });

const plans = [
	{
		id: '9ac9e69e-f2cb-4f73-a73d-8aa9196f3021',
		key: 'starter',
		name: 'Starter',
		badge: 'Best for small offices',
		monthlyPrice: '149.00',
		listMonthlyPrice: '189.00',
		annualDiscountPercent: 34,
		yearlyPrice: '1188.00',
		listYearlyPrice: '1788.00',
		description: 'A cleaner website, intake, and update lane for offices getting serious about their brand.',
		featureList: ['Public site', 'Office fit intake', 'Update feed access', 'Basic admin'],
		highlighted: false,
		sortOrder: 1,
	},
	{
		id: 'fb0ac18b-370e-4f76-8d63-941e7203fd2d',
		key: 'growth',
		name: 'Growth',
		badge: 'Most popular',
		monthlyPrice: '249.00',
		listMonthlyPrice: '329.00',
		annualDiscountPercent: 34,
		yearlyPrice: '1980.00',
		listYearlyPrice: '2988.00',
		description: 'The sales-site plus the stronger office story: reviews, renewals, downloads, and a tighter admin rail.',
		featureList: ['Everything in Starter', 'Review manager', 'Licenses and renewals', 'Partner ad feed'],
		highlighted: true,
		sortOrder: 2,
	},
	{
		id: '32594bf4-4cea-4c4f-b251-81d3fddff40f',
		key: 'enterprise',
		name: 'Enterprise',
		badge: 'For larger teams',
		monthlyPrice: '399.00',
		listMonthlyPrice: '499.00',
		annualDiscountPercent: 31,
		yearlyPrice: '3288.00',
		listYearlyPrice: '4788.00',
		description: 'For multi-user operations that want the full branded website and update lane with more control.',
		featureList: ['Everything in Growth', 'More staff seats', 'Priority migrations', 'Custom admin options'],
		highlighted: false,
		sortOrder: 3,
	},
];

const reviews = [
	{
		id: '9c0a4864-354c-4db0-bc9d-0776f534ec0c',
		fullName: 'Marilyn Perry',
		company: 'Marilyn Perry Credit',
		rating: 5,
		quote: 'The site finally looks like a real software company and not a stitched-together waitlist.',
		status: 'approved',
	},
	{
		id: '1fd80d1d-70d1-4777-9fff-4867f8e15c30',
		fullName: 'Avery Cole',
		company: 'Cole Workflow Group',
		rating: 5,
		quote: 'The intake flow asks the right questions, and the admin side actually reflects how offices operate.',
		status: 'approved',
	},
	{
		id: '52f97662-d0dd-4589-b361-c0ea5297ab47',
		fullName: 'Jordan Blake',
		company: 'Blake Financial',
		rating: 4,
		quote: 'The updates lane and renewals finally feel branded instead of tacked on.',
		status: 'pending',
	},
];

const payments = [
	{
		id: '0bdd0b47-4d68-4e42-806c-53f4695ff283',
		email: 'marilyn@example.com',
		customerName: 'Marilyn Perry',
		planKey: 'growth',
		amount: '249.00',
		status: 'paid',
		provider: 'zelle',
		externalReference: 'ZELLE-APR-001',
		metadata: { memo: 'Growth monthly' },
	},
	{
		id: '2b7a009b-d588-452d-b7e4-bb8fa68f9e97',
		email: 'avery@example.com',
		customerName: 'Avery Cole',
		planKey: 'starter',
		amount: '1188.00',
		status: 'paid',
		provider: 'ach',
		externalReference: 'ACH-APR-002',
		metadata: { memo: 'Starter annual' },
	},
];

const licenses = [
	{
		id: 'c04db429-2d5c-4c94-a446-c2fa91f87d7e',
		email: 'marilyn@example.com',
		customerName: 'Marilyn Perry',
		planKey: 'growth',
		licenseKey: 'LIC-1042',
		status: 'active',
		expiresAt: '2026-05-12T00:00:00.000Z',
		notes: 'Growth monthly license',
	},
	{
		id: '13c0e8b6-3d3d-4633-8e8d-5117fb65ef53',
		email: 'avery@example.com',
		customerName: 'Avery Cole',
		planKey: 'starter',
		licenseKey: 'LIC-1048',
		status: 'active',
		expiresAt: '2026-06-01T00:00:00.000Z',
		notes: 'Starter annual license',
	},
	{
		id: 'dd8d2406-0d5d-47de-b24b-1f4df5a81aab',
		email: 'jordan@example.com',
		customerName: 'Jordan Blake',
		planKey: 'enterprise',
		licenseKey: 'LIC-1051',
		status: 'trial',
		expiresAt: '2026-04-30T00:00:00.000Z',
		notes: 'Pending upgrade decision',
	},
];

const privacyRequests = [
	{
		id: '7a5744b2-c07f-4637-8c45-83d8dfdbb83d',
		email: 'avery@example.com',
		requestType: 'Delete my info',
		details: 'Requested deletion of sales-site contact history.',
		status: 'open',
	},
	{
		id: 'aab3d6db-48ec-4952-822f-27a5ae8f23cd',
		email: 'marilyn@example.com',
		requestType: 'Privacy export',
		details: 'Needs a copy of the website intake answers and billing history.',
		status: 'processing',
	},
];

await sql.begin(async (tx) => {
	for (const plan of plans) {
		await tx`
			insert into pricing_plans (
				id, key, name, badge, monthly_price, list_monthly_price, annual_discount_percent,
				yearly_price, list_yearly_price, description, feature_list, highlighted, sort_order
			) values (
				${plan.id}::uuid, ${plan.key}, ${plan.name}, ${plan.badge}, ${plan.monthlyPrice}, ${plan.listMonthlyPrice},
				${plan.annualDiscountPercent}, ${plan.yearlyPrice}, ${plan.listYearlyPrice}, ${plan.description},
				${JSON.stringify(plan.featureList)}::jsonb, ${plan.highlighted}, ${plan.sortOrder}
			)
			on conflict (id) do update set
				key = excluded.key,
				name = excluded.name,
				badge = excluded.badge,
				monthly_price = excluded.monthly_price,
				list_monthly_price = excluded.list_monthly_price,
				annual_discount_percent = excluded.annual_discount_percent,
				yearly_price = excluded.yearly_price,
				list_yearly_price = excluded.list_yearly_price,
				description = excluded.description,
				feature_list = excluded.feature_list,
				highlighted = excluded.highlighted,
				sort_order = excluded.sort_order,
				updated_at = now()
		`;
	}

	for (const review of reviews) {
		await tx`
			insert into reviews (id, full_name, company, rating, quote, status)
			values (${review.id}::uuid, ${review.fullName}, ${review.company}, ${review.rating}, ${review.quote}, ${review.status})
			on conflict (id) do update set
				full_name = excluded.full_name,
				company = excluded.company,
				rating = excluded.rating,
				quote = excluded.quote,
				status = excluded.status
		`;
	}

	for (const payment of payments) {
		await tx`
			insert into payments (id, email, customer_name, plan_key, amount, status, provider, external_reference, metadata)
			values (
				${payment.id}::uuid, ${payment.email}, ${payment.customerName}, ${payment.planKey},
				${payment.amount}, ${payment.status}, ${payment.provider}, ${payment.externalReference},
				${JSON.stringify(payment.metadata)}::jsonb
			)
			on conflict (id) do update set
				email = excluded.email,
				customer_name = excluded.customer_name,
				plan_key = excluded.plan_key,
				amount = excluded.amount,
				status = excluded.status,
				provider = excluded.provider,
				external_reference = excluded.external_reference,
				metadata = excluded.metadata
		`;
	}

	for (const license of licenses) {
		await tx`
			insert into licenses (id, email, customer_name, plan_key, license_key, status, expires_at, notes)
			values (
				${license.id}::uuid, ${license.email}, ${license.customerName}, ${license.planKey},
				${license.licenseKey}, ${license.status}, ${license.expiresAt}, ${license.notes}
			)
			on conflict (id) do update set
				email = excluded.email,
				customer_name = excluded.customer_name,
				plan_key = excluded.plan_key,
				license_key = excluded.license_key,
				status = excluded.status,
				expires_at = excluded.expires_at,
				notes = excluded.notes
		`;
	}

	for (const request of privacyRequests) {
		await tx`
			insert into privacy_requests (id, email, request_type, details, status)
			values (${request.id}::uuid, ${request.email}, ${request.requestType}, ${request.details}, ${request.status})
			on conflict (id) do update set
				email = excluded.email,
				request_type = excluded.request_type,
				details = excluded.details,
				status = excluded.status
		`;
	}
});

const [planRows, reviewRows, paymentRows, licenseRows, privacyRows] = await Promise.all([
	sql`select count(*)::int as count from pricing_plans`,
	sql`select count(*)::int as count from reviews`,
	sql`select count(*)::int as count from payments`,
	sql`select count(*)::int as count from licenses`,
	sql`select count(*)::int as count from privacy_requests`,
]);

console.log(JSON.stringify({
	pricing_plans: planRows[0]?.count ?? 0,
	reviews: reviewRows[0]?.count ?? 0,
	payments: paymentRows[0]?.count ?? 0,
	licenses: licenseRows[0]?.count ?? 0,
	privacy_requests: privacyRows[0]?.count ?? 0,
}, null, 2));

await sql.end();
