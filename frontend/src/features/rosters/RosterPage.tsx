import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { useSession } from '../../app/session';
import { ApiError, api } from '../../shared/api/client';
import type { Assignment, DataResponse, RosterGridData } from '../../shared/api/types';
import { RosterGrid } from './RosterGrid';

export function RosterPage() {
    const { rosterId = '' } = useParams();
    const { session } = useSession();
    const readOnly = session?.membership.role === 'staff';
    const [grid, setGrid] = useState<RosterGridData | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [selectedShiftId, setSelectedShiftId] = useState('');
    const [saving, setSaving] = useState(false);

    async function load(keepError = false): Promise<RosterGridData | null> {
        setLoading(true);
        if (!keepError) {
            setError(null);
        }

        try {
            const payload = await api<DataResponse<RosterGridData>>(`/api/v1/rosters/${rosterId}`);
            setGrid(payload.data);
            setSelectedShiftId((current) => current || payload.data.shift_definitions.find((shift) => shift.status === 'active')?.id || '');

            return payload.data;
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'The roster could not be loaded.');

            return null;
        } finally {
            setLoading(false);
        }
    }

    useEffect(() => {
        void load();
    }, [rosterId]);

    async function onAssign(employeeId: string, dutyDate: string): Promise<void> {
        if (!grid || saving || selectedShiftId === '') {
            if (selectedShiftId === '') {
                setError('Choose a shift first.');
            }

            return;
        }

        setSaving(true);

        try {
            await api(`/api/v1/rosters/${grid.id}/assignments`, {
                method: 'POST',
                body: JSON.stringify({
                    employee_id: employeeId,
                    shift_definition_id: selectedShiftId,
                    duty_date: dutyDate,
                    lock_version: grid.lock_version,
                }),
            });
            await load();
        } catch (caught) {
            setError(caught instanceof ApiError ? caught.message : 'The duty could not be saved.');
            await load(true);
        } finally {
            setSaving(false);
        }
    }

    async function onRemove(assignment: Assignment): Promise<void> {
        if (!grid || saving || !window.confirm('Remove this duty from the draft?')) {
            return;
        }

        setSaving(true);

        try {
            await api(`/api/v1/rosters/${grid.id}/assignments/${assignment.id}`, {
                method: 'DELETE',
                body: JSON.stringify({ lock_version: grid.lock_version }),
            });
            await load();
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'The duty could not be removed.');
            await load(true);
        } finally {
            setSaving(false);
        }
    }

    return (
        <section className="space-y-4">
            <Link className="text-sm font-medium text-teal-700" to="/rosters">Back to drafts</Link>
            <RosterGrid
                grid={grid}
                loading={loading}
                error={error}
                readOnly={readOnly || saving || grid?.status !== 'draft'}
                selectedShiftId={selectedShiftId}
                onSelectShift={setSelectedShiftId}
                onRetry={() => void load()}
                onAssign={(employeeId, dutyDate) => void onAssign(employeeId, dutyDate)}
                onRemove={(assignment) => void onRemove(assignment)}
            />
        </section>
    );
}
