<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { dashboard, login, register } from '@/routes';

withDefaults(
    defineProps<{
        canRegister: boolean;
        preview: {
            origin: string;
            login_url: string;
            installer_url: string;
            api_url: string;
            metrics: {
                clients: number;
                open_violations: number;
                open_tasks: number;
                mrr: number;
            };
        };
    }>(),
    {
        canRegister: true,
    },
);

const modules = [
    'Client dossiers with current score, notes, letters, briefs, tasks, and audit trail',
    'Three-bureau comparison workspace with Metro 2 scan queue and mismatch flags',
    'AI lanes for drafting, summaries, and operator-side casework support',
    'Connectivity controls for Tailscale, ngrok, OpenAPI, and partner intake flows',
];

const nextUp = [
    'Portal-safe client updates, notes, cycles, and violations through the partner API',
    'Website or lead-manager syncs that write into CreditSoft with an audit trail',
    'Browser ingestion helpers for report capture and evidence intake',
];
</script>

<template>
    <Head title="Preview">
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="anonymous" />
        <link
            href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Manrope:wght@400;500;600;700;800&display=swap"
            rel="stylesheet"
        />
    </Head>

    <div class="min-h-screen bg-[#f7f2e7] text-stone-950" style="font-family: 'Manrope', sans-serif;">
        <div class="border-b border-stone-300/70 bg-[#fbf7ef]">
            <div class="mx-auto flex w-full max-w-7xl items-center justify-between px-6 py-4 lg:px-10">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.38em] text-stone-500">CreditSoft Intranet</p>
                    <p class="mt-1 text-sm font-medium text-stone-700">Local-first credit operations</p>
                </div>

                <nav class="flex items-center gap-3 text-sm">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="dashboard()"
                        class="rounded-full border border-stone-300 bg-white px-4 py-2 font-medium text-stone-900 transition hover:border-stone-500"
                    >
                        Open dashboard
                    </Link>
                    <template v-else>
                        <Link
                            :href="login()"
                            class="rounded-full border border-stone-300 bg-white px-4 py-2 font-medium text-stone-900 transition hover:border-stone-500"
                        >
                            Login
                        </Link>
                        <Link
                            v-if="canRegister"
                            :href="register()"
                            class="rounded-full bg-stone-950 px-4 py-2 font-medium text-white transition hover:bg-stone-800"
                        >
                            Register
                        </Link>
                    </template>
                </nav>
            </div>
        </div>

        <main>
            <section class="border-b border-stone-300/70 bg-[radial-gradient(circle_at_top_left,_rgba(240,188,58,0.18),_transparent_32%),linear-gradient(180deg,#fbf7ef_0%,#f7f2e7_100%)]">
                <div class="mx-auto grid w-full max-w-7xl gap-12 px-6 py-14 lg:grid-cols-[1.1fr_0.9fr] lg:px-10 lg:py-20">
                    <div class="space-y-7">
                        <div class="space-y-3">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.38em] text-amber-700">Day-One Preview</p>
                            <h1
                                class="max-w-4xl text-5xl leading-[0.95] tracking-[-0.04em] text-stone-950 md:text-6xl"
                                style="font-family: 'Fraunces', serif;"
                            >
                                A private credit repair control panel that already feels like a real product.
                            </h1>
                            <p class="max-w-2xl text-lg leading-8 text-stone-700">
                                CreditSoft now has live client workspaces, bureau comparison, Metro 2 review, AI drafting lanes, CFO reporting, and
                                a partner API, all running locally with Tailscale and ngrok support.
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-3">
                            <a
                                :href="preview.login_url"
                                class="rounded-full bg-stone-950 px-5 py-3 text-sm font-semibold text-white transition hover:bg-stone-800"
                            >
                                Open the portal
                            </a>
                            <a
                                :href="preview.installer_url"
                                class="rounded-full border border-stone-300 bg-white px-5 py-3 text-sm font-semibold text-stone-900 transition hover:border-stone-500"
                            >
                                View installer
                            </a>
                            <a
                                :href="preview.api_url"
                                class="rounded-full border border-stone-300 bg-white px-5 py-3 text-sm font-semibold text-stone-900 transition hover:border-stone-500"
                            >
                                View API index
                            </a>
                        </div>

                        <div class="grid gap-6 border-t border-stone-300/80 pt-8 md:grid-cols-4">
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-[0.3em] text-stone-500">Clients</p>
                                <p class="mt-2 text-3xl font-semibold text-stone-950">{{ preview.metrics.clients }}</p>
                            </div>
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-[0.3em] text-stone-500">Open Violations</p>
                                <p class="mt-2 text-3xl font-semibold text-stone-950">{{ preview.metrics.open_violations }}</p>
                            </div>
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-[0.3em] text-stone-500">Open Tasks</p>
                                <p class="mt-2 text-3xl font-semibold text-stone-950">{{ preview.metrics.open_tasks }}</p>
                            </div>
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-[0.3em] text-stone-500">MRR</p>
                                <p class="mt-2 text-3xl font-semibold text-stone-950">${{ preview.metrics.mrr.toLocaleString() }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="border-l border-stone-300/80 pl-0 lg:pl-10">
                        <div class="space-y-8">
                            <div class="space-y-3">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.34em] text-stone-500">Already Working</p>
                                <ul class="space-y-4 text-base leading-7 text-stone-800">
                                    <li v-for="module in modules" :key="module" class="border-b border-stone-200/80 pb-4">
                                        {{ module }}
                                    </li>
                                </ul>
                            </div>

                            <div class="space-y-3">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.34em] text-stone-500">Share This Build</p>
                                <div class="space-y-3 text-sm leading-6 text-stone-700">
                                    <p>
                                        Send friends the root ngrok URL for the preview page, or the login URL if you want them to step into the real
                                        operator shell.
                                    </p>
                                    <p class="break-all font-medium text-stone-950">{{ preview.origin }}</p>
                                    <p class="break-all">{{ preview.login_url }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="mx-auto grid w-full max-w-7xl gap-12 px-6 py-14 lg:grid-cols-[0.95fr_1.05fr] lg:px-10 lg:py-16">
                <div class="space-y-4">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.34em] text-stone-500">What This Covers</p>
                    <h2 class="text-3xl leading-tight tracking-[-0.03em] text-stone-950" style="font-family: 'Fraunces', serif;">
                        Enough surface area to prove the product direction, not just a mockup.
                    </h2>
                    <p class="max-w-xl text-base leading-8 text-stone-700">
                        The current build already walks through installer, login, client overview, compare, violations, notes, letters, briefs,
                        CFO, connectivity, and API docs. That makes it strong enough for feedback from operators, managers, and integration partners.
                    </p>
                </div>

                <div class="grid gap-8 md:grid-cols-2">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.3em] text-stone-500">Operator routes</p>
                        <ul class="mt-4 space-y-3 text-sm leading-7 text-stone-800">
                            <li>`/dashboard` live queue and KPI surface</li>
                            <li>`/clients/{id}` dossier with notes, letters, briefs, and captures</li>
                            <li>`/clients/{id}/compare` bureau alignment and Metro 2 review</li>
                            <li>`/clients/{id}/violations` queued findings with evidence</li>
                        </ul>
                    </div>
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.3em] text-stone-500">System routes</p>
                        <ul class="mt-4 space-y-3 text-sm leading-7 text-stone-800">
                            <li>`/install` split-screen installer with license and feed rail</li>
                            <li>`/settings/connectivity` Tailscale, ngrok, and partner API controls</li>
                            <li>`/settings/api` embedded Swagger explorer inside the app shell</li>
                            <li>`/api/v1` public API index for live integration discovery</li>
                        </ul>
                    </div>
                </div>
            </section>

            <section class="border-t border-stone-300/70 bg-[#fbf7ef]">
                <div class="mx-auto grid w-full max-w-7xl gap-10 px-6 py-14 lg:grid-cols-[1fr_1fr] lg:px-10 lg:py-16">
                    <div class="space-y-4">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.34em] text-stone-500">Next Up</p>
                        <ul class="space-y-4 text-base leading-7 text-stone-800">
                            <li v-for="item in nextUp" :key="item" class="border-b border-stone-200/80 pb-4">
                                {{ item }}
                            </li>
                        </ul>
                    </div>

                    <div class="space-y-4">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.34em] text-stone-500">Why It Matters</p>
                        <p class="text-base leading-8 text-stone-700">
                            This is already beyond spreadsheet replacement. It is the start of a local-first credit operations system with private case
                            data, controlled external integrations, and enough structure to automate work without losing auditability.
                        </p>
                        <div class="flex flex-wrap gap-3 pt-2">
                            <a
                                :href="preview.login_url"
                                class="rounded-full bg-stone-950 px-5 py-3 text-sm font-semibold text-white transition hover:bg-stone-800"
                            >
                                Show the portal
                            </a>
                            <a
                                :href="preview.installer_url"
                                class="rounded-full border border-stone-300 bg-white px-5 py-3 text-sm font-semibold text-stone-900 transition hover:border-stone-500"
                            >
                                Show the installer
                            </a>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>
</template>
