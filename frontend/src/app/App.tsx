import { NavLink, Navigate, Route, Routes } from 'react-router-dom';
import { LoginPage } from '../features/auth/LoginPage';
import { RosterPage } from '../features/rosters/RosterPage';
import { RostersPage } from '../features/rosters/RostersPage';
import { ShiftsPage } from '../features/shifts/ShiftsPage';
import { StaffPage } from '../features/staff/StaffPage';
import { WardsPage } from '../features/wards/WardsPage';
import { api } from '../shared/api/client';
import { Banner, LoadingBlock } from '../shared/ui/Feedback';
import { SessionProvider, useSession } from './session';

function Shell() {
    const { session, loading, error, refresh, setSession } = useSession();

    if (loading) {
        return <main className="mx-auto max-w-6xl px-4 py-10"><LoadingBlock label="Loading NurseRoster…" /></main>;
    }

    if (!session) {
        return (
            <div className="space-y-4">
                {error ? <div className="mx-auto max-w-md px-4 pt-6"><Banner tone="error" onRetry={() => void refresh()}>{error}</Banner></div> : null}
                <LoginPage />
            </div>
        );
    }

    async function signOut(): Promise<void> {
        await api('/api/v1/logout', { method: 'POST', body: JSON.stringify({}) });
        setSession(null);
    }

    const linkClass = ({ isActive }: { isActive: boolean }) =>
        `rounded-md px-3 py-2 text-sm font-medium ${isActive ? 'bg-teal-600 text-white' : 'text-teal-100 hover:bg-navy-800'}`;

    return (
        <div className="min-h-screen">
            <header className="bg-navy-950 text-white">
                <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-4">
                    <div>
                        <p className="text-lg font-semibold">NurseRoster</p>
                        <p className="text-sm text-teal-100">{session.membership.tenant.name} · {session.membership.role.replace('_', ' ')}</p>
                    </div>
                    <button className="rounded-md bg-navy-800 px-3 py-2 text-sm" type="button" onClick={() => void signOut()}>Sign out</button>
                </div>
                <nav className="mx-auto flex max-w-6xl gap-2 overflow-x-auto px-4 pb-4">
                    <NavLink className={linkClass} to="/rosters">Rosters</NavLink>
                    <NavLink className={linkClass} to="/staff">Staff</NavLink>
                    <NavLink className={linkClass} to="/wards">Wards</NavLink>
                    <NavLink className={linkClass} to="/shifts">Shifts</NavLink>
                </nav>
            </header>
            <main className="mx-auto max-w-6xl px-4 py-6">
                <Routes>
                    <Route path="/" element={<Navigate to="/rosters" replace />} />
                    <Route path="/rosters" element={<RostersPage />} />
                    <Route path="/rosters/:rosterId" element={<RosterPage />} />
                    <Route path="/staff" element={<StaffPage />} />
                    <Route path="/wards" element={<WardsPage />} />
                    <Route path="/shifts" element={<ShiftsPage />} />
                    <Route path="*" element={<Navigate to="/rosters" replace />} />
                </Routes>
            </main>
        </div>
    );
}

export function App() {
    return (
        <SessionProvider>
            <Shell />
        </SessionProvider>
    );
}
