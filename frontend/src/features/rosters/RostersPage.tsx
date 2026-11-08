import { useEffect, useState, type FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { useSession } from '../../app/session';
import { api } from '../../shared/api/client';
import type { DataResponse, Page, RosterSummary, Ward } from '../../shared/api/types';
import { Banner, LoadingBlock } from '../../shared/ui/Feedback';
import { currentMonthPeriod } from './formatShift';

export function RostersPage() {
    const { session } = useSession();
    const canEdit = session?.membership.role === 'hospital_admin' || session?.membership.role === 'supervisor';
    const month = currentMonthPeriod();
    const [rosters, setRosters] = useState<Page<RosterSummary> | null>(null);
    const [wards, setWards] = useState<Ward[]>([]);
    const [wardId, setWardId] = useState('');
    const [periodStart, setPeriodStart] = useState(month.start);
    const [periodEnd, setPeriodEnd] = useState(month.end);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [pending, setPending] = useState(false);

    async function load(): Promise<void> {
        setLoading(true);
        setError(null);

        try {
            const [rosterPage, wardPage] = await Promise.all([
                api<Page<RosterSummary>>('/api/v1/rosters?per_page=50'),
                api<Page<Ward>>('/api/v1/wards?per_page=50'),
            ]);
            setRosters(rosterPage);
            setWards(wardPage.data);
            setWardId((current) => current || wardPage.data[0]?.id || '');
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Rosters could not be loaded.');
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
            await api<DataResponse<RosterSummary>>('/api/v1/rosters', {
                method: 'POST',
                body: JSON.stringify({ ward_id: wardId, period_start: periodStart, period_end: periodEnd }),
            });
            await load();
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'The roster could not be created.');
        } finally {
            setPending(false);
        }
    }

    return (
        <section className="space-y-4">
            <header>
                <h2 className="text-2xl font-semibold text-navy-900">Roster drafts</h2>
                <p className="text-sm text-slate-600">Create a draft and assign duties. Nothing here is published.</p>
            </header>
            {error ? <Banner tone="error" onRetry={() => void load()}>{error}</Banner> : null}
            {canEdit ? (
                <form className="grid gap-3 rounded-xl bg-white p-4 ring-1 ring-line md:grid-cols-4" onSubmit={(event) => void onSubmit(event)}>
                    <select className="rounded-md border border-line px-3 py-2" value={wardId} onChange={(event) => setWardId(event.target.value)} required>
                        {wards.map((ward) => (
                            <option key={ward.id} value={ward.id}>{ward.name}</option>
                        ))}
                    </select>
                    <input className="rounded-md border border-line px-3 py-2" type="date" value={periodStart} onChange={(event) => setPeriodStart(event.target.value)} required />
                    <input className="rounded-md border border-line px-3 py-2" type="date" value={periodEnd} onChange={(event) => setPeriodEnd(event.target.value)} required />
                    <button className="rounded-md bg-teal-700 px-4 py-2 font-semibold text-white disabled:opacity-60" type="submit" disabled={pending || wardId === ''}>
                        {pending ? 'Creating…' : 'Create draft'}
                    </button>
                </form>
            ) : null}
            {loading ? <LoadingBlock label="Loading rosters…" /> : null}
            {!loading && rosters ? (
                <ul className="divide-y divide-line overflow-hidden rounded-xl bg-white ring-1 ring-line">
                    {rosters.data.length === 0 ? <li className="px-4 py-6 text-sm text-slate-600">No drafts yet.</li> : null}
                    {rosters.data.map((roster) => (
                        <li key={roster.id}>
                            <Link className="block px-4 py-3 hover:bg-teal-100" to={`/rosters/${roster.id}`}>
                                <span className="font-medium">{roster.ward.name}</span>
                                <span className="mt-1 block text-sm text-slate-600">{roster.period_start} to {roster.period_end} · {roster.status}</span>
                            </Link>
                        </li>
                    ))}
                </ul>
            ) : null}
        </section>
    );
}
