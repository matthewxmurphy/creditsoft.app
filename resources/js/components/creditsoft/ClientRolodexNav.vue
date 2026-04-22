<script setup lang="ts">
import { faAnglesLeft, faAnglesRight } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { clientHealthTone, type ClientHealthInput } from '@/lib/client-health';

type NameMode = 'first' | 'last';
type HealthTone = 'blue' | 'rose' | 'amber' | 'emerald' | 'stone';

type ClientNavigatorItem = {
    id: number;
    first_name?: string | null;
    last_name?: string | null;
    display_name: string;
    status?: string | null;
    status_label: string;
    href: string;
    source_kind?: string | null;
    client_health?: ClientHealthInput;
};

type ClientNavigator = {
    current_id: number;
    current_view?: string | null;
    position: number;
    total: number;
    options: ClientNavigatorItem[];
};

const props = defineProps<{
    clientId: number;
}>();

const nameModeStorageKey = 'creditsoft.clientRolodexNameMode.v2';
const page = usePage<{
    clientNavigator?: ClientNavigator | null;
}>();

const savedNameMode =
    typeof window !== 'undefined'
        ? window.localStorage.getItem(nameModeStorageKey)
        : null;
const nameMode = ref<NameMode>(savedNameMode === 'last' ? 'last' : 'first');
const selectedLetter = ref('');

const navigator = computed(() => page.props.clientNavigator ?? null);
const options = computed(() => navigator.value?.options ?? []);
const currentPath = computed(() =>
    String(page.url ?? `/clients/${props.clientId}`),
);
const pathOnly = computed(() => currentPath.value.split('?')[0] ?? '');
const queryString = computed(() => {
    const questionIndex = currentPath.value.indexOf('?');

    return questionIndex >= 0 ? currentPath.value.slice(questionIndex) : '';
});
const clientWorkspaceSuffix = computed(() => {
    const match = pathOnly.value.match(/^\/clients\/\d+(\/[^?#]+)?$/);

    return match?.[1] ?? '';
});

const nameParts = (client: ClientNavigatorItem) => {
    const first = (client.first_name ?? '').trim();
    const last = (client.last_name ?? '').trim();

    if (first || last) {
        return { first, last };
    }

    const parts = client.display_name.trim().split(/\s+/);

    return {
        first: parts[0] ?? client.display_name.trim(),
        last: parts.length > 1 ? parts.at(-1)! : '',
    };
};

const sortName = (client: ClientNavigatorItem) => {
    const parts = nameParts(client);

    if (nameMode.value === 'last') {
        return `${parts.last || parts.first} ${parts.first}`.trim();
    }

    return `${parts.first || parts.last} ${parts.last}`.trim();
};

const tabLabel = (client: ClientNavigatorItem) => {
    const parts = nameParts(client);

    if (nameMode.value === 'last' && parts.last && parts.first) {
        return `${parts.last}, ${parts.first}`;
    }

    return client.display_name;
};

const clientInitial = (client: ClientNavigatorItem) => {
    const parts = nameParts(client);
    const name = nameMode.value === 'last' ? parts.last : parts.first;
    const letter = name.trim().charAt(0).toUpperCase();

    return /^[A-Z]$/.test(letter) ? letter : '#';
};

const sortedOptions = computed(() =>
    [...options.value].sort((left, right) => {
        const nameCompare = sortName(left).localeCompare(
            sortName(right),
            'en',
            {
                sensitivity: 'base',
            },
        );

        return nameCompare === 0 ? left.id - right.id : nameCompare;
    }),
);

const currentClient = computed(
    () =>
        sortedOptions.value.find((client) => client.id === props.clientId) ??
        sortedOptions.value.find(
            (client) => client.id === navigator.value?.current_id,
        ) ??
        null,
);

const currentIndex = computed(() =>
    currentClient.value
        ? sortedOptions.value.findIndex(
              (client) => client.id === currentClient.value?.id,
          )
        : -1,
);
const previousClient = computed(() =>
    currentIndex.value > 0 ? sortedOptions.value[currentIndex.value - 1] : null,
);
const nextClient = computed(() =>
    currentIndex.value >= 0 &&
    currentIndex.value < sortedOptions.value.length - 1
        ? sortedOptions.value[currentIndex.value + 1]
        : null,
);
const clientsByLetter = computed(() => {
    const groups = new Map<string, ClientNavigatorItem[]>();

    for (const client of sortedOptions.value) {
        const letter = clientInitial(client);

        groups.set(letter, [...(groups.get(letter) ?? []), client]);
    }

    return groups;
});
const letters = computed(() => {
    const present = clientsByLetter.value;
    const alpha = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'
        .split('')
        .filter((letter) => present.has(letter));

    return present.has('#') ? ['#', ...alpha] : alpha;
});

const letterCount = (letter: string) =>
    clientsByLetter.value.get(letter)?.length ?? 0;
const tonePriority: Record<HealthTone, number> = {
    rose: 5,
    amber: 4,
    blue: 3,
    emerald: 2,
    stone: 1,
};

const normalizeTone = (tone: string): HealthTone =>
    ['blue', 'rose', 'amber', 'emerald', 'stone'].includes(tone)
        ? (tone as HealthTone)
        : 'stone';

const clientTone = (client: ClientNavigatorItem) =>
    normalizeTone(clientHealthTone(client.client_health));

const letterTone = (letter: string) => {
    const clients = clientsByLetter.value.get(letter) ?? [];

    return clients.reduce((winner, client) => {
        const tone = clientTone(client);

        return tonePriority[tone] > tonePriority[winner] ? tone : winner;
    }, 'stone' as HealthTone);
};

const tabToneClass = (tone: HealthTone, selected = false) => {
    if (tone === 'rose') {
        return selected
            ? 'border-rose-400 bg-rose-100 text-rose-950 shadow-[inset_0_3px_0_rgba(244,63,94,0.9)]'
            : 'border-rose-200 bg-rose-50 text-rose-900 hover:border-rose-400 hover:bg-rose-100';
    }

    if (tone === 'amber') {
        return selected
            ? 'border-amber-400 bg-amber-100 text-amber-950 shadow-[inset_0_3px_0_rgba(251,191,36,0.95)]'
            : 'border-amber-200 bg-amber-50 text-amber-900 hover:border-amber-400 hover:bg-amber-100';
    }

    if (tone === 'blue') {
        return selected
            ? 'border-blue-400 bg-blue-100 text-blue-950 shadow-[inset_0_3px_0_rgba(59,130,246,0.9)]'
            : 'border-blue-200 bg-blue-50 text-blue-900 hover:border-blue-400 hover:bg-blue-100';
    }

    if (tone === 'emerald') {
        return selected
            ? 'border-emerald-400 bg-emerald-100 text-emerald-950 shadow-[inset_0_3px_0_rgba(16,185,129,0.9)]'
            : 'border-emerald-200 bg-emerald-50 text-emerald-900 hover:border-emerald-400 hover:bg-emerald-100';
    }

    return selected
        ? 'border-stone-400 bg-white text-stone-950 shadow-[inset_0_3px_0_rgba(120,113,108,0.7)]'
        : 'border-stone-300 bg-stone-50 text-stone-600 hover:border-stone-500 hover:bg-white hover:text-stone-950';
};

const controlTabClass = (enabled = true) =>
    enabled
        ? 'border-stone-300 bg-white text-stone-700 hover:border-stone-500 hover:bg-stone-50 hover:text-stone-950'
        : 'border-stone-200 bg-stone-100 text-stone-300';

const sortTabClass = (mode: NameMode) =>
    nameMode.value === mode
        ? 'border-stone-950 bg-stone-950 text-white'
        : 'border-stone-300 bg-white text-stone-600 hover:border-stone-500 hover:bg-stone-50 hover:text-stone-950';

const clientHref = (client: ClientNavigatorItem) =>
    `/clients/${client.id}${clientWorkspaceSuffix.value}${queryString.value}`;

const selectNameMode = (mode: NameMode) => {
    nameMode.value = mode;
};

const selectLetter = (letter: string) => {
    selectedLetter.value = letter;
};

watch(
    [currentClient, nameMode],
    ([client]) => {
        selectedLetter.value = client
            ? clientInitial(client)
            : (letters.value[0] ?? '');
    },
    { immediate: true },
);

watch(nameMode, (mode) => {
    if (typeof window !== 'undefined') {
        window.localStorage.setItem(nameModeStorageKey, mode);
    }
});
</script>

<template>
    <nav
        v-if="navigator && sortedOptions.length > 0"
        class="isolate relative z-50 min-w-0 translate-y-[4px] overflow-visible"
        aria-label="Client rolodex"
    >
        <div
            class="absolute top-[calc(100%+1.45rem)] left-0 z-[60] flex -translate-x-full flex-col gap-0"
            aria-label="Name sort mode"
        >
            <button
                type="button"
                class="relative inline-flex h-11 w-8 items-center justify-center rounded-l-xl rounded-r-none border border-r-0 px-0 py-1 text-[8px] font-semibold tracking-[0.12em] uppercase shadow-sm transition"
                :class="sortTabClass('first')"
                aria-label="Sort by first name"
                @click="selectNameMode('first')"
            >
                <span class="-rotate-90 whitespace-nowrap">First</span>
            </button>
            <button
                type="button"
                class="relative -mt-px inline-flex h-11 w-8 items-center justify-center rounded-l-xl rounded-r-none border border-r-0 px-0 py-1 text-[8px] font-semibold tracking-[0.12em] uppercase shadow-sm transition"
                :class="sortTabClass('last')"
                aria-label="Sort by last name"
                @click="selectNameMode('last')"
            >
                <span class="-rotate-90 whitespace-nowrap">Last</span>
            </button>
        </div>

        <div
            class="relative z-50 flex min-w-0 flex-col items-center overflow-visible"
            aria-label="Client rolodex tabs"
        >
            <div
                class="relative z-50 mt-[5px] flex w-full min-w-0 justify-center overflow-visible px-4"
            >
                <div
                    class="inline-flex max-w-full min-w-0 items-end gap-0 overflow-x-auto overflow-y-visible border-b border-stone-300/80"
                    aria-label="Client rolodex alphabet"
                >
                    <button
                        v-for="letter in letters"
                        :key="letter"
                        type="button"
                        class="relative -mb-px inline-flex h-[50px] min-w-10 shrink-0 items-center justify-center rounded-t-lg border border-b-0 px-2 text-xs font-bold transition"
                        :class="
                            tabToneClass(
                                letterTone(letter),
                                selectedLetter === letter,
                            )
                        "
                        :aria-label="`Show ${letterCount(letter)} ${letter} clients`"
                        :title="`${letterCount(letter)} client${letterCount(letter) === 1 ? '' : 's'} under ${letter}`"
                        @click="selectLetter(letter)"
                    >
                        <span class="-translate-y-[5px]">{{ letter }}</span>
                        <span
                            class="absolute top-1 right-1 text-[8px] leading-none opacity-60"
                            >{{ letterCount(letter) }}</span
                        >
                    </button>
                </div>
            </div>

            <div
                class="relative z-[55] -mt-[10px] flex w-full min-w-0 justify-center overflow-visible px-4"
            >
                <div
                    class="inline-flex max-w-full min-w-0 items-end gap-0 overflow-visible border-b border-stone-300/80"
                >
                    <div
                        class="flex max-w-full min-w-0 gap-0 overflow-x-auto overflow-y-visible"
                        aria-label="Client rolodex people tabs"
                    >
                        <Link
                            v-for="client in sortedOptions"
                            :key="client.id"
                            :href="clientHref(client)"
                            class="-mb-px inline-flex h-11 max-w-[14rem] shrink-0 items-center rounded-t-lg border border-b-0 px-3 text-sm font-semibold transition"
                            :class="
                                tabToneClass(
                                    clientTone(client),
                                    client.id === props.clientId,
                                )
                            "
                            :title="client.display_name"
                        >
                            <span class="truncate">{{ tabLabel(client) }}</span>
                        </Link>
                    </div>

                    <div class="ml-2 flex shrink-0 items-end gap-0">
                        <Link
                            v-if="previousClient"
                            :href="clientHref(previousClient)"
                            class="-mb-px inline-flex size-11 shrink-0 items-center justify-center rounded-t-lg border border-b-0 transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-300"
                            :class="controlTabClass(true)"
                            :title="`Previous: ${previousClient.display_name}`"
                            aria-label="Previous client"
                        >
                            <FontAwesomeIcon :icon="faAnglesLeft" />
                        </Link>
                        <button
                            v-else
                            type="button"
                            disabled
                            class="-mb-px inline-flex size-11 shrink-0 items-center justify-center rounded-t-lg border border-b-0"
                            :class="controlTabClass(false)"
                            aria-label="Previous client unavailable"
                        >
                            <FontAwesomeIcon :icon="faAnglesLeft" />
                        </button>

                        <Link
                            v-if="nextClient"
                            :href="clientHref(nextClient)"
                            class="-mb-px inline-flex size-11 shrink-0 items-center justify-center rounded-t-lg border border-b-0 transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-300"
                            :class="controlTabClass(true)"
                            :title="`Next: ${nextClient.display_name}`"
                            aria-label="Next client"
                        >
                            <FontAwesomeIcon :icon="faAnglesRight" />
                        </Link>
                        <button
                            v-else
                            type="button"
                            disabled
                            class="-mb-px inline-flex size-11 shrink-0 items-center justify-center rounded-t-lg border border-b-0"
                            :class="controlTabClass(false)"
                            aria-label="Next client unavailable"
                        >
                            <FontAwesomeIcon :icon="faAnglesRight" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </nav>
</template>
