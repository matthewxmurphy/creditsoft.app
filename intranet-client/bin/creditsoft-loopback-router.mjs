#!/usr/bin/env node

import { spawn } from 'node:child_process';
import { existsSync, readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const clientScript = path.join(__dirname, 'creditsoft-intranet-client.mjs');

const splitList = (value) => String(value || '')
    .split(',')
    .map((item) => item.trim())
    .filter(Boolean);

const readToken = () => {
    if (process.env.CREDITSOFT_API_TOKEN) {
        return process.env.CREDITSOFT_API_TOKEN;
    }

    const tokenFile = process.env.CREDITSOFT_API_TOKEN_FILE;

    if (!tokenFile || !existsSync(tokenFile)) {
        return '';
    }

    return readFileSync(tokenFile, 'utf8').trim();
};

const bases = [
    'http://127.0.0.1:8001/api/v1',
    ...splitList(process.env.CREDITSOFT_API_BASES),
];

const args = [
    clientScript,
    '--serve',
    '--no-open',
    '--save',
    '--strategy',
    process.env.CREDITSOFT_NODE_SELECTION_STRATEGY || 'ordered',
    '--listen',
    process.env.CREDITSOFT_ROUTER_LISTEN || '127.0.0.1',
    '--listen-port',
    process.env.CREDITSOFT_ROUTER_PORT || '8877',
];

if (process.env.CREDITSOFT_CRM_BASE_URL) {
    args.push('--crm-base', process.env.CREDITSOFT_CRM_BASE_URL);
}

for (const base of [...new Set(bases)]) {
    args.push('--base', base);
}

const child = spawn(process.execPath, args, {
    env: {
        ...process.env,
        CREDITSOFT_API_TOKEN: readToken(),
    },
    stdio: 'inherit',
});

child.on('exit', (code, signal) => {
    if (signal) {
        process.kill(process.pid, signal);
        return;
    }

    process.exitCode = code ?? 0;
});
