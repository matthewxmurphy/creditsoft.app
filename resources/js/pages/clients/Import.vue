<script setup lang="ts">
import { computed, reactive } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { faFileImport, faTableColumns, faUsersGear, faWandSparkles } from '@fortawesome/free-solid-svg-icons';
import ClientsWorkspaceNav from '@/components/creditsoft/ClientsWorkspaceNav.vue';

const props = defineProps<{
    staff: Array<{
        id: number;
        name: string;
        role_label?: string | null;
        assigned_client_count: number;
    }>;
    assignmentModes: Array<{
        value: string;
        label: string;
        description: string;
    }>;
    unassignedCount: number;
}>();

const defaultSingleAssignee = props.staff[0]?.id?.toString() ?? '';
const defaultTeam = props.staff.map((member) => member.id.toString());

const assignment = reactive({
    assignment_mode: props.assignmentModes[0]?.value ?? 'source_match',
    assigned_to: defaultSingleAssignee,
    assignment_user_ids: defaultTeam,
});

const importForm = useForm<{
    import_file: File | null;
    assignment_mode: string;
    assigned_to: string;
    assignment_user_ids: string[];
}>({
    import_file: null,
    assignment_mode: assignment.assignment_mode,
    assigned_to: assignment.assigned_to,
    assignment_user_ids: [...assignment.assignment_user_ids],
});

const assignForm = useForm<{
    assignment_mode: string;
    assigned_to: string;
    assignment_user_ids: string[];
}>({
    assignment_mode: assignment.assignment_mode,
    assigned_to: assignment.assigned_to,
    assignment_user_ids: [...assignment.assignment_user_ids],
});

const hasAssignableStaff = computed(() => props.staff.length > 0);

const syncAssignment = () => {
    importForm.assignment_mode = assignment.assignment_mode;
    importForm.assigned_to = assignment.assigned_to;
    importForm.assignment_user_ids = [...assignment.assignment_user_ids];

    assignForm.assignment_mode = assignment.assignment_mode;
    assignForm.assigned_to = assignment.assigned_to;
    assignForm.assignment_user_ids = [...assignment.assignment_user_ids];
};

const toggleAssignmentMember = (memberId: string, checked: boolean) => {
    if (checked) {
        if (! assignment.assignment_user_ids.includes(memberId)) {
            assignment.assignment_user_ids = [...assignment.assignment_user_ids, memberId];
        }

        syncAssignment();

        return;
    }

    assignment.assignment_user_ids = assignment.assignment_user_ids.filter((candidate) => candidate !== memberId);
    syncAssignment();
};

const submitImport = () => {
    syncAssignment();
    importForm.post('/clients/import/disputefox', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => importForm.reset('import_file'),
    });
};

const assignCurrentUnassigned = () => {
    syncAssignment();
    assignForm.post('/clients/assign-unassigned', {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Client Import" />

    <h1 class="sr-only">Client import</h1>

    <div class="space-y-8">
        <ClientsWorkspaceNav mode="import" />

        <div class="grid gap-8 xl:grid-cols-[0.92fr_1.08fr]">
            <section class="space-y-4">
                <div class="rounded-[28px] border border-stone-300/70 bg-stone-50/80 p-5">
                    <div class="border-b border-stone-300/70 pb-4">
                        <p class="text-[11px] font-medium uppercase tracking-[0.32em] text-stone-500">
                            Assignment rules
                        </p>
                        <p class="mt-2 text-sm leading-6 text-stone-600">
                            Unassigned is not automation. Set the ownership rule here and CreditSoft will use it for imported dossiers and any current unassigned files you want to fix.
                        </p>
                    </div>

                    <div class="mt-4 space-y-4">
                        <div class="space-y-2">
                            <label class="block text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Assignment mode</label>
                            <select v-model="assignment.assignment_mode" class="h-10 w-full rounded-2xl border border-stone-300 bg-white px-3 text-sm text-stone-900" @change="syncAssignment">
                                <option v-for="mode in assignmentModes" :key="mode.value" :value="mode.value">
                                    {{ mode.label }}
                                </option>
                            </select>
                            <p class="text-xs leading-5 text-stone-500">
                                {{ assignmentModes.find((mode) => mode.value === assignment.assignment_mode)?.description }}
                            </p>
                        </div>

                        <div v-if="assignment.assignment_mode !== 'split_evenly'" class="space-y-2">
                            <label class="block text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">
                                {{ assignment.assignment_mode === 'source_match' ? 'Fallback owner' : 'Client owner' }}
                            </label>
                            <select v-model="assignment.assigned_to" class="h-10 w-full rounded-2xl border border-stone-300 bg-white px-3 text-sm text-stone-900" :disabled="!hasAssignableStaff" @change="syncAssignment">
                                <option v-for="member in staff" :key="member.id" :value="member.id.toString()">
                                    {{ member.name }} · {{ member.role_label ?? 'Staff' }} · {{ member.assigned_client_count }} assigned
                                </option>
                            </select>
                            <p v-if="importForm.errors.assigned_to || assignForm.errors.assigned_to" class="text-xs text-rose-700">
                                {{ importForm.errors.assigned_to ?? assignForm.errors.assigned_to }}
                            </p>
                        </div>

                        <div v-else class="space-y-3">
                            <div>
                                <label class="block text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Balanced team</label>
                                <p class="mt-1 text-xs leading-5 text-stone-500">
                                    Pick the staff and admin members CreditSoft should rotate through, starting with the lightest queue.
                                </p>
                            </div>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <label
                                    v-for="member in staff"
                                    :key="member.id"
                                    class="flex items-start gap-3 rounded-2xl border border-stone-300 bg-white px-3 py-3 text-sm text-stone-700"
                                >
                                    <input
                                        :checked="assignment.assignment_user_ids.includes(member.id.toString())"
                                        class="mt-1 h-4 w-4 rounded border-stone-400 text-stone-950"
                                        type="checkbox"
                                        @change="toggleAssignmentMember(member.id.toString(), ($event.target as HTMLInputElement).checked)"
                                    />
                                    <span class="block">
                                        <span class="block font-medium text-stone-900">{{ member.name }}</span>
                                        <span class="block text-xs text-stone-500">{{ member.role_label ?? 'Staff' }} · {{ member.assigned_client_count }} assigned</span>
                                    </span>
                                </label>
                            </div>
                            <p v-if="importForm.errors.assignment_user_ids || assignForm.errors.assignment_user_ids" class="text-xs text-rose-700">
                                {{ importForm.errors.assignment_user_ids ?? assignForm.errors.assignment_user_ids }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="rounded-[28px] border border-stone-300/70 bg-stone-50/80 p-5">
                    <div class="border-b border-stone-300/70 pb-4">
                        <p class="text-[11px] font-medium uppercase tracking-[0.32em] text-stone-500">
                            Client import
                        </p>
                        <p class="mt-2 text-sm leading-6 text-stone-600">
                            Bring in a client export here instead of crowding the roster page. We preserve the original source headers so the mapper lane can get smarter without losing context.
                        </p>
                    </div>

                    <form class="mt-4 space-y-4" @submit.prevent="submitImport">
                        <label class="block text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">
                            XLSX export
                        </label>
                        <input
                            accept=".xlsx"
                            class="block w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 text-sm text-stone-700 shadow-sm file:mr-4 file:rounded-full file:border-0 file:bg-stone-900 file:px-4 file:py-2 file:text-xs file:font-semibold file:uppercase file:tracking-[0.18em] file:text-white hover:file:bg-stone-800"
                            type="file"
                            @change="importForm.import_file = ($event.target as HTMLInputElement).files?.[0] ?? null"
                        />

                        <p v-if="importForm.errors.import_file" class="text-sm text-red-600">
                            {{ importForm.errors.import_file }}
                        </p>

                        <button type="submit" class="rounded-full bg-stone-950 px-4 py-2 text-xs font-medium uppercase tracking-[0.22em] text-stone-50 transition hover:bg-stone-800" :disabled="importForm.processing || !hasAssignableStaff">
                            {{ importForm.processing ? 'Importing…' : 'Import client file' }}
                        </button>
                    </form>
                </div>
            </section>

            <section class="space-y-4">
                <div class="rounded-[28px] border border-amber-200/80 bg-amber-50/85 p-5">
                    <div class="flex items-center gap-3">
                        <FontAwesomeIcon :icon="faFileImport" class="text-sm text-amber-700" />
                        <p class="text-[11px] font-medium uppercase tracking-[0.32em] text-amber-800">What lands now</p>
                    </div>
                    <p class="mt-3 text-sm leading-6 text-stone-700">
                        First-pass import already maps name, email, phone, address, state, assignee, sales rep, and progress into CreditSoft. The difference now is that every imported dossier also lands with a real owner.
                    </p>
                </div>

                <div class="rounded-[28px] border border-stone-300/70 bg-white/90 p-5">
                    <div class="flex items-center gap-3">
                        <FontAwesomeIcon :icon="faUsersGear" class="text-sm text-stone-500" />
                        <p class="text-[11px] font-medium uppercase tracking-[0.32em] text-stone-500">Fix current unassigned</p>
                    </div>
                    <p class="mt-3 text-sm leading-6 text-stone-600">
                        {{ unassignedCount }} client {{ unassignedCount === 1 ? 'dossier still needs an owner.' : 'dossiers still need an owner.' }}
                    </p>
                    <button
                        type="button"
                        class="mt-4 rounded-full border border-stone-300 px-4 py-2 text-xs font-medium uppercase tracking-[0.22em] text-stone-700 transition hover:border-stone-400 hover:bg-stone-100"
                        :disabled="assignForm.processing || !hasAssignableStaff || unassignedCount === 0"
                        @click="assignCurrentUnassigned"
                    >
                        {{ assignForm.processing ? 'Assigning…' : 'Assign current unassigned clients' }}
                    </button>
                </div>

                <div class="rounded-[28px] border border-stone-300/70 bg-white/90 p-5">
                    <div class="flex items-center gap-3">
                        <FontAwesomeIcon :icon="faTableColumns" class="text-sm text-stone-500" />
                        <p class="text-[11px] font-medium uppercase tracking-[0.32em] text-stone-500">Mapper lane next</p>
                    </div>
                    <p class="mt-3 text-sm leading-6 text-stone-600">
                        Every import keeps the raw source headers in metadata so the next mapper pass can suggest matches, let a human confirm them, and stop losing unfamiliar columns.
                    </p>
                </div>

                <div class="rounded-[28px] border border-stone-300/70 bg-white/90 p-5">
                    <div class="flex items-center gap-3">
                        <FontAwesomeIcon :icon="faWandSparkles" class="text-sm text-stone-500" />
                        <p class="text-[11px] font-medium uppercase tracking-[0.32em] text-stone-500">Why this page exists</p>
                    </div>
                    <p class="mt-3 text-sm leading-6 text-stone-600">
                        The client roster should stay focused on live dossiers. Import work belongs in its own lane under the page gear so the roster and creation flow stay clean.
                    </p>
                </div>
            </section>
        </div>
    </div>
</template>
