/**
 * Calculate the next salary period start date based on last salary date.
 * If previous salary ended on YYYY-MM-DD, next period starts on YYYY-MM-DD + 1 day.
 *
 * Server (AttendanceReportService::getSalaryReportData) bilan bir xil qoida:
 * keyingi kun hisobot boshlanishidan (fallbackFrom) katta bo'lsa va hisobot oxirgi sanasidan (toDate)
 * oshib ketmasa ishlatiladi, aks holda fallbackFrom saqlanadi.
 */
export function resolveNextSalaryStartDate(
    lastSalaryDate?: string | null,
    fallbackFrom?: string | null,
    toDate?: string | null
): string {
    if (lastSalaryDate) {
        const parts = String(lastSalaryDate).split('-');
        if (parts.length === 3) {
            const year = parseInt(parts[0], 10);
            const month = parseInt(parts[1], 10) - 1;
            const day = parseInt(parts[2], 10);
            const nextDay = new Date(year, month, day + 1);

            if (!isNaN(nextDay.getTime())) {
                const nextYear = nextDay.getFullYear();
                const nextMonth = String(nextDay.getMonth() + 1).padStart(2, '0');
                const nextDate = String(nextDay.getDate()).padStart(2, '0');
                const nextDayStr = `${nextYear}-${nextMonth}-${nextDate}`;

                if (!fallbackFrom || nextDayStr > fallbackFrom) {
                    if (toDate && nextDayStr > toDate) {
                        return fallbackFrom || '';
                    }
                    return nextDayStr;
                }
            }
        }
    }

    return fallbackFrom || '';
}

/**
 * Check if the salary form dates deviate from the currently calculated report date range.
 */
export function isSalaryPeriodMismatch(
    reportFrom?: string | null,
    reportTo?: string | null,
    formFrom?: string | null,
    formTo?: string | null
): boolean {
    const rf = (reportFrom || '').trim();
    const rt = (reportTo || '').trim();
    const ff = (formFrom || '').trim();
    const ft = (formTo || '').trim();

    return (ff !== '' && rf !== '' && ff !== rf) || (ft !== '' && rt !== '' && ft !== rt);
}
