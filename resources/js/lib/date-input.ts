export function isValidDateInput(value: string): boolean {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(value) || value.startsWith('0000')) {
        return false;
    }

    const date = new Date(`${value}T00:00:00.000Z`);
    return Number.isFinite(date.getTime()) && date.toISOString().slice(0, 10) === value;
}

export function addDaysToDate(value: string, days: number): string | null {
    if (!isValidDateInput(value) || !Number.isInteger(days)) {
        return null;
    }

    const date = new Date(`${value}T00:00:00.000Z`);
    date.setUTCDate(date.getUTCDate() + days);

    if (!Number.isFinite(date.getTime()) || date.getUTCFullYear() > 9999 || date.getUTCFullYear() < 1) {
        return null;
    }

    return date.toISOString().slice(0, 10);
}
