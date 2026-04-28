const parseDisplayDate = (value: string) => {
    const dateOnlyMatch = value.match(/^(\d{4})-(\d{2})-(\d{2})$/);

    if (dateOnlyMatch) {
        const [, year, month, day] = dateOnlyMatch;

        return new Date(Number(year), Number(month) - 1, Number(day));
    }

    return new Date(value);
};

export const formatCurrency = (value?: number | null) =>
    new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        maximumFractionDigits: 0,
    }).format(Number(value ?? 0));

export const formatNumber = (value?: number | null) =>
    new Intl.NumberFormat('en-US').format(Number(value ?? 0));

export const formatDate = (value?: string | null) =>
    value
        ? new Intl.DateTimeFormat('en-US', {
              month: 'short',
              day: 'numeric',
              year: 'numeric',
          }).format(parseDisplayDate(value))
        : 'Not set';

export const formatDateTime = (value?: string | null) =>
    value
        ? new Intl.DateTimeFormat('en-US', {
              month: 'short',
              day: 'numeric',
              year: 'numeric',
              hour: 'numeric',
              minute: '2-digit',
          }).format(new Date(value))
        : 'Not set';

export const compactLabel = (value: string) =>
    value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());

export const badgeTone = (count = 0) => {
    if (count >= 1) return 'bg-amber-400 text-stone-950 ring-1 ring-stone-950/20';

    return 'bg-stone-200 text-stone-700';
};
