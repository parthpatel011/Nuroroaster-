import { useMemo, useState } from 'react';
import { Banner, LoadingBlock } from '../../shared/ui/Feedback';
import type { Assignment, RosterGridData } from '../../shared/api/types';
import { dayNumber, shiftLabel } from './formatShift';

interface RosterGridProps {
    grid: RosterGridData | null;
    loading: boolean;
    error: string | null;
    readOnly: boolean;
    selectedShiftId: string;
    onSelectShift: (shiftId: string) => void;
    onRetry: () => void;
    onAssign: (employeeId: string, dutyDate: string) => void;
    onRemove: (assignment: Assignment) => void;
}

export function RosterGrid({
    grid,
    loading,
    error,
    readOnly,
    selectedShiftId,
    onSelectShift,
    onRetry,
    onAssign,
    onRemove,
}: RosterGridProps) {
    const [week, setWeek] = useState(0);
    const [showAll, setShowAll] = useState(false);
    const weekCount = grid ? Math.max(1, Math.ceil(grid.days.length / 7)) : 1;
    const visibleDays = useMemo(() => {
        if (!grid) {
            return [];
        }

        if (showAll) {
            return grid.days;
        }

        return grid.days.slice(week * 7, week * 7 + 7);
    }, [grid, showAll, week]);

    if (loading && !grid) {
        return <LoadingBlock label="Loading roster…" />;
    }

    if (!grid) {
        return <Banner tone="error" onRetry={onRetry}>{error ?? 'The roster could not be loaded.'}</Banner>;
    }

    const byCell = new Map(grid.assignments.map((assignment) => [`${assignment.employee_id}:${assignment.duty_date}`, assignment]));

    return (
        <div className="space-y-4">
            {error ? <Banner tone="error" onRetry={onRetry}>{error}</Banner> : null}
            <Banner>
                Publishing, PDF or PNG export, and WhatsApp sharing are not in this version. This screen edits the draft only.
            </Banner>
            <div className="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 className="text-xl font-semibold text-navy-900">{grid.ward.name}</h2>
                    <p className="text-sm text-slate-600">
                        {grid.period_start} to {grid.period_end} · {grid.timezone} · {grid.status} · version {grid.lock_version}
                    </p>
                </div>
                <label className="text-sm text-slate-700">
                    Shift
                    <select
                        className="mt-1 block rounded-md border border-line bg-white px-3 py-2"
                        value={selectedShiftId}
                        disabled={readOnly}
                        onChange={(event) => onSelectShift(event.target.value)}
                    >
                        {grid.shift_definitions.filter((shift) => shift.status === 'active').map((shift) => (
                            <option key={shift.id} value={shift.id}>
                                {shift.code} {shiftLabel(shift.start_time, shift.end_time, shift.ends_next_day)}
                            </option>
                        ))}
                    </select>
                </label>
            </div>
            <div className="flex flex-wrap gap-2">
                {Array.from({ length: weekCount }, (_, index) => (
                    <button
                        key={index}
                        className={`rounded-md px-3 py-1 text-sm ${!showAll && week === index ? 'bg-navy-900 text-white' : 'bg-white text-navy-900 ring-1 ring-line'}`}
                        type="button"
                        onClick={() => {
                            setShowAll(false);
                            setWeek(index);
                        }}
                    >
                        Week {index + 1}
                    </button>
                ))}
                <button
                    className={`rounded-md px-3 py-1 text-sm ${showAll ? 'bg-navy-900 text-white' : 'bg-white text-navy-900 ring-1 ring-line'}`}
                    type="button"
                    onClick={() => setShowAll(true)}
                >
                    Full month
                </button>
            </div>
            {grid.staff_truncated ? <Banner>Only the first 100 staff records are shown.</Banner> : null}
            <div className="overflow-x-auto rounded-xl border border-line bg-white">
                <table className="min-w-full border-collapse text-sm">
                    <thead className="bg-navy-900 text-white">
                        <tr>
                            <th className="sticky left-0 bg-navy-900 px-3 py-2 text-left font-medium">Staff</th>
                            {visibleDays.map((day) => (
                                <th key={day} className="px-2 py-2 text-center font-medium">
                                    <span className="block text-xs text-teal-100">{day.slice(5, 7)}</span>
                                    {dayNumber(day)}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {grid.staff.map((employee) => (
                            <tr key={employee.id} className="border-t border-line">
                                <th className="sticky left-0 bg-white px-3 py-2 text-left font-medium text-navy-900">
                                    {employee.full_name}
                                    <span className="mt-0.5 block text-xs font-normal text-slate-500">{employee.employee_code}</span>
                                </th>
                                {visibleDays.map((day) => {
                                    const assignment = byCell.get(`${employee.id}:${day}`);

                                    return (
                                        <td key={day} className="px-1 py-1 text-center">
                                            {assignment ? (
                                                <button
                                                    className="w-full rounded-md bg-teal-700 px-1 py-2 text-xs font-semibold text-white"
                                                    type="button"
                                                    disabled={readOnly}
                                                    onClick={() => onRemove(assignment)}
                                                >
                                                    {assignment.shift_code}
                                                    {assignment.ends_next_day ? <span className="mt-0.5 block font-normal">→ next day</span> : null}
                                                </button>
                                            ) : (
                                                <button
                                                    className="w-full rounded-md px-1 py-2 text-xs text-slate-400 ring-1 ring-line hover:bg-teal-100 disabled:hover:bg-transparent"
                                                    type="button"
                                                    disabled={readOnly || employee.status !== 'active'}
                                                    onClick={() => onAssign(employee.id, day)}
                                                >
                                                    {readOnly ? '—' : 'Add'}
                                                </button>
                                            )}
                                        </td>
                                    );
                                })}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
