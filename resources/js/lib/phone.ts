export const formatUsPhone = (value: string | null | undefined): string => {
    const raw = String(value ?? '').trim();

    if (!raw) {
        return '';
    }

    const extensionMatch = raw.match(
        /\s+(?:ext\.?|extension|x)\s*(\d{1,8})\s*$/i,
    );
    const extension = extensionMatch?.[1] ?? '';
    const phone = extensionMatch
        ? raw.slice(0, -extensionMatch[0].length).trim()
        : raw;
    let digits = phone.replace(/\D+/g, '');

    if (digits.length === 11 && digits.startsWith('1')) {
        digits = digits.slice(1);
    }

    if (digits.length !== 10) {
        return raw;
    }

    const formatted = `+1 (${digits.slice(0, 3)}) ${digits.slice(3, 6)}-${digits.slice(6)}`;

    return extension ? `${formatted} x${extension}` : formatted;
};
