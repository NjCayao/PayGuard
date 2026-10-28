import { activePageButtonClass, pageButtonClass, secondaryButtonClass } from './ui';

function pageNumbers(current, last) {
    const pages = new Set([1, last, current - 1, current, current + 1]);

    return [...pages].filter((page) => page >= 1 && page <= last).sort((a, b) => a - b);
}

export default function Pagination({ meta, onChange }) {
    if (!meta || meta.total < 1) {
        return null;
    }

    const current = meta.current_page;
    const last = meta.last_page;
    const numbers = pageNumbers(current, last);
    const items = [];

    numbers.forEach((page, index) => {
        if (index > 0 && page - numbers[index - 1] > 1) {
            items.push(
                <span key={`gap-${page}`} className="px-1 text-sm text-slate-400">
                    …
                </span>
            );
        }

        items.push(
            <button
                key={page}
                type="button"
                disabled={page === current}
                onClick={() => onChange(page)}
                className={page === current ? activePageButtonClass : pageButtonClass}
                aria-current={page === current ? 'page' : undefined}
            >
                {page}
            </button>
        );
    });

    return (
        <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
            <p className="text-sm text-slate-600">
                Mostrando {meta.from}–{meta.to} de {meta.total}
            </p>
            <div className="flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    disabled={current <= 1}
                    onClick={() => onChange(current - 1)}
                    className={secondaryButtonClass}
                >
                    Anterior
                </button>
                {items}
                <button
                    type="button"
                    disabled={current >= last}
                    onClick={() => onChange(current + 1)}
                    className={secondaryButtonClass}
                >
                    Siguiente
                </button>
            </div>
        </div>
    );
}
