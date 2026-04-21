import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const rootDir = resolve(fileURLToPath(new URL('../../../', import.meta.url)));
const supabaseDataDir = resolve(rootDir, 'supabase', 'data');
const leadsPath = resolve(supabaseDataDir, 'leads.json');
const licenseBillingIntelligencePath = resolve(supabaseDataDir, 'license-billing-intelligence.json');
const installAdsPath = fileURLToPath(new URL('./install-ads.json', import.meta.url));

async function readJsonFile<T>(path: string, fallback: T): Promise<T> {
	try {
		const raw = await readFile(path, 'utf8');
		return JSON.parse(raw) as T;
	} catch {
		return fallback;
	}
}

async function writeJsonFile(path: string, value: unknown): Promise<void> {
	await mkdir(dirname(path), { recursive: true });
	await writeFile(path, `${JSON.stringify(value, null, 2)}\n`, 'utf8');
}

export async function readLocalLeads<T>(fallback: T): Promise<T> {
	return readJsonFile(leadsPath, fallback);
}

export async function writeLocalLeads(value: unknown): Promise<void> {
	await writeJsonFile(leadsPath, value);
}

export async function appendLocalLead<T extends { id: string }>(lead: T): Promise<T[]> {
	const leads = await readLocalLeads<T[]>([]);
	const next = [lead, ...leads];
	await writeLocalLeads(next);
	return next;
}

export async function appendLocalLicenseBillingIntelligence<T extends { id: string; idempotencyKey: string }>(entry: T): Promise<T[]> {
	const entries = await readJsonFile<T[]>(licenseBillingIntelligencePath, []);
	const next = [
		entry,
		...entries.filter((item) => item.idempotencyKey !== entry.idempotencyKey),
	];
	await writeJsonFile(licenseBillingIntelligencePath, next);
	return next;
}

export async function readLocalInstallAds<T>(fallback: T): Promise<T> {
	return readJsonFile(installAdsPath, fallback);
}
