import { useEffect, useState, type FormEvent } from 'react';
import { useSession } from '../../app/session';
import { api } from '../../shared/api/client';
import type { DataResponse, Page, Ward } from '../../shared/api/types';
import { Banner, LoadingBlock } from '../../shared/ui/Feedback';

export function WardsPage() {
    const { session } = useSession();
    const canEdit = session?.membership.role === 'hospital_admin';
    const [page, setPage] = useState<Page<Ward> | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [code, setCode] = useState('');
    const [name, setName] = useState('');
    const [pending, setPending] = useState(false);

    async function load(): Promise<void> {
        setLoading(true);
        setError(null);

        try {
            setPage(await api<Page<Ward>>('/api/v1/wards?per_page=50'));
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Wards could not be loaded.');
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
            await api<DataResponse<Ward>>('/api/v1/wards', {
                method: 'POST',
                body: JSON.stringify({ code, name }),
            });
            setCode('');
            setName('');
            await load();
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'The ward could not be saved.');
        } finally {
            setPending(false);
        }
    }

    return (
        <section className="space-y-4">
            <header>
                <h2 className="text-2xl font-semibold text-navy-900">Wards</h2>
                <p className="text-sm text-slate-600">A roster belongs to one ward. Departments and branches are not in this version.</p>
            </header>
            {error ? <Banner tone="error" onRetry={() => void load()}>{error}</Banner> : null}
            {canEdit ? (
                <form className="grid gap-3 rounded-xl bg-white p-4 ring-1 ring-line sm:grid-cols-[1fr_2fr_auto]" onSubmit={(event) => void onSubmit(event)}>
                    <input className="rounded-md border border-line px-3 py-2" placeholder="Code" value={code} onChange={(event) => setCode(event.target.value)} required />
                    <input className="rounded-md border border-line px-3 py-2" placeholder="Name" value={name} onChange={(event) => setName(event.target.value)} required />
                    <button className="rounded-md bg-teal-700 px-4 py-2 font-semibold text-white disabled:opacity-60" type="submit" disabled={pending}>
                        {pending ? 'Saving…' : 'Add ward'}
                    </button>
                </form>
            ) : null}
            {loading ? <LoadingBlock label="Loading wards…" /> : null}
            {!loading && page ? (
                <ul className="divide-y divide-line overflow-hidden rounded-xl bg-white ring-1 ring-line">
                    {page.data.map((ward) => (
                        <li key={ward.id} className="px-4 py-3">
                            <span className="font-medium">{ward.name}</span>
                            <span className="ml-2 text-sm text-slate-500">{ward.code}</span>
                        </li>
                    ))}
                </ul>
            ) : null}
        </section>
    );
}
