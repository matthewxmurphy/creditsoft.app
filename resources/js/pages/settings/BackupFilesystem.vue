<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { faFolderOpen, faHardDrive, faLink, faNetworkWired, faTrashCan } from '@fortawesome/free-solid-svg-icons';
import ConnectivityBrandMark from '@/components/creditsoft/ConnectivityBrandMark.vue';
import DashboardWorkspaceNav from '@/components/creditsoft/DashboardWorkspaceNav.vue';
import { chooseLocalBackupDirectory, clearLocalBackupDirectory, getLocalBackupDirectoryState, supportsLocalBackupDirectoryPicker } from '@/lib/localBackupDirectory';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Backup / File System',
                href: '/settings/filesystem',
            },
        ],
    },
});

type WasabiSettings = {
    enabled: boolean;
    access_key_id: string;
    secret_key: string;
    bucket: string;
    region: string;
    endpoint: string;
    use_path_style_endpoint: boolean;
    has_access_key_id: boolean;
    has_secret_key: boolean;
    masked_access_key_id?: string | null;
    masked_secret_key?: string | null;
};

type DropboxSettings = {
    enabled: boolean;
    app_key: string;
    app_secret: string;
    refresh_token: string;
    root_folder: string;
    sync_mode: string;
    has_app_secret: boolean;
    has_refresh_token: boolean;
    masked_app_secret?: string | null;
    masked_refresh_token?: string | null;
};

type GoogleDriveSettings = {
    enabled: boolean;
    client_id: string;
    client_secret: string;
    refresh_token: string;
    folder_id: string;
    shared_drive_id: string;
    sync_mode: string;
    has_client_secret: boolean;
    has_refresh_token: boolean;
    masked_client_secret?: string | null;
    masked_refresh_token?: string | null;
};

type ClusterPeer = {
    label: string;
    base_url: string;
    license_key: string;
    enabled: boolean;
};

const props = defineProps<{
    settings: {
        archive_destination: string;
        external_handoff_lane: string;
        local: {
            private_path: string;
            backup_temp_path: string;
            archive_disks: string[];
        };
        cluster: {
            enabled: boolean;
            office_label: string;
            shared_secret: string;
            has_shared_secret: boolean;
            masked_shared_secret?: string | null;
            peers: ClusterPeer[];
        };
        wasabi: WasabiSettings;
        dropbox: DropboxSettings;
        google_drive: GoogleDriveSettings;
    };
}>();

const form = useForm({
    archive_destination: props.settings.archive_destination ?? 'local',
    external_handoff_lane: props.settings.external_handoff_lane ?? 'none',
    cluster: {
        enabled: props.settings.cluster.enabled ?? false,
        office_label: props.settings.cluster.office_label ?? 'CreditSoft Office',
        shared_secret: props.settings.cluster.shared_secret ?? '',
        peers: (props.settings.cluster.peers ?? []).map((peer) => ({
            label: peer.label ?? '',
            base_url: peer.base_url ?? '',
            license_key: peer.license_key ?? '',
            enabled: peer.enabled ?? true,
        })),
    },
    wasabi: {
        access_key_id: props.settings.wasabi.access_key_id ?? '',
        secret_key: props.settings.wasabi.secret_key ?? '',
        bucket: props.settings.wasabi.bucket ?? '',
        region: props.settings.wasabi.region ?? 'us-east-1',
        endpoint: props.settings.wasabi.endpoint ?? 'https://s3.us-east-1.wasabisys.com',
        use_path_style_endpoint: props.settings.wasabi.use_path_style_endpoint ?? true,
    },
    dropbox: {
        enabled: props.settings.dropbox.enabled ?? false,
        app_key: props.settings.dropbox.app_key ?? '',
        app_secret: props.settings.dropbox.app_secret ?? '',
        refresh_token: props.settings.dropbox.refresh_token ?? '',
        root_folder: props.settings.dropbox.root_folder ?? '/CreditSoft',
        sync_mode: props.settings.dropbox.sync_mode ?? 'exports',
    },
    google_drive: {
        enabled: props.settings.google_drive.enabled ?? false,
        client_id: props.settings.google_drive.client_id ?? '',
        client_secret: props.settings.google_drive.client_secret ?? '',
        refresh_token: props.settings.google_drive.refresh_token ?? '',
        folder_id: props.settings.google_drive.folder_id ?? '',
        shared_drive_id: props.settings.google_drive.shared_drive_id ?? '',
        sync_mode: props.settings.google_drive.sync_mode ?? 'client_documents',
    },
});

const dropboxReady = Boolean(
    props.settings.dropbox.enabled
    && props.settings.dropbox.app_key
    && props.settings.dropbox.has_app_secret
    && props.settings.dropbox.has_refresh_token,
);

const googleDriveReady = Boolean(
    props.settings.google_drive.enabled
    && props.settings.google_drive.client_id
    && props.settings.google_drive.has_client_secret
    && props.settings.google_drive.has_refresh_token,
);

const clusterReady = Boolean(
    props.settings.cluster.enabled
    && props.settings.cluster.has_shared_secret
    && (props.settings.cluster.peers ?? []).some((peer) => peer.enabled && peer.base_url),
);

const localDirectorySupported = supportsLocalBackupDirectoryPicker();
const localDirectoryName = ref<string | null>(null);
const localDirectoryPermission = ref<'granted' | 'denied' | 'prompt' | 'missing' | 'unsupported'>(
    localDirectorySupported ? 'missing' : 'unsupported',
);

const syncLocalDirectoryState = async () => {
    const state = await getLocalBackupDirectoryState();
    localDirectoryName.value = state.name;
    localDirectoryPermission.value = state.permission;
};

const chooseLocalDirectory = async () => {
    const state = await chooseLocalBackupDirectory();
    localDirectoryName.value = state.name;
    localDirectoryPermission.value = state.permission;
};

const clearLocalDirectory = async () => {
    await clearLocalBackupDirectory();
    localDirectoryName.value = null;
    localDirectoryPermission.value = localDirectorySupported ? 'missing' : 'unsupported';
};

const addClusterPeer = () => {
    form.cluster.peers.push({
        label: '',
        base_url: '',
        license_key: '',
        enabled: true,
    });
};

const removeClusterPeer = (index: number) => {
    form.cluster.peers.splice(index, 1);
};

onMounted(() => {
    void syncLocalDirectoryState();
});

const submit = () => {
    form.put('/settings/filesystem', {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Backup / File System" />

    <div class="space-y-6">
        <DashboardWorkspaceNav />

        <section class="space-y-2">
            <h1 class="text-xl font-semibold text-stone-950">Backup / file system</h1>
            <p class="max-w-3xl text-sm leading-6 text-stone-600">
                Keep archive backups honest and local-first. Wasabi is the real off-machine archive lane today. Dropbox and Google Drive can be staged as external file lanes for exports, handoff, and future sync work.
            </p>
        </section>

        <form class="space-y-6" @submit.prevent="submit">
            <section class="overflow-hidden rounded-[28px] border border-stone-300/70 bg-white/95">
                <div class="border-b border-stone-200/80 px-6 py-5">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div class="flex items-center gap-3">
                            <FontAwesomeIcon :icon="faFolderOpen" class="text-[28px] text-stone-700" />
                            <div>
                                <p class="text-[11px] font-medium uppercase tracking-[0.28em] text-stone-500">Local export folder</p>
                                <h2 class="mt-2 text-lg font-semibold text-stone-950">Pick the USB, external drive, or synced folder for one-click local backups</h2>
                                <p class="mt-2 max-w-3xl text-sm leading-6 text-stone-600">
                                    CreditSoft can keep creating the archive locally, then the installed Chrome app can drop a copy straight into a chosen folder on this machine. That can be a flash drive, external SSD, or another synced local folder.
                                </p>
                            </div>
                        </div>
                        <span class="inline-flex items-center rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em]" :class="localDirectoryName ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-amber-200 bg-amber-50 text-amber-800'">
                            {{ localDirectoryName ? 'Folder ready' : 'Choose folder' }}
                        </span>
                    </div>
                </div>
                <div class="space-y-5 px-6 py-6">
                    <div class="rounded-[24px] border px-4 py-4 text-sm leading-6" :class="localDirectoryName ? 'border-emerald-200 bg-emerald-50 text-emerald-950' : 'border-amber-200 bg-amber-50 text-amber-950'">
                        <p class="font-medium">
                            {{ localDirectoryName ? 'CreditSoft has a preferred local export folder.' : 'Choose a local export folder to let footer backups land somewhere outside the app storage.' }}
                        </p>
                        <p v-if="localDirectorySupported">
                            {{ localDirectoryName ? `Current folder: ${localDirectoryName}. Local footer backups can write there after the archive is created.` : 'This browser supports the local folder picker, so you can browse to a USB drive or another local folder and keep that choice for the installed app.' }}
                        </p>
                        <p v-else>
                            This browser build does not expose the local folder picker. CreditSoft can still create the backup archive, but the browser will fall back to a download instead of writing directly to a chosen folder.
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <button
                            v-if="localDirectorySupported"
                            type="button"
                            class="inline-flex items-center gap-2 rounded-full border border-stone-300 bg-white px-4 py-2 text-sm font-medium text-stone-800 transition hover:border-stone-500 hover:text-stone-950"
                            @click="chooseLocalDirectory"
                        >
                            <FontAwesomeIcon :icon="faFolderOpen" />
                            {{ localDirectoryName ? 'Choose different folder' : 'Choose folder' }}
                        </button>
                        <button
                            v-if="localDirectorySupported && localDirectoryName"
                            type="button"
                            class="inline-flex items-center gap-2 rounded-2xl border border-stone-300 bg-white px-4 py-2 text-sm font-medium text-stone-700 transition hover:border-stone-500 hover:text-stone-950"
                            @click="clearLocalDirectory"
                        >
                            <FontAwesomeIcon :icon="faTrashCan" />
                            Clear folder
                        </button>
                        <span class="text-xs leading-5 text-stone-500">
                            Permission: {{ localDirectoryPermission }}
                        </span>
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-[28px] border border-stone-300/70 bg-white/95">
                <div class="border-b border-stone-200/80 px-6 py-5">
                    <p class="text-[11px] font-medium uppercase tracking-[0.28em] text-stone-500">Archive strategy</p>
                    <h2 class="mt-2 text-lg font-semibold text-stone-950">Choose the real archive lane and the optional handoff lane</h2>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-stone-600">
                        Archive backups are the office safety net. External handoff lanes are where files can be mirrored, exported, or staged for a future sync connector.
                    </p>
                </div>
                <div class="grid gap-4 px-6 py-6 lg:grid-cols-2">
                    <label class="space-y-2">
                        <span class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Archive destination</span>
                        <select v-model="form.archive_destination" class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900">
                            <option value="local">Local only</option>
                            <option value="wasabi">Wasabi archive</option>
                        </select>
                    </label>
                    <label class="space-y-2">
                        <span class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">External handoff lane</span>
                        <select v-model="form.external_handoff_lane" class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900">
                            <option value="none">None</option>
                            <option value="dropbox">Dropbox</option>
                            <option value="google_drive">Google Drive</option>
                        </select>
                    </label>
                    <div class="rounded-[24px] border border-stone-200 bg-stone-50 px-4 py-4">
                        <div class="flex items-center gap-3">
                            <FontAwesomeIcon :icon="faHardDrive" class="text-[24px] text-stone-700" />
                            <div>
                                <p class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Local lane</p>
                                <p class="mt-1 text-sm font-semibold text-stone-900">Always available on this machine</p>
                            </div>
                        </div>
                        <p class="mt-3 break-all text-sm leading-6 text-stone-900">{{ props.settings.local.private_path }}</p>
                    </div>
                    <div class="rounded-[24px] border border-stone-200 bg-stone-50 px-4 py-4">
                        <div class="flex items-center gap-3">
                            <ConnectivityBrandMark brand="wasabi" large />
                            <div>
                                <p class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Backup runtime</p>
                                <p class="mt-1 text-sm font-semibold text-stone-900">Current temp path and active disks</p>
                            </div>
                        </div>
                        <p class="mt-3 break-all text-sm leading-6 text-stone-900">{{ props.settings.local.backup_temp_path }}</p>
                        <p class="mt-3 text-xs leading-5 text-stone-500">Current archive disks: {{ props.settings.local.archive_disks.join(', ') }}</p>
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-[28px] border border-stone-300/70 bg-white/95">
                <div class="border-b border-stone-200/80 px-6 py-5">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div class="flex items-center gap-3">
                            <ConnectivityBrandMark brand="wasabi" large />
                            <div>
                                <h2 class="text-lg font-semibold text-stone-950">Working archive destination</h2>
                                <p class="mt-1 max-w-3xl text-sm leading-6 text-stone-600">
                                    This is the real backup target the office can use today. Fill in the bucket and credentials here and CreditSoft can keep local plus Wasabi archives.
                                </p>
                            </div>
                        </div>
                        <span class="inline-flex items-center rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em]" :class="props.settings.wasabi.enabled ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-amber-200 bg-amber-50 text-amber-800'">
                            {{ props.settings.wasabi.enabled ? 'Ready' : 'Needs setup' }}
                        </span>
                    </div>
                </div>
                <div class="space-y-5 px-6 py-6">
                    <div class="rounded-[24px] border px-4 py-4 text-sm leading-6" :class="props.settings.wasabi.enabled ? 'border-emerald-200 bg-emerald-50 text-emerald-950' : 'border-amber-200 bg-amber-50 text-amber-950'">
                        <p class="font-medium">{{ props.settings.wasabi.enabled ? 'Wasabi is ready for archive backups.' : 'Fill in the Wasabi credentials to turn the archive lane on.' }}</p>
                        <p>
                            {{ props.settings.wasabi.enabled ? 'Archive backups can target Wasabi once the backup destination is set to Wasabi.' : 'CreditSoft needs the access key, secret key, and bucket before it can move archive backups off the machine.' }}
                        </p>
                    </div>
                    <div class="grid gap-4 lg:grid-cols-2">
                        <label class="space-y-2">
                            <span class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Access key ID</span>
                            <input v-model="form.wasabi.access_key_id" type="password" class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" placeholder="Paste new value only if replacing" />
                            <p v-if="props.settings.wasabi.has_access_key_id" class="text-xs leading-5 text-stone-500">Saved on file: {{ props.settings.wasabi.masked_access_key_id }}</p>
                        </label>
                        <label class="space-y-2">
                            <span class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Secret key</span>
                            <input v-model="form.wasabi.secret_key" type="password" class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" placeholder="Paste new value only if replacing" />
                            <p v-if="props.settings.wasabi.has_secret_key" class="text-xs leading-5 text-stone-500">Saved on file: {{ props.settings.wasabi.masked_secret_key }}</p>
                        </label>
                        <label class="space-y-2">
                            <span class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Bucket</span>
                            <input v-model="form.wasabi.bucket" type="text" class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" />
                        </label>
                        <label class="space-y-2">
                            <span class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Region</span>
                            <input v-model="form.wasabi.region" type="text" class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" />
                        </label>
                        <label class="space-y-2 lg:col-span-2">
                            <span class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Endpoint</span>
                            <input v-model="form.wasabi.endpoint" type="text" class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" />
                        </label>
                        <label class="flex items-start gap-3 rounded-[24px] border border-stone-200 bg-stone-50 px-4 py-4 lg:col-span-2">
                            <input v-model="form.wasabi.use_path_style_endpoint" type="checkbox" class="mt-1 h-4 w-4 rounded border-stone-400 text-stone-950" />
                            <span class="space-y-1 text-sm text-stone-700">
                                <span class="block font-medium text-stone-900">Use path-style endpoint</span>
                                <span class="block leading-6">Leave this on for the normal Wasabi S3-compatible lane unless the bucket setup specifically requires otherwise.</span>
                            </span>
                        </label>
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-[28px] border border-stone-300/70 bg-white/95">
                <div class="border-b border-stone-200/80 px-6 py-5">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div class="flex items-center gap-3">
                            <ConnectivityBrandMark brand="dropbox" large />
                            <div>
                                <h2 class="text-lg font-semibold text-stone-950">External handoff lane</h2>
                                <p class="mt-1 max-w-3xl text-sm leading-6 text-stone-600">
                                    Use Dropbox when the office wants a familiar shared folder for exports, backups copied out of band, or handoff to non-technical staff.
                                </p>
                            </div>
                        </div>
                        <span class="inline-flex items-center rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em]" :class="dropboxReady ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-amber-200 bg-amber-50 text-amber-800'">
                            {{ dropboxReady ? 'Ready' : 'Needs setup' }}
                        </span>
                    </div>
                </div>
                <div class="space-y-5 px-6 py-6">
                    <div class="rounded-[24px] border px-4 py-4 text-sm leading-6" :class="dropboxReady ? 'border-emerald-200 bg-emerald-50 text-emerald-950' : 'border-amber-200 bg-amber-50 text-amber-950'">
                        <p class="font-medium">{{ dropboxReady ? 'Dropbox is ready for staged handoffs.' : 'Fill in the Dropbox app credentials to make the handoff lane usable.' }}</p>
                        <p>
                            {{ dropboxReady ? 'CreditSoft can stage backup and export handoffs into the Dropbox lane.' : 'CreditSoft needs the app key, app secret, and refresh token before Dropbox can stage office handoffs.' }}
                        </p>
                    </div>
                    <label class="flex items-start gap-3 rounded-[24px] border border-stone-200 bg-stone-50 px-4 py-4">
                        <input v-model="form.dropbox.enabled" type="checkbox" class="mt-1 h-4 w-4 rounded border-stone-400 text-stone-950" />
                        <span class="space-y-1 text-sm text-stone-700">
                            <span class="block font-medium text-stone-900">Enable Dropbox lane</span>
                            <span class="block leading-6">Keep Dropbox ready as an external handoff or export destination. This stores the connector details even though Wasabi remains the stronger archive lane.</span>
                        </span>
                    </label>
                    <div class="grid gap-4 lg:grid-cols-2">
                        <label class="space-y-2">
                            <span class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">App key</span>
                            <input v-model="form.dropbox.app_key" type="text" class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" />
                        </label>
                        <label class="space-y-2">
                            <span class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Root folder</span>
                            <input v-model="form.dropbox.root_folder" type="text" class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" />
                        </label>
                        <label class="space-y-2">
                            <span class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">App secret</span>
                            <input v-model="form.dropbox.app_secret" type="password" class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" placeholder="Paste new value only if replacing" />
                            <p v-if="props.settings.dropbox.has_app_secret" class="text-xs leading-5 text-stone-500">Saved on file: {{ props.settings.dropbox.masked_app_secret }}</p>
                        </label>
                        <label class="space-y-2">
                            <span class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Refresh token</span>
                            <input v-model="form.dropbox.refresh_token" type="password" class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" placeholder="Paste new value only if replacing" />
                            <p v-if="props.settings.dropbox.has_refresh_token" class="text-xs leading-5 text-stone-500">Saved on file: {{ props.settings.dropbox.masked_refresh_token }}</p>
                        </label>
                        <label class="space-y-2 lg:col-span-2">
                            <span class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Dropbox sync mode</span>
                            <select v-model="form.dropbox.sync_mode" class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900">
                                <option value="exports">Exports only</option>
                                <option value="client_documents">Client documents</option>
                                <option value="everything">Everything</option>
                            </select>
                        </label>
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-[28px] border border-stone-300/70 bg-white/95">
                <div class="border-b border-stone-200/80 px-6 py-5">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div class="flex items-center gap-3">
                            <ConnectivityBrandMark brand="google_drive" large />
                            <div>
                                <h2 class="text-lg font-semibold text-stone-950">Shared drive and document mirror lane</h2>
                                <p class="mt-1 max-w-3xl text-sm leading-6 text-stone-600">
                                    Use Google Drive when the office already works out of shared drives and wants client documents or exports mirrored into a familiar folder structure.
                                </p>
                            </div>
                        </div>
                        <span class="inline-flex items-center rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em]" :class="googleDriveReady ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-amber-200 bg-amber-50 text-amber-800'">
                            {{ googleDriveReady ? 'Ready' : 'Needs setup' }}
                        </span>
                    </div>
                </div>
                <div class="space-y-5 px-6 py-6">
                    <div class="rounded-[24px] border px-4 py-4 text-sm leading-6" :class="googleDriveReady ? 'border-emerald-200 bg-emerald-50 text-emerald-950' : 'border-amber-200 bg-amber-50 text-amber-950'">
                        <p class="font-medium">{{ googleDriveReady ? 'Google Drive is ready for staged handoffs.' : 'Fill in the Google Drive OAuth details to make the shared-drive lane usable.' }}</p>
                        <p>
                            {{ googleDriveReady ? 'CreditSoft can stage backup and export handoffs into the Google Drive lane.' : 'CreditSoft needs the client ID, client secret, and refresh token before Google Drive can stage office handoffs.' }}
                        </p>
                    </div>
                    <label class="flex items-start gap-3 rounded-[24px] border border-stone-200 bg-stone-50 px-4 py-4">
                        <input v-model="form.google_drive.enabled" type="checkbox" class="mt-1 h-4 w-4 rounded border-stone-400 text-stone-950" />
                        <span class="space-y-1 text-sm text-stone-700">
                            <span class="block font-medium text-stone-900">Enable Google Drive lane</span>
                            <span class="block leading-6">Store the connector details now so a shared-drive export or sync lane can be switched on without digging back through old admin notes.</span>
                        </span>
                    </label>
                    <div class="grid gap-4 lg:grid-cols-2">
                        <label class="space-y-2">
                            <span class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Client ID</span>
                            <input v-model="form.google_drive.client_id" type="text" class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" />
                        </label>
                        <label class="space-y-2">
                            <span class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Folder ID</span>
                            <input v-model="form.google_drive.folder_id" type="text" class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" />
                        </label>
                        <label class="space-y-2">
                            <span class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Client secret</span>
                            <input v-model="form.google_drive.client_secret" type="password" class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" placeholder="Paste new value only if replacing" />
                            <p v-if="props.settings.google_drive.has_client_secret" class="text-xs leading-5 text-stone-500">Saved on file: {{ props.settings.google_drive.masked_client_secret }}</p>
                        </label>
                        <label class="space-y-2">
                            <span class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Shared drive ID</span>
                            <input v-model="form.google_drive.shared_drive_id" type="text" class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" placeholder="Optional" />
                        </label>
                        <label class="space-y-2 lg:col-span-2">
                            <span class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Refresh token</span>
                            <input v-model="form.google_drive.refresh_token" type="password" class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" placeholder="Paste new value only if replacing" />
                            <p v-if="props.settings.google_drive.has_refresh_token" class="text-xs leading-5 text-stone-500">Saved on file: {{ props.settings.google_drive.masked_refresh_token }}</p>
                        </label>
                        <label class="space-y-2 lg:col-span-2">
                            <span class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Google Drive sync mode</span>
                            <select v-model="form.google_drive.sync_mode" class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900">
                                <option value="exports">Exports only</option>
                                <option value="client_documents">Client documents</option>
                                <option value="everything">Everything</option>
                            </select>
                        </label>
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-[28px] border border-stone-300/70 bg-white/95">
                <div class="border-b border-stone-200/80 px-6 py-5">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div class="flex items-center gap-3">
                            <FontAwesomeIcon :icon="faNetworkWired" class="text-[28px] text-stone-700" />
                            <div>
                                <h2 class="text-lg font-semibold text-stone-950">Cluster backup mirror</h2>
                                <p class="mt-1 max-w-3xl text-sm leading-6 text-stone-600">
                                    Cluster-license offices can mirror database snapshots to one another over the tailnet. CreditSoft sends the zip after a backup runs and stores incoming peer copies in a separate cluster archive folder.
                                </p>
                            </div>
                        </div>
                        <span class="inline-flex items-center rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em]" :class="clusterReady ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-amber-200 bg-amber-50 text-amber-800'">
                            {{ clusterReady ? 'Ready' : 'Needs setup' }}
                        </span>
                    </div>
                </div>
                <div class="space-y-5 px-6 py-6">
                    <div class="rounded-[24px] border px-4 py-4 text-sm leading-6" :class="clusterReady ? 'border-emerald-200 bg-emerald-50 text-emerald-950' : 'border-amber-200 bg-amber-50 text-amber-950'">
                        <p class="font-medium">{{ clusterReady ? 'Cluster backup mirroring is ready.' : 'Turn on the cluster lane, set a shared secret, and add at least one peer office.' }}</p>
                        <p>
                            {{ clusterReady ? 'When any footer backup runs, CreditSoft will try to mirror the snapshot to the enabled peer offices.' : 'Each office should use the same shared secret and point peers at the tailnet or local-office URL where that office can receive cluster backup copies.' }}
                        </p>
                    </div>
                    <label class="flex items-start gap-3 rounded-[24px] border border-stone-200 bg-stone-50 px-4 py-4">
                        <input v-model="form.cluster.enabled" type="checkbox" class="mt-1 h-4 w-4 rounded border-stone-400 text-stone-950" />
                        <span class="space-y-1 text-sm text-stone-700">
                            <span class="block font-medium text-stone-900">Enable cluster mirror lane</span>
                            <span class="block leading-6">Use this when the same licensed customer runs more than one office machine and wants each office to hold a peer copy of the database backups.</span>
                        </span>
                    </label>
                    <div class="grid gap-4 lg:grid-cols-2">
                        <label class="space-y-2">
                            <span class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">This office label</span>
                            <input v-model="form.cluster.office_label" type="text" class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" />
                        </label>
                        <label class="space-y-2">
                            <span class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Shared cluster secret</span>
                            <input v-model="form.cluster.shared_secret" type="password" class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" placeholder="Paste new value only if replacing" />
                            <p v-if="props.settings.cluster.has_shared_secret" class="text-xs leading-5 text-stone-500">Saved on file: {{ props.settings.cluster.masked_shared_secret }}</p>
                        </label>
                    </div>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <p class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Peer offices</p>
                            <button
                                type="button"
                                class="inline-flex items-center gap-2 rounded-full border border-stone-300 bg-white px-4 py-2 text-sm font-medium text-stone-800 transition hover:border-stone-500 hover:text-stone-950"
                                @click="addClusterPeer"
                            >
                                <FontAwesomeIcon :icon="faLink" />
                                Add peer
                            </button>
                        </div>
                        <div v-if="form.cluster.peers.length === 0" class="rounded-[24px] border border-dashed border-stone-300 bg-stone-50 px-4 py-4 text-sm leading-6 text-stone-600">
                            No peer offices added yet. Add the other office base URL and, if you want cleaner tracking, its license key.
                        </div>
                        <div v-for="(peer, index) in form.cluster.peers" :key="`cluster-peer-${index}`" class="space-y-4 rounded-[24px] border border-stone-200 bg-stone-50 px-4 py-4">
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-sm font-semibold text-stone-900">{{ peer.label || `Peer office ${index + 1}` }}</p>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-2xl border border-stone-300 bg-white px-3 py-1.5 text-sm font-medium text-stone-700 transition hover:border-stone-500 hover:text-stone-950"
                                    @click="removeClusterPeer(index)"
                                >
                                    <FontAwesomeIcon :icon="faTrashCan" />
                                    Remove
                                </button>
                            </div>
                            <div class="grid gap-4 lg:grid-cols-2">
                                <label class="space-y-2">
                                    <span class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Peer label</span>
                                    <input v-model="peer.label" type="text" class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" placeholder="Phoenix office" />
                                </label>
                                <label class="space-y-2">
                                    <span class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Peer license key</span>
                                    <input v-model="peer.license_key" type="text" class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" placeholder="Optional" />
                                </label>
                                <label class="space-y-2 lg:col-span-2">
                                    <span class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Peer base URL</span>
                                    <input v-model="peer.base_url" type="url" class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900" placeholder="http://creditsoft-office.tailnet-name.ts.net:8001" />
                                </label>
                                <label class="flex items-start gap-3 rounded-[24px] border border-stone-200 bg-white px-4 py-4 lg:col-span-2">
                                    <input v-model="peer.enabled" type="checkbox" class="mt-1 h-4 w-4 rounded border-stone-400 text-stone-950" />
                                    <span class="space-y-1 text-sm text-stone-700">
                                        <span class="block font-medium text-stone-900">Mirror backups to this peer</span>
                                        <span class="block leading-6">CreditSoft will only try this peer after a backup runs when the peer is enabled and the base URL is present.</span>
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <div class="flex justify-end">
                <button type="submit" class="rounded-full bg-stone-950 px-6 py-3 text-sm font-medium text-white transition hover:bg-stone-800 disabled:cursor-not-allowed disabled:opacity-60" :disabled="form.processing">
                    {{ form.processing ? 'Saving...' : 'Save backup settings' }}
                </button>
            </div>
        </form>
    </div>
</template>
