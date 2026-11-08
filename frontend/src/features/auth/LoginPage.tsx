import { useState, type FormEvent } from 'react';
import { api } from '../../shared/api/client';
import type { DataResponse, Session } from '../../shared/api/types';
import { Banner } from '../../shared/ui/Feedback';
import { useSession } from '../../app/session';

export function LoginPage() {
    const { setSession } = useSession();
    const [email, setEmail] = useState('admin.north@example.com');
    const [password, setPassword] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [pending, setPending] = useState(false);

    async function onSubmit(event: FormEvent): Promise<void> {
        event.preventDefault();
        setPending(true);
        setError(null);

        try {
            const payload = await api<DataResponse<Session>>('/api/v1/login', {
                method: 'POST',
                body: JSON.stringify({ email, password }),
            });
            setSession(payload.data);
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Sign-in failed.');
        } finally {
            setPending(false);
        }
    }

    return (
        <main className="mx-auto flex min-h-screen max-w-md flex-col justify-center px-4">
            <p className="text-sm font-semibold uppercase tracking-wide text-teal-700">NurseRoster</p>
            <h1 className="mt-2 text-3xl font-semibold text-navy-900">Sign in</h1>
            <p className="mt-2 text-sm text-slate-600">English draft rosters for one hospital. Synthetic demo accounts use example.com.</p>
            <form className="mt-6 space-y-4 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-line" onSubmit={(event) => void onSubmit(event)}>
                {error ? <Banner tone="error">{error}</Banner> : null}
                <label className="block text-sm font-medium">
                    Email
                    <input className="mt-1 w-full rounded-md border border-line px-3 py-2" type="email" autoComplete="username" value={email} onChange={(event) => setEmail(event.target.value)} required />
                </label>
                <label className="block text-sm font-medium">
                    Password
                    <input className="mt-1 w-full rounded-md border border-line px-3 py-2" type="password" autoComplete="current-password" value={password} onChange={(event) => setPassword(event.target.value)} required />
                </label>
                <button className="w-full rounded-md bg-teal-700 px-4 py-2 font-semibold text-white disabled:opacity-60" type="submit" disabled={pending}>
                    {pending ? 'Signing in…' : 'Sign in'}
                </button>
            </form>
        </main>
    );
}
