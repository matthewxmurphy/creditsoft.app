export type ClientHealthSignal = {
    label?: string | null;
    detail?: string | null;
    reason?: string | null;
    state?: string | null;
    status?: string | null;
    value?: string | null;
    note?: string | null;
    tone?: string | null;
    color?: string | null;
    score?: number | string | null;
    score_label?: string | null;
    amount_label?: string | null;
    last_paid_label?: string | null;
    next_due_label?: string | null;
    imported_payment_label?: string | null;
};

export type ClientHealthInput = ClientHealthSignal | string | null | undefined;

export const clientHealthTone = (input: ClientHealthInput) => {
    if (!input) {
        return 'stone';
    }

    if (typeof input !== 'string') {
        const explicitTone = `${input.tone ?? input.color ?? ''}`.toLowerCase();

        if (
            explicitTone.includes('diamond') ||
            explicitTone.includes('vip') ||
            explicitTone.includes('blue')
        ) {
            return 'blue';
        }

        if (explicitTone.includes('red') || explicitTone.includes('rose')) {
            return 'rose';
        }

        if (explicitTone.includes('yellow') || explicitTone.includes('amber')) {
            return 'amber';
        }

        if (
            explicitTone.includes('green') ||
            explicitTone.includes('emerald')
        ) {
            return 'emerald';
        }

        if (explicitTone.includes('stone')) {
            return 'stone';
        }
    }

    const text =
        typeof input === 'string'
            ? input
            : [
                  input.label,
                  input.detail,
                  input.reason,
                  input.state,
                  input.status,
                  input.value,
                  input.note,
                  input.tone,
                  input.color,
              ]
                  .filter(Boolean)
                  .join(' ');
    const normalized = text.toLowerCase();

    if (
        normalized.includes('diamond') ||
        normalized.includes('vip') ||
        normalized.includes('blue')
    ) {
        return 'blue';
    }

    if (
        normalized.includes('behind') ||
        normalized.includes('owes') ||
        normalized.includes('past due') ||
        normalized.includes('delinquent') ||
        normalized.includes('overdue') ||
        normalized.includes('red')
    ) {
        return 'rose';
    }

    if (
        normalized.includes('watch') ||
        normalized.includes('warn') ||
        normalized.includes('due soon') ||
        normalized.includes('late') ||
        normalized.includes('failed') ||
        normalized.includes('yellow') ||
        normalized.includes('attention') ||
        normalized.includes('amber')
    ) {
        return 'amber';
    }

    if (
        normalized.includes('current') ||
        normalized.includes('green') ||
        normalized.includes('paid') ||
        normalized.includes('healthy')
    ) {
        return 'emerald';
    }

    return 'stone';
};

export const clientHealthLabel = (input: ClientHealthInput) => {
    if (!input) {
        return null;
    }

    if (typeof input === 'string') {
        return input;
    }

    return input.label ?? input.value ?? input.status ?? input.note ?? null;
};

export const clientHealthScoreLabel = (input: ClientHealthInput) => {
    if (!input || typeof input === 'string') {
        return null;
    }

    if (input.score_label) {
        return input.score_label;
    }

    return input.score !== null && input.score !== undefined
        ? `${input.score}/100`
        : null;
};

export const clientHealthDetail = (input: ClientHealthInput) => {
    if (!input || typeof input === 'string') {
        return null;
    }

    const parts = [
        input.detail ?? input.reason ?? input.status ?? input.value ?? null,
        input.amount_label ? `Amount ${input.amount_label}` : null,
        input.next_due_label ? `Next due ${input.next_due_label}` : null,
        input.last_paid_label ? `Last paid ${input.last_paid_label}` : null,
        input.imported_payment_label
            ? `Imported ${input.imported_payment_label}`
            : null,
    ].filter(Boolean);

    return parts.length > 0 ? parts.join(' · ') : null;
};

export const clientHealthBadgeClass = (input: ClientHealthInput) => {
    const tone = clientHealthTone(input);

    if (tone === 'blue') {
        return 'border-blue-300 bg-blue-50 text-blue-950';
    }

    if (tone === 'rose') {
        return 'border-rose-300 bg-rose-50 text-rose-900';
    }

    if (tone === 'emerald') {
        return 'border-emerald-300 bg-emerald-50 text-emerald-900';
    }

    if (tone === 'amber') {
        return 'border-amber-300 bg-amber-50 text-amber-900';
    }

    return 'border-stone-300 bg-white text-stone-600';
};

export const clientHealthDotClass = (input: ClientHealthInput) => {
    const tone = clientHealthTone(input);

    if (tone === 'blue') {
        return 'bg-blue-500';
    }

    if (tone === 'rose') {
        return 'bg-rose-500';
    }

    if (tone === 'emerald') {
        return 'bg-emerald-500';
    }

    if (tone === 'amber') {
        return 'bg-amber-400';
    }

    return 'bg-stone-300';
};

export const clientHealthRowClass = (input: ClientHealthInput) => {
    const tone = clientHealthTone(input);

    if (tone === 'blue') {
        return 'border-l-4 border-l-blue-500 bg-blue-50/55 hover:bg-blue-50/80';
    }

    if (tone === 'rose') {
        return 'border-l-4 border-l-rose-500 bg-rose-50/65 hover:bg-rose-50';
    }

    if (tone === 'emerald') {
        return 'border-l-4 border-l-emerald-500 bg-emerald-50/55 hover:bg-emerald-50/80';
    }

    if (tone === 'amber') {
        return 'border-l-4 border-l-amber-400 bg-amber-50/65 hover:bg-amber-50';
    }

    return 'border-l-4 border-l-stone-200 bg-white hover:bg-stone-50';
};

export const clientHealthTextClass = (input: ClientHealthInput) => {
    const tone = clientHealthTone(input);

    if (tone === 'blue') {
        return 'text-blue-800';
    }

    if (tone === 'rose') {
        return 'text-rose-800';
    }

    if (tone === 'emerald') {
        return 'text-emerald-800';
    }

    if (tone === 'amber') {
        return 'text-amber-800';
    }

    return 'text-stone-500';
};

export const clientHealthPanelClass = (input: ClientHealthInput) => {
    const tone = clientHealthTone(input);

    if (tone === 'blue') {
        return 'border-blue-300/80 bg-blue-50/80 shadow-blue-100/70';
    }

    if (tone === 'rose') {
        return 'border-rose-300/80 bg-rose-50/80 shadow-rose-100/70';
    }

    if (tone === 'emerald') {
        return 'border-emerald-300/80 bg-emerald-50/80 shadow-emerald-100/70';
    }

    if (tone === 'amber') {
        return 'border-amber-300/80 bg-amber-50/80 shadow-amber-100/70';
    }

    return 'border-stone-300/70 bg-stone-50/75 shadow-stone-200/40';
};
