import { useEffect, useState, type FormEvent } from 'react';
import { useSession } from '../../app/session';
import { api } from '../../shared/api/client';
import type { DataResponse, Page, ShiftDefinition } from '../../shared/api/types';
import { Banner, LoadingBlock } from '../../shared/ui/Feedback';
import { shiftLabel } from '../rosters/formatShift';

export function ShiftsPage() {
    const { session } = useSession();
    const canEdit = session?.membership.role === 'hospital_admin';
    const [page, setPage] = useState<Page<ShiftDefinition> | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [code, setCode] = useState('');
    const [name, setName] = useState('');
    const [start, setStart] = useState('07:00');
    const [end, setEnd] = useState('15:00');
    const [pending, setPending] = useState(false);

    async function load(): Promise<void> {
        setLoading(true);
        setError(null);

        try {
            setPage(await api<Page<ShiftDefinition>>('/api/v1/shift-definitions?per_page=50'));
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Shifts could not be loaded.');
        } finally {
            setLoading(false);
        }
    }

    useEffect(() => {
        void load();
    }, []);

    async function onSubmit(event: FormEvent): Promise<void> {
        event.preventDefault();
        setPending(true);
        setError(null);

        try {
            await api<DataResponse<ShiftDefinition>>('/api/v1/shift-definitions', {
                method: 'POST',
                body: JSON.stringify({ code, name, start_time: start, end_time: end, break_minutes: 0 }),
            });
            setCode('');
            setName('');
            await load();
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'The shift could not be saved.');
        } finally {
            setPending(false);
        }
    }

    return (
        <section className="space-y-4">
            <header>
                <h2 className="text-2xl font-semibold text-navy-900">Shift definitions</h2>
                <p className="text-sm text-slate-600">Times are hospital local time. An end at or before the start finishes the next day.</p>
            </header>
            {error ? <Banner tone="error" onRetry={() => void load()}>{error}</Banner> : null}
            {canEdit ? (
                <form className="grid gap-3 rounded-xl bg-white p-4 ring-1 ring-line md:grid-cols-5" onSubmit={(event) => void onSubmit(event)}>
                    <input className="rounded-md border border-line px-3 py-2" placeholder="Code" value={code} onChange={(event) => setCode(event.target.value)} required />
                    <input className="rounded-md border border-line px-3 py-2" placeholder="Name" value={name} onChange={(event) => setName(event.target.value)} required />
                    <input className="rounded-md border border-line px-3 py-2" type="time" value={start} onChange={(event) => setStart(event.target.value)} required />
                    <input className="rounded-md border border-line px-3 py-2" type="time" value={end} onChange={(event) => setEnd(event.target.value)} required />
                    <button className="rounded-md bg-teal-700 px-4 py-2 font-semibold text-white disabled:opacity-60" type="submit" disabled={pending}>
                        {pending ? 'Saving…' : 'Add shift'}
                    </button>
                </form>
            ) : null}
            {loading ? <LoadingBlock label="Loading shifts…" /> : null}
            {!loading && page ? (
                <ul className="divide-y divide-line overflow-hidden rounded-xl bg-white ring-1 ring-line">
                    {page.data.map((shift) => (
                        <li key={shift.id} className="px-4 py-3">
                            <span className="font-medium">{shift.name}</span>
                            <span className="ml-2 text-sm text-slate-500">{shift.code}</span>
                            <span className="mt-1 block text-sm">{shiftLabel(shift.start_time, shift.end_time, shift.ends_next_day)}</span>
                        </li>
                    ))}
                </ul>
            ) : null}
        </section>
    );
}
