import {
	boolean,
	integer,
	jsonb,
	numeric,
	pgEnum,
	pgTable,
	text,
	timestamp,
	uuid,
	varchar,
} from 'drizzle-orm/pg-core';

export const leadStatusEnum = pgEnum('lead_status', ['new', 'qualified', 'converted', 'closed']);
export const licenseStatusEnum = pgEnum('license_status', ['active', 'trial', 'expired', 'revoked']);
export const paymentStatusEnum = pgEnum('payment_status', ['pending', 'paid', 'failed', 'refunded']);
export const reviewStatusEnum = pgEnum('review_status', ['pending', 'approved', 'hidden']);
export const privacyRequestStatusEnum = pgEnum('privacy_request_status', ['open', 'processing', 'completed', 'rejected']);

export const leads = pgTable('leads', {
	id: uuid('id').defaultRandom().primaryKey(),
	fullName: text('full_name').notNull(),
	email: varchar('email', { length: 255 }).notNull(),
	phone: varchar('phone', { length: 64 }),
	company: varchar('company', { length: 255 }),
	planInterest: varchar('plan_interest', { length: 128 }),
	source: varchar('source', { length: 128 }).default('site_intake').notNull(),
	status: leadStatusEnum('status').default('new').notNull(),
	notes: text('notes'),
	createdAt: timestamp('created_at', { withTimezone: true }).defaultNow().notNull(),
	updatedAt: timestamp('updated_at', { withTimezone: true }).defaultNow().notNull(),
});

export const leadIntakeAnswers = pgTable('lead_intake_answers', {
	id: uuid('id').defaultRandom().primaryKey(),
	leadId: uuid('lead_id')
		.references(() => leads.id, { onDelete: 'cascade' })
		.notNull(),
	clientCount: varchar('client_count', { length: 64 }),
	monitoringSystems: jsonb('monitoring_systems').$type<string[]>().default([]).notNull(),
	currentWorkflow: text('current_workflow'),
	merchantStatus: varchar('merchant_status', { length: 64 }),
	merchantProvider: varchar('merchant_provider', { length: 255 }),
	paymentMethods: text('payment_methods'),
	websiteStatus: varchar('website_status', { length: 64 }),
	websiteSentiment: varchar('website_sentiment', { length: 64 }),
	outsourcingStatus: varchar('outsourcing_status', { length: 64 }),
	outsourcingNotes: text('outsourcing_notes'),
	roiVisibility: varchar('roi_visibility', { length: 64 }),
	teamSize: varchar('team_size', { length: 64 }),
	switchTimeline: varchar('switch_timeline', { length: 64 }),
	biggestPain: text('biggest_pain'),
	primaryGoal: text('primary_goal'),
	additionalNotes: text('additional_notes'),
	createdAt: timestamp('created_at', { withTimezone: true }).defaultNow().notNull(),
});

export const pricingPlans = pgTable('pricing_plans', {
	id: uuid('id').defaultRandom().primaryKey(),
	key: varchar('key', { length: 64 }).notNull().unique(),
	name: varchar('name', { length: 255 }).notNull(),
	badge: varchar('badge', { length: 255 }),
	monthlyPrice: numeric('monthly_price', { precision: 10, scale: 2 }),
	listMonthlyPrice: numeric('list_monthly_price', { precision: 10, scale: 2 }),
	annualDiscountPercent: integer('annual_discount_percent'),
	yearlyPrice: numeric('yearly_price', { precision: 10, scale: 2 }),
	listYearlyPrice: numeric('list_yearly_price', { precision: 10, scale: 2 }),
	description: text('description'),
	featureList: jsonb('feature_list').$type<string[]>().default([]).notNull(),
	highlighted: boolean('highlighted').default(false).notNull(),
	sortOrder: integer('sort_order').default(0).notNull(),
	updatedAt: timestamp('updated_at', { withTimezone: true }).defaultNow().notNull(),
});

export const licenses = pgTable('licenses', {
	id: uuid('id').defaultRandom().primaryKey(),
	email: varchar('email', { length: 255 }).notNull(),
	customerName: varchar('customer_name', { length: 255 }),
	planKey: varchar('plan_key', { length: 64 }).notNull(),
	licenseKey: varchar('license_key', { length: 128 }).notNull().unique(),
	status: licenseStatusEnum('status').default('active').notNull(),
	expiresAt: timestamp('expires_at', { withTimezone: true }),
	issuedAt: timestamp('issued_at', { withTimezone: true }).defaultNow().notNull(),
	notes: text('notes'),
});

export const payments = pgTable('payments', {
	id: uuid('id').defaultRandom().primaryKey(),
	email: varchar('email', { length: 255 }).notNull(),
	customerName: varchar('customer_name', { length: 255 }),
	planKey: varchar('plan_key', { length: 64 }),
	amount: numeric('amount', { precision: 10, scale: 2 }).notNull(),
	status: paymentStatusEnum('status').default('pending').notNull(),
	provider: varchar('provider', { length: 128 }),
	externalReference: varchar('external_reference', { length: 255 }),
	metadata: jsonb('metadata').$type<Record<string, unknown>>().default({}).notNull(),
	createdAt: timestamp('created_at', { withTimezone: true }).defaultNow().notNull(),
});

export const licenseBillingIntelligence = pgTable('license_billing_intelligence', {
	id: uuid('id').defaultRandom().primaryKey(),
	licenseCode: varchar('license_code', { length: 128 }).notNull(),
	idempotencyKey: varchar('idempotency_key', { length: 128 }).notNull().unique(),
	sourceSystem: varchar('source_system', { length: 128 }).notNull(),
	captureType: varchar('capture_type', { length: 128 }).notNull(),
	customerName: varchar('customer_name', { length: 255 }),
	clientCuid: varchar('client_cuid', { length: 128 }),
	amount: numeric('amount', { precision: 10, scale: 2 }),
	status: varchar('status', { length: 128 }),
	gatewayName: varchar('gateway_name', { length: 128 }),
	reference: varchar('reference', { length: 255 }),
	paidAt: timestamp('paid_at', { withTimezone: true }),
	payload: jsonb('payload').$type<Record<string, unknown>>().default({}).notNull(),
	createdAt: timestamp('created_at', { withTimezone: true }).defaultNow().notNull(),
});

export const reviews = pgTable('reviews', {
	id: uuid('id').defaultRandom().primaryKey(),
	fullName: varchar('full_name', { length: 255 }).notNull(),
	company: varchar('company', { length: 255 }),
	rating: integer('rating').notNull(),
	quote: text('quote').notNull(),
	status: reviewStatusEnum('status').default('pending').notNull(),
	createdAt: timestamp('created_at', { withTimezone: true }).defaultNow().notNull(),
});

export const privacyRequests = pgTable('privacy_requests', {
	id: uuid('id').defaultRandom().primaryKey(),
	email: varchar('email', { length: 255 }).notNull(),
	requestType: varchar('request_type', { length: 128 }).notNull(),
	details: text('details'),
	status: privacyRequestStatusEnum('status').default('open').notNull(),
	createdAt: timestamp('created_at', { withTimezone: true }).defaultNow().notNull(),
});
