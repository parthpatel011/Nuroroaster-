interface BannerProps {
    children: string;
    tone?: 'error' | 'info';
    onRetry?: () => void;
}

export function Banner({ children, tone = 'info', onRetry }: BannerProps) {
    const toneClass = tone === 'error' ? 'border-rose-200 bg-rose-50 text-rose-900' : 'border-teal-100 bg-teal-100 text-navy-900';

    return (
        <div className={`flex flex-wrap items-center justify-between gap-3 rounded-lg border px-4 py-3 text-sm ${toneClass}`} role={tone === 'error' ? 'alert' : 'status'}>
            <p>{children}</p>
            {onRetry ? (
                <button className="rounded-md bg-white px-3 py-1 font-medium text-navy-900 shadow-sm" type="button" onClick={onRetry}>
                    Retry
                </button>
            ) : null}
        </div>
    );
}

export function LoadingBlock({ label }: { label: string }) {
    return (
        <p className="rounded-lg border border-line bg-white px-4 py-8 text-center text-sm text-slate-600" role="status">
            {label}
        </p>
    );
}
