import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api, errorMessage } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import {
    alertErrorClass,
    cardClass,
    fieldClass,
    labelClass,
    secondaryButtonClass,
} from '../components/ui';
import { formatDateTime, formatMoney } from '../utils/format';
import { toneClass, verificationStatus } from '../utils/status';

const statusOptions = [
    { value: '', label: 'Todos' },
    { value: 'verified', label: 'Verificado' },
    { value: 'pending', label: 'Pendiente' },
    { value: 'processing', label: 'En revisión' },
    { value: 'not_found', label: 'No encontrado' },
    { value: 'mismatch', label: 'No coincide' },
    { value: 'reused', label: 'Reutilizada' },
    { value: 'error', label: 'Error' },
];

const emptyFilters = { date_from: '', date_to: '', cashier_id: '', status: '' };

export default function Verifications() {
    const { user } = useAuth();
    const [items, setItems] = useState([]);
    const [cashiers, setCashiers] = useState([]);
    const [filters, setFilters] = useState(emptyFilters);
    const [page, setPage] = useState(1);
    const [lastPage, setLastPage] = useState(1);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');

    async function load(requestedPage = 1, requestedFilters = filters) {
        setLoading(true);
        setError('');
        try {
            const params = { page: requestedPage };
            Object.entries(requestedFilters).forEach(([key, value]) => {
                if (value !== '') {
                    params[key] = value;
                }
            });
            const response = await api.get('/verifications', { params });
            setItems(response.data.data);
            setPage(response.data.meta.current_page);
            setLastPage(response.data.meta.last_page);
        } catch (loadError) {
            setError(errorMessage(loadError, 'No se pudo cargar las verificaciones.'));
        } finally {
            setLoading(false);
        }
    }

    useEffect(() => {
        api.get('/cashiers')
            .then((response) => setCashiers(response.data.data))
            .catch(() => setCashiers([]));
        load(1, emptyFilters);
    }, []);

    function updateFilter(field, value) {
        const next = { ...filters, [field]: value };
        setFilters(next);
        load(1, next);
    }

    function clearFilters() {
        setFilters(emptyFilters);
        load(1, emptyFilters);
    }

    function exportUrl(format) {
        const params = new URLSearchParams({ format });
        Object.entries(filters).forEach(([key, value]) => {
            if (value !== '') {
                params.append(key, value);
            }
        });
        return `/api/panel/verifications/export?${params.toString()}`;
    }

    const timeZone = user?.business?.timezone;
    const hasFilters = Object.values(filters).some((value) => value !== '');

    return (
        <div>
            <div className="flex flex-wrap items-end justify-between gap-3">
                <h1 className="text-xl font-bold text-slate-900">Verificaciones</h1>
                <div className="flex gap-2">
                    <a
                        href={exportUrl('xlsx')}
                        className={secondaryButtonClass}
                        target="_blank"
                        rel="noreferrer"
                    >
                        Excel
                    </a>
                    <a
                        href={exportUrl('pdf')}
                        className={secondaryButtonClass}
                        target="_blank"
                        rel="noreferrer"
                    >
                        PDF
                    </a>
                </div>
            </div>

            <div className={`${cardClass} mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4`}>
                <div>
                    <label htmlFor="filter-from" className={labelClass}>
                        Desde
                    </label>
                    <input
                        id="filter-from"
                        type="date"
                        className={fieldClass}
                        value={filters.date_from}
                        onChange={(event) => updateFilter('date_from', event.target.value)}
                    />
                </div>
                <div>
                    <label htmlFor="filter-to" className={labelClass}>
                        Hasta
                    </label>
                    <input
                        id="filter-to"
                        type="date"
                        className={fieldClass}
                        value={filters.date_to}
                        onChange={(event) => updateFilter('date_to', event.target.value)}
                    />
                </div>
                <div>
                    <label htmlFor="filter-cashier" className={labelClass}>
                        Cajero
                    </label>
                    <select
                        id="filter-cashier"
                        className={fieldClass}
                        value={filters.cashier_id}
                        onChange={(event) => updateFilter('cashier_id', event.target.value)}
                    >
                        <option value="">Todos</option>
                        {cashiers.map((cashier) => (
                            <option key={cashier.id} value={cashier.id}>
                                {cashier.name}
                            </option>
                        ))}
                    </select>
                </div>
                <div>
                    <label htmlFor="filter-status" className={labelClass}>
                        Estado
                    </label>
                    <select
                        id="filter-status"
                        className={fieldClass}
                        value={filters.status}
                        onChange={(event) => updateFilter('status', event.target.value)}
                    >
                        {statusOptions.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                </div>
            </div>

            {hasFilters && (
                <button type="button" onClick={clearFilters} className={`${secondaryButtonClass} mt-3`}>
                    Limpiar filtros
                </button>
            )}

            {error !== '' && <p className={`${alertErrorClass} mt-4`}>{error}</p>}

            <div className={`${cardClass} mt-4 overflow-x-auto p-0`}>
                {loading ? (
                    <p className="p-6 text-slate-600">Cargando...</p>
                ) : items.length === 0 ? (
                    <p className="p-6 text-slate-600">
                        Aún no hay verificaciones. Aparecerán aquí cuando los cajeros
                        consulten por WhatsApp.
                    </p>
                ) : (
                    <table className="w-full min-w-[760px] text-left text-sm">
                        <thead>
                            <tr className="border-b border-slate-200 text-slate-600">
                                <th className="px-6 py-3 font-semibold">Fecha</th>
                                <th className="px-4 py-3 font-semibold">Cajero</th>
                                <th className="px-4 py-3 font-semibold">Monto</th>
                                <th className="px-4 py-3 font-semibold">Destinatario</th>
                                <th className="px-4 py-3 font-semibold">Resultado</th>
                                <th className="px-4 py-3 font-semibold">Motivo</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-200">
                            {items.map((item) => {
                                const badge = verificationStatus(item);
                                return (
                                    <tr key={item.id}>
                                        <td className="whitespace-nowrap px-6 py-3 text-slate-900">
                                            <Link
                                                to={`/app/verificaciones/${item.id}`}
                                                className="font-semibold text-emerald-800 hover:underline"
                                            >
                                                {formatDateTime(item.created_at, timeZone)}
                                            </Link>
                                        </td>
                                        <td className="px-4 py-3 text-slate-900">{item.cashier.name}</td>
                                        <td className="whitespace-nowrap px-4 py-3 font-semibold text-slate-900">
                                            {formatMoney(item.amount)}
                                        </td>
                                        <td className="px-4 py-3 text-slate-900">
                                            {item.recipient_name ?? '—'}
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3">
                                            <span className={toneClass[badge.tone]}>{badge.label}</span>
                                        </td>
                                        <td className="max-w-[220px] truncate px-4 py-3 text-slate-600">
                                            {item.reason ?? '—'}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                )}
            </div>

            {lastPage > 1 && (
                <div className="mt-4 flex items-center justify-between">
                    <button
                        type="button"
                        disabled={page <= 1}
                        onClick={() => load(page - 1)}
                        className={secondaryButtonClass}
                    >
                        Anterior
                    </button>
                    <p className="text-sm text-slate-600">
                        Página {page} de {lastPage}
                    </p>
                    <button
                        type="button"
                        disabled={page >= lastPage}
                        onClick={() => load(page + 1)}
                        className={secondaryButtonClass}
                    >
                        Siguiente
                    </button>
                </div>
            )}
        </div>
    );
}
