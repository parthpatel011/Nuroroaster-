import { describe, expect, it } from 'vitest';
import { currentMonthPeriod, shiftLabel } from './formatShift';

describe('shift labels', () => {
    it('marks overnight shifts', () => {
        expect(shiftLabel('20:00', '08:00', true)).toBe('20:00–08:00 → next day');
        expect(shiftLabel('07:00', '15:00', false)).toBe('07:00–15:00');
    });

    it('builds a local calendar month', () => {
        expect(currentMonthPeriod(new Date(2026, 1, 10))).toEqual({
            start: '2026-02-01',
            end: '2026-02-28',
        });
    });
});
