import { createContext, useContext, useEffect, useState, type ReactNode } from 'react';
import { api } from '../shared/api/client';
import type { DataResponse, Session } from '../shared/api/types';

interface SessionState {
    session: Session | null;
    loading: boolean;
    error: string | null;
    refresh: () => Promise<void>;
    setSession: (session: Session | null) => void;
}

const SessionContext = createContext<SessionState | null>(null);

export function SessionProvider({ children }: { children: ReactNode }) {
    const [session, setSession] = useState<Session | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    async function refresh(): Promise<void> {
        setLoading(true);
        setError(null);

        try {
            const payload = await api<DataResponse<Session>>('/api/v1/me');
            setSession(payload.data);
        } catch (caught) {
            setSession(null);
            if (caught instanceof Error && !('status' in caught && caught.status === 401)) {
                const status = 'status' in caught ? caught.status : null;
                if (status !== 401) {
                    setError(caught.message);
                }
            }
        } finally {
            setLoading(false);
        }
    }

    useEffect(() => {
        void refresh();
    }, []);

    return (
        <SessionContext.Provider value={{ session, loading, error, refresh, setSession }}>
            {children}
        </SessionContext.Provider>
    );
}

export function useSession(): SessionState {
    const value = useContext(SessionContext);

    if (!value) {
        throw new Error('Session is unavailable.');
    }

    return value;
}
