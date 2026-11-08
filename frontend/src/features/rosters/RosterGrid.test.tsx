import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import type { RosterGridData } from '../../shared/api/types';
import { RosterGrid } from './RosterGrid';

const grid: RosterGridData = {
    id: 'roster-1',
    status: 'draft',
    lock_version: 3,
    period_start: '2026-10-30',
    period_end: '2026-11-02',
    timezone: 'Asia/Kolkata',
    ward: { id: 'ward-1', code: 'GEN', name: 'General Ward' },
    days: ['2026-10-30', '2026-10-31', '2026-11-01'],
    staff_truncated: false,
    staff: [
        {
            id: 'nurse-1',
            employee_code: 'N-01',
            full_name: 'Nurse Demo 01',
            status: 'active',
            inactive_from: null,
        },
    ],
    shift_definitions: [
        {
            id: 'night',
            code: 'NIGHT',
            name: 'Night',
            start_time: '20:00',
            end_time: '08:00',
            ends_next_day: true,
            break_minutes: 30,
            status: 'active',
        },
    ],
    assignments: [
        {
            id: 'assignment-1',
            employee_id: 'nurse-1',
            shift_definition_id: 'night',
            duty_date: '2026-10-31',
            starts_at: '2026-10-31T14:30:00+00:00',
            ends_at: '2026-11-01T02:30:00+00:00',
            ends_next_day: true,
            shift_code: 'NIGHT',
            shift_name: 'Night',
            start_time: '20:00',
            end_time: '08:00',
        },
    ],
};

describe('RosterGrid', () => {
    it('shows a loading state', () => {
        render(
            <RosterGrid
                grid={null}
                loading
                error={null}
                readOnly
                selectedShiftId=""
                onSelectShift={() => undefined}
                onRetry={() => undefined}
                onAssign={() => undefined}
                onRemove={() => undefined}
            />,
        );

        expect(screen.getByRole('status').textContent).toContain('Loading roster');
    });

    it('shows an error state with retry', async () => {
        const onRetry = vi.fn();
        render(
            <RosterGrid
                grid={null}
                loading={false}
                error="The roster could not be loaded."
                readOnly
                selectedShiftId=""
                onSelectShift={() => undefined}
                onRetry={onRetry}
                onAssign={() => undefined}
                onRemove={() => undefined}
            />,
        );

        expect(screen.getByRole('alert').textContent).toContain('could not be loaded');
        await userEvent.click(screen.getByRole('button', { name: 'Retry' }));
        expect(onRetry).toHaveBeenCalledOnce();
    });

    it('shows an overnight duty and does not offer unpublished sharing', () => {
        render(
            <RosterGrid
                grid={grid}
                loading={false}
                error={null}
                readOnly={false}
                selectedShiftId="night"
                onSelectShift={() => undefined}
                onRetry={() => undefined}
                onAssign={() => undefined}
                onRemove={() => undefined}
            />,
        );

        expect(screen.getByRole('button', { name: /NIGHT/ }).textContent).toContain('→ next day');
        expect(screen.getByText(/WhatsApp sharing are not in this version/)).toBeTruthy();
        expect(screen.queryByRole('button', { name: /share/i })).toBeNull();
    });
});
