export class ApiError extends Error {
    readonly status: number;

    readonly code: string | null;

    constructor(message: string, status: number, code: string | null) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.code = code;
    }
}

function csrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return match?.[1] ? decodeURIComponent(match[1]) : '';
}

export async function api<T>(path: string, init: RequestInit = {}): Promise<T> {
    const headers = new Headers(init.headers);
    headers.set('Accept', 'application/json');

    if (init.body !== undefined) {
        headers.set('Content-Type', 'application/json');
        headers.set('X-XSRF-TOKEN', csrfToken());
    }

    const response = await fetch(path, {
        ...init,
        headers,
        credentials: 'same-origin',
    });

    if (response.status === 204) {
        return undefined as T;
    }

    const payload: unknown = await response.json().catch(() => null);

    if (!response.ok) {
        const record = isRecord(payload) ? payload : {};
        const detail = stringField(record, 'detail') ?? stringField(record, 'message') ?? 'The request failed.';
        throw new ApiError(detail, response.status, stringField(record, 'code'));
    }

    return payload as T;
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null;
}

function stringField(record: Record<string, unknown>, key: string): string | null {
    const value = record[key];

    return typeof value === 'string' ? value : null;
}
