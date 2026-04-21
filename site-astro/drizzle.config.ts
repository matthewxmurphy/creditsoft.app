import { defineConfig } from 'drizzle-kit';

const databaseUrl = process.env.DATABASE_URL;

export default defineConfig({
	out: './drizzle/migrations',
	schema: './drizzle/schema.ts',
	dialect: 'postgresql',
	dbCredentials: {
		url: databaseUrl ?? '',
	},
	verbose: true,
	strict: true,
});
