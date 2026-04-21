<script setup lang="ts">
import { computed, reactive } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import {
    faPenToSquare,
    faShieldHalved,
    faTrashCan,
    faUsers,
} from '@fortawesome/free-solid-svg-icons';
import { Input } from '@/components/ui/input';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Accounts Manager',
                href: '/settings/users',
            },
        ],
    },
});

type RoleOption = {
    value: string;
    label: string;
    description?: string | null;
    readonly: boolean;
    workspace: boolean;
    ops: boolean;
    areas: string[];
};

type ManagerOption = {
    id: number;
    name: string;
    email: string;
    role_label?: string | null;
};

type ManagedUser = {
    id: number;
    name: string;
    email: string;
    roles: string[];
    role_labels: string[];
    primary_role?: string | null;
    primary_role_label?: string | null;
    manager_id?: number | null;
    manager_name?: string | null;
    direct_reports_count: number;
    gravatar_url: string;
    last_login_at?: string | null;
    last_seen_at?: string | null;
    active_api_keys_count: number;
    read_only_demo: boolean;
    is_current_user: boolean;
};

const props = defineProps<{
    roles: RoleOption[];
    manager_options: ManagerOption[];
    users: ManagedUser[];
}>();

const page = usePage<{
    auth: {
        role?: string | null;
        can_manage_users: boolean;
        can_view_user_directory: boolean;
        can_edit_users: boolean;
        read_only_demo: boolean;
    };
}>();

const defaultRole = props.roles[0]?.value ?? 'staff';
const roleOrder = props.roles.map((role) => role.value);

const form = useForm({
    name: '',
    email: '',
    roles: [defaultRole],
    manager_id: null as number | null,
    password: '',
});

const roleDrafts = reactive<Record<number, string[]>>(
    Object.fromEntries(
        props.users.map((user) => [user.id, [...user.roles]]),
    ),
);

const managerDrafts = reactive<Record<number, number | null>>(
    Object.fromEntries(
        props.users.map((user) => [user.id, user.manager_id ?? null]),
    ),
);

const canViewUserDirectory = computed(() =>
    page.props.auth.can_view_user_directory
    || ['owner_admin', 'admin', 'demo_admin', 'manager'].includes(page.props.auth.role ?? ''),
);
const canEditUsers = computed(() =>
    page.props.auth.can_edit_users
    || ['owner_admin', 'admin'].includes(page.props.auth.role ?? ''),
);
const isReadOnlyDemo = computed(() => page.props.auth.read_only_demo);
const roleLookup = computed(() => new Map(props.roles.map((role) => [role.value, role])));

const orderedRoles = (roles: string[]) =>
    [...roles]
        .filter(Boolean)
        .filter((role, index, array) => array.indexOf(role) === index)
        .sort((left, right) => {
            const leftIndex = roleOrder.indexOf(left);
            const rightIndex = roleOrder.indexOf(right);

            if (leftIndex === -1 && rightIndex === -1) {
                return left.localeCompare(right);
            }

            if (leftIndex === -1) {
                return 1;
            }

            if (rightIndex === -1) {
                return -1;
            }

            return leftIndex - rightIndex;
        });

const sameRoles = (left: string[], right: string[]) =>
    JSON.stringify(orderedRoles(left)) === JSON.stringify(orderedRoles(right));

const selectedRoleMeta = (roles: string[]) =>
    orderedRoles(roles)
        .map((role) => roleLookup.value.get(role))
        .filter((role): role is RoleOption => !!role);

const summarizedAreas = (roles: string[]) =>
    selectedRoleMeta(roles)
        .flatMap((role) => role.areas)
        .filter((area, index, array) => array.indexOf(area) === index);

const hasOpsAccess = (roles: string[]) => selectedRoleMeta(roles).some((role) => role.ops);
const isReadOnlyAccess = (roles: string[]) => selectedRoleMeta(roles).some((role) => role.readonly);

const roleDescription = (roles: string[]) => {
    const meta = selectedRoleMeta(roles);

    if (meta.length === 0) {
        return 'No role description on file.';
    }

    if (meta.length === 1) {
        return meta[0].description ?? 'Role access is defined in the office ACL config.';
    }

    return `Stacked access across ${meta.length} roles so one person can cover multiple office lanes without losing their core job.`;
};

const rosterSummary = computed(() => ({
    total: props.users.length,
    managers: props.users.filter((user) => user.roles.includes('manager')).length,
    stacked: props.users.filter((user) => user.roles.length > 1).length,
    unassigned: props.users.filter((user) => !user.manager_id && !user.roles.some((role) => ['owner_admin', 'admin', 'demo_admin'].includes(role))).length,
}));

const submit = () => {
    form.post('/settings/users', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('name', 'email', 'password');
            form.roles = [defaultRole];
            form.manager_id = null;
        },
    });
};

const updateUserAccess = (user: ManagedUser) => {
    router.put(`/settings/users/${user.id}`, {
        roles: orderedRoles(roleDrafts[user.id] ?? user.roles),
        manager_id: managerDrafts[user.id] ?? null,
    }, {
        preserveScroll: true,
    });
};

const destroyUser = (id: number) => {
    router.delete(`/settings/users/${id}`, {
        preserveScroll: true,
    });
};

const hasUserAccessChanges = (user: ManagedUser) =>
    !sameRoles(roleDrafts[user.id] ?? user.roles, user.roles)
    || (managerDrafts[user.id] ?? null) !== (user.manager_id ?? null);

const initials = (name: string) =>
    name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');

const formatDateTime = (value?: string | null) =>
    value
        ? new Intl.DateTimeFormat('en-US', {
              month: 'short',
              day: 'numeric',
              year: 'numeric',
              hour: 'numeric',
              minute: '2-digit',
          }).format(new Date(value))
        : 'Never';
</script>

<template>
    <Head title="Accounts Manager" />

    <div v-if="canViewUserDirectory" class="space-y-8">
        <section class="space-y-3">
            <h1 class="text-xl font-semibold text-stone-950">Accounts + access manager</h1>
            <p class="max-w-5xl text-sm leading-6 text-stone-600">
                Global admin can see the full office, managers can see the people assigned under them, and staff can stack more than one role when one person needs to cover multiple lanes like Manager + Social / Meta.
            </p>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-[24px] border border-stone-300/70 bg-white/90 p-4">
                <p class="text-[11px] font-medium uppercase tracking-[0.28em] text-stone-500">Staff logins</p>
                <p class="mt-3 text-2xl font-semibold text-stone-950">{{ rosterSummary.total }}</p>
                <p class="mt-2 text-sm leading-6 text-stone-600">Every active office account visible inside this access tree.</p>
            </article>
            <article class="rounded-[24px] border border-stone-300/70 bg-white/90 p-4">
                <p class="text-[11px] font-medium uppercase tracking-[0.28em] text-stone-500">Managers</p>
                <p class="mt-3 text-2xl font-semibold text-stone-950">{{ rosterSummary.managers }}</p>
                <p class="mt-2 text-sm leading-6 text-stone-600">Anyone who can oversee direct reports and team workload from this office map.</p>
            </article>
            <article class="rounded-[24px] border border-stone-300/70 bg-white/90 p-4">
                <p class="text-[11px] font-medium uppercase tracking-[0.28em] text-stone-500">Stacked roles</p>
                <p class="mt-3 text-2xl font-semibold text-stone-950">{{ rosterSummary.stacked }}</p>
                <p class="mt-2 text-sm leading-6 text-stone-600">Users carrying more than one lane, like Manager plus Social media manager.</p>
            </article>
            <article class="rounded-[24px] border border-stone-300/70 bg-white/90 p-4">
                <p class="text-[11px] font-medium uppercase tracking-[0.28em] text-stone-500">No manager</p>
                <p class="mt-3 text-2xl font-semibold text-stone-950">{{ rosterSummary.unassigned }}</p>
                <p class="mt-2 text-sm leading-6 text-stone-600">People still sitting in the global pool instead of under a manager tree.</p>
            </article>
        </section>

        <div class="grid gap-8 xl:grid-cols-[0.88fr_1.12fr]">
            <section class="rounded-[28px] border border-stone-300/70 bg-white/95 p-6">
                <div class="border-b border-stone-200/80 pb-4">
                    <p class="text-[11px] font-medium uppercase tracking-[0.32em] text-stone-500">Add user</p>
                    <p class="mt-2 text-sm text-stone-600">Create a new office login, stack the right access lanes, and drop them under the right manager.</p>
                </div>

                <form class="mt-5 space-y-5" @submit.prevent="submit">
                    <div v-if="!canEditUsers" class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        You can see the office ACL tree here, but only admin-level accounts can create or change users.
                    </div>

                    <div v-else-if="isReadOnlyDemo" class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        Demo admin accounts can review staff and access lanes, but cannot create or remove users.
                    </div>

                    <label class="space-y-2">
                        <span class="text-xs font-medium uppercase tracking-[0.22em] text-stone-500">Full name</span>
                        <Input v-model="form.name" placeholder="Ashley Thomas" />
                        <span v-if="form.errors.name" class="text-xs text-rose-700">{{ form.errors.name }}</span>
                    </label>

                    <label class="space-y-2">
                        <span class="text-xs font-medium uppercase tracking-[0.22em] text-stone-500">Email</span>
                        <Input v-model="form.email" type="email" placeholder="ashley@creditsoft.app" />
                        <span v-if="form.errors.email" class="text-xs text-rose-700">{{ form.errors.email }}</span>
                    </label>

                    <div class="space-y-3">
                        <div>
                            <span class="text-xs font-medium uppercase tracking-[0.22em] text-stone-500">Access roles</span>
                            <p class="mt-1 text-xs leading-5 text-stone-500">Pick one or more roles so the person can cover several lanes without swapping accounts.</p>
                        </div>

                        <div class="grid gap-2">
                            <label
                                v-for="role in roles"
                                :key="role.value"
                                class="flex gap-3 rounded-[20px] border border-stone-200 bg-stone-50/80 px-4 py-3"
                            >
                                <input
                                    v-model="form.roles"
                                    type="checkbox"
                                    :value="role.value"
                                    class="mt-1 size-4 rounded border-stone-300 text-stone-950 focus:ring-stone-400"
                                >
                                <span class="space-y-1">
                                    <span class="block text-sm font-medium text-stone-900">
                                        {{ role.label }}{{ role.readonly ? ' (read-only)' : '' }}
                                    </span>
                                    <span class="block text-xs leading-5 text-stone-500">
                                        {{ role.description ?? 'Role access is defined in the office ACL config.' }}
                                    </span>
                                </span>
                            </label>
                        </div>

                        <p class="text-xs leading-5 text-stone-500">
                            {{ roleDescription(form.roles) }}
                        </p>
                        <span v-if="form.errors.roles" class="text-xs text-rose-700">{{ form.errors.roles }}</span>
                    </div>

                    <label class="space-y-2">
                        <span class="text-xs font-medium uppercase tracking-[0.22em] text-stone-500">Reports to</span>
                        <select v-model="form.manager_id" class="w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 text-sm text-stone-900">
                            <option :value="null">Global / no manager yet</option>
                            <option v-for="manager in manager_options" :key="manager.id" :value="manager.id">
                                {{ manager.name }}{{ manager.role_label ? ` · ${manager.role_label}` : '' }}
                            </option>
                        </select>
                        <p class="text-xs leading-5 text-stone-500">
                            Global admins see the whole office. Managers see themselves plus direct reports under their tree.
                        </p>
                        <span v-if="form.errors.manager_id" class="text-xs text-rose-700">{{ form.errors.manager_id }}</span>
                    </label>

                    <label class="space-y-2">
                        <span class="text-xs font-medium uppercase tracking-[0.22em] text-stone-500">Starting password</span>
                        <Input v-model="form.password" type="text" placeholder="Set a starting password" />
                        <span v-if="form.errors.password" class="text-xs text-rose-700">{{ form.errors.password }}</span>
                    </label>

                    <button
                        type="submit"
                        class="inline-flex items-center gap-2 rounded-full bg-stone-950 px-5 py-3 text-xs font-medium uppercase tracking-[0.2em] text-white transition hover:bg-stone-800 disabled:cursor-not-allowed disabled:bg-stone-300"
                        :disabled="form.processing || !canEditUsers || isReadOnlyDemo"
                    >
                        <FontAwesomeIcon :icon="faUsers" />
                        Create user
                    </button>
                </form>
            </section>

            <section class="rounded-[28px] border border-stone-300/70 bg-white/95 p-6">
                <div class="border-b border-stone-200/80 pb-4">
                    <div class="flex items-center gap-2">
                        <FontAwesomeIcon :icon="faShieldHalved" class="text-stone-700" />
                        <p class="text-[11px] font-medium uppercase tracking-[0.32em] text-stone-500">ACL manager</p>
                    </div>
                    <h2 class="mt-3 text-lg font-semibold text-stone-950">Role lanes, hierarchy, and stacked access</h2>
                    <p class="mt-2 max-w-4xl text-sm leading-6 text-stone-600">
                        Use the manager role for team oversight, add Social media manager when the same person owns Meta and content, and keep global office visibility with the admin lanes.
                    </p>
                </div>

                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <article
                        v-for="role in roles"
                        :key="role.value"
                        class="rounded-[24px] border border-stone-200 bg-stone-50/80 p-4"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="space-y-2">
                                <p class="text-base font-semibold text-stone-950">{{ role.label }}</p>
                                <p class="text-sm leading-6 text-stone-600">
                                    {{ role.description ?? 'Role access is defined in the office ACL config.' }}
                                </p>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <span
                                    class="rounded-full border px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.18em]"
                                    :class="role.readonly ? 'border-stone-300 bg-white text-stone-700' : 'border-emerald-200 bg-emerald-50 text-emerald-800'"
                                >
                                    {{ role.readonly ? 'Read-only' : 'Writable' }}
                                </span>
                                <span
                                    v-if="role.ops"
                                    class="rounded-full border border-sky-200 bg-sky-50 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-sky-800"
                                >
                                    Ops
                                </span>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <span
                                v-for="area in role.areas"
                                :key="`${role.value}-${area}`"
                                class="rounded-full border border-stone-300 bg-white px-2.5 py-1 text-[10px] font-medium uppercase tracking-[0.16em] text-stone-700"
                            >
                                {{ area }}
                            </span>
                        </div>
                    </article>
                </div>
            </section>
        </div>

        <section class="space-y-4">
            <div class="border-b border-stone-300/70 pb-4">
                <div class="flex items-center gap-2">
                    <FontAwesomeIcon :icon="faPenToSquare" class="text-stone-700" />
                    <p class="text-[11px] font-medium uppercase tracking-[0.32em] text-stone-500">Staff roster</p>
                </div>
                <p class="mt-2 text-sm text-stone-600">Edit stacked roles, set the manager tree, and see who has direct reports or multiple office lanes.</p>
            </div>

            <div class="space-y-3">
                <div v-for="user in users" :key="user.id" class="rounded-[24px] border border-stone-300/70 bg-white/90 p-4">
                    <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
                        <div class="flex min-w-0 items-start gap-4">
                            <Avatar class="size-12 overflow-hidden rounded-full border border-stone-200 bg-white">
                                <AvatarImage :src="user.gravatar_url" :alt="user.name" />
                                <AvatarFallback class="bg-stone-100 text-sm font-semibold text-stone-900">
                                    {{ initials(user.name) }}
                                </AvatarFallback>
                            </Avatar>

                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="truncate font-medium text-stone-950">{{ user.name }}</p>
                                    <span v-if="user.is_current_user" class="rounded-full bg-stone-950 px-2 py-1 text-[10px] font-medium uppercase tracking-[0.18em] text-stone-50">
                                        You
                                    </span>
                                    <span v-if="user.read_only_demo" class="rounded-full border border-stone-300 bg-stone-100 px-2 py-1 text-[10px] font-medium uppercase tracking-[0.18em] text-stone-700">
                                        Demo
                                    </span>
                                    <span v-if="user.direct_reports_count > 0" class="rounded-full border border-amber-200 bg-amber-50 px-2 py-1 text-[10px] font-medium uppercase tracking-[0.18em] text-amber-800">
                                        {{ user.direct_reports_count }} reports
                                    </span>
                                </div>

                                <p class="truncate text-sm text-stone-500">{{ user.email }}</p>

                                <div class="mt-3 flex flex-wrap gap-2">
                                    <span
                                        v-for="label in user.role_labels"
                                        :key="`${user.id}-${label}`"
                                        class="rounded-full border border-stone-300 bg-white px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-stone-700"
                                    >
                                        {{ label }}
                                    </span>
                                    <span
                                        v-if="hasOpsAccess(user.roles)"
                                        class="rounded-full border border-sky-200 bg-sky-50 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-sky-800"
                                    >
                                        Ops access
                                    </span>
                                    <span
                                        v-if="isReadOnlyAccess(user.roles)"
                                        class="rounded-full border border-stone-300 bg-stone-100 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-stone-700"
                                    >
                                        Read-only
                                    </span>
                                </div>

                                <p class="mt-3 text-sm leading-6 text-stone-600">
                                    {{ roleDescription(user.roles) }}
                                </p>

                                <div class="mt-3 flex flex-wrap gap-2">
                                    <span
                                        v-for="area in summarizedAreas(user.roles)"
                                        :key="`${user.id}-${area}`"
                                        class="rounded-full border border-stone-300 bg-stone-50 px-2.5 py-1 text-[10px] font-medium uppercase tracking-[0.16em] text-stone-700"
                                    >
                                        {{ area }}
                                    </span>
                                </div>

                                <div class="mt-4 grid gap-2 text-xs text-stone-600 md:grid-cols-4">
                                    <p><span class="font-medium text-stone-800">Last login:</span> {{ formatDateTime(user.last_login_at) }}</p>
                                    <p><span class="font-medium text-stone-800">Last seen:</span> {{ formatDateTime(user.last_seen_at) }}</p>
                                    <p><span class="font-medium text-stone-800">Active API keys:</span> {{ user.active_api_keys_count }}</p>
                                    <p><span class="font-medium text-stone-800">Reports to:</span> {{ user.manager_name ?? 'Global office' }}</p>
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="canEditUsers && !isReadOnlyDemo && !user.is_current_user"
                            class="w-full shrink-0 rounded-[22px] border border-stone-200 bg-stone-50/80 p-4 xl:w-[340px]"
                        >
                            <div class="space-y-4">
                                <div>
                                    <span class="text-xs font-medium uppercase tracking-[0.22em] text-stone-500">Access stack</span>
                                    <p class="mt-1 text-xs leading-5 text-stone-500">Managers can carry more than one role, so add Social / Meta without replacing the main manager lane.</p>
                                </div>

                                <div class="grid gap-2">
                                    <label
                                        v-for="role in roles"
                                        :key="`${user.id}-${role.value}`"
                                        class="flex gap-3 rounded-[18px] border border-stone-200 bg-white px-3 py-2.5"
                                    >
                                        <input
                                            v-model="roleDrafts[user.id]"
                                            type="checkbox"
                                            :value="role.value"
                                            class="mt-1 size-4 rounded border-stone-300 text-stone-950 focus:ring-stone-400"
                                        >
                                        <span class="space-y-1">
                                            <span class="block text-sm font-medium text-stone-900">
                                                {{ role.label }}{{ role.readonly ? ' (read-only)' : '' }}
                                            </span>
                                            <span class="block text-[11px] leading-5 text-stone-500">
                                                {{ role.description ?? 'Role access is defined in the office ACL config.' }}
                                            </span>
                                        </span>
                                    </label>
                                </div>

                                <label class="space-y-2">
                                    <span class="text-xs font-medium uppercase tracking-[0.22em] text-stone-500">Reports to</span>
                                    <select v-model="managerDrafts[user.id]" class="w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 text-sm text-stone-900">
                                        <option :value="null">Global / no manager yet</option>
                                        <option v-for="manager in manager_options.filter((option) => option.id !== user.id)" :key="`${user.id}-${manager.id}`" :value="manager.id">
                                            {{ manager.name }}{{ manager.role_label ? ` · ${manager.role_label}` : '' }}
                                        </option>
                                    </select>
                                </label>

                                <p class="text-xs leading-5 text-stone-500">
                                    {{ roleDescription(roleDrafts[user.id] ?? user.roles) }}
                                </p>

                                <div class="flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-2 rounded-full bg-stone-950 px-4 py-2 text-[11px] font-medium uppercase tracking-[0.18em] text-white transition hover:bg-stone-800 disabled:cursor-not-allowed disabled:bg-stone-300"
                                        :disabled="!hasUserAccessChanges(user)"
                                        @click="updateUserAccess(user)"
                                    >
                                        <FontAwesomeIcon :icon="faPenToSquare" />
                                        Save access
                                    </button>

                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-2 rounded-full border border-stone-300 px-3 py-2 text-[11px] font-medium uppercase tracking-[0.18em] text-stone-700 transition hover:border-rose-300 hover:text-rose-700"
                                        @click="destroyUser(user.id)"
                                    >
                                        <FontAwesomeIcon :icon="faTrashCan" />
                                        Remove
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div v-else-if="user.is_current_user" class="w-full shrink-0 rounded-[22px] border border-stone-200 bg-stone-50/80 p-4 text-sm leading-6 text-stone-600 xl:w-[320px]">
                            Use another admin account to change your own access stack so you do not lock yourself out accidentally.
                        </div>

                        <div v-else class="w-full shrink-0 rounded-[22px] border border-stone-200 bg-stone-50/80 p-4 text-sm leading-6 text-stone-600 xl:w-[320px]">
                            This roster is visible so managers can see their direct reports, but only admin-level accounts can rewrite access or move people in the hierarchy.
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
