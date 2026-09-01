const suffixMap: Record<string, string> = {
    jr: 'Jr.',
    sr: 'Sr.',
    ii: 'II',
    iii: 'III',
    iv: 'IV',
    v: 'V',
    vi: 'VI',
    vii: 'VII',
    viii: 'VIII',
    ix: 'IX',
    x: 'X',
};

type ClientNameForm = {
    first_name?: string | null;
    middle_name?: string | null;
    last_name?: string | null;
    name_suffix?: string | null;
};

export function formatClientNamePart(value?: string | null): string {
    const name = (value ?? '').replace(/\s+/g, ' ').trim();

    if (!name) {
        return '';
    }

    return name.replace(/[A-Za-z]+(?:['-][A-Za-z]+)*/g, (token) =>
        formatNameToken(token),
    );
}

export function formatClientSuffix(value?: string | null): string {
    const suffix = (value ?? '').replace(/\s+/g, ' ').trim();
    const key = suffix.toLowerCase().replace(/[^a-z0-9]/g, '');

    return suffixMap[key] ?? formatClientNamePart(suffix);
}

export function normalizeClientNameForm<T extends ClientNameForm>(form: T): T {
    form.first_name = formatClientNamePart(form.first_name);
    form.middle_name = formatClientNamePart(form.middle_name);
    form.last_name = formatClientNamePart(form.last_name);
    form.name_suffix = formatClientSuffix(form.name_suffix);

    if (form.first_name && !form.middle_name) {
        const parts = form.first_name.split(/\s+/).filter(Boolean);

        if (parts.length > 1) {
            form.first_name = formatClientNamePart(parts.shift() ?? '');
            form.middle_name = formatClientNamePart(parts.join(' '));
        }
    }

    if (form.last_name && !form.name_suffix) {
        const parts = form.last_name.split(/\s+/).filter(Boolean);
        const maybeSuffix = parts.at(-1) ?? '';
        const suffix = formatClientSuffix(maybeSuffix);

        if (parts.length > 1 && suffixMap[suffixKey(maybeSuffix)]) {
            parts.pop();
            form.last_name = formatClientNamePart(parts.join(' '));
            form.name_suffix = suffix;
        }
    }

    return form;
}

function formatNameToken(token: string): string {
    const letters = token.replace(/[^A-Za-z]+/g, '');

    if (!letters) {
        return token;
    }

    const isAllLower = letters.toLowerCase() === letters;
    const isAllUpper = letters.toUpperCase() === letters;
    const isSimpleTitle = /^[A-Z][a-z]+(?:['-][a-z]+)*$/.test(token);

    if (!isAllLower && !isAllUpper && !isSimpleTitle) {
        return token;
    }

    return token
        .toLowerCase()
        .replace(/(^|['-])([a-z])/g, (_match, prefix: string, letter: string) =>
            `${prefix}${letter.toUpperCase()}`,
        )
        .replace(/\bMc([a-z])/g, (_match, letter: string) => `Mc${letter.toUpperCase()}`);
}

function suffixKey(value: string): string {
    return value.toLowerCase().replace(/[^a-z0-9]/g, '');
}
