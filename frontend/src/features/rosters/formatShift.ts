export function shiftLabel(start: string, end: string, endsNextDay: boolean): string {
    const label = `${start}–${end}`;

    return endsNextDay ? `${label} → next day` : label;
}

export function currentMonthPeriod(now = new Date()): { start: string; end: string } {
    const start = new Date(now.getFullYear(), now.getMonth(), 1);
    const end = new Date(now.getFullYear(), now.getMonth() + 1, 0);

    return {
        start: formatDate(start),
        end: formatDate(end),
    };
}

export function formatDate(date: Date): string {
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${date.getFullYear()}-${month}-${day}`;
}

export function dayNumber(isoDate: string): string {
    return isoDate.slice(8, 10);
}
