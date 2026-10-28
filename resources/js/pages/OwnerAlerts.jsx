import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api, errorMessage } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import DateCell from '../components/DateCell';
import Pagination from '../components/Pagination';
import {
    alertErrorClass,
    cardClass,
    fieldClass,
    labelClass,
    secondaryButtonClass,
} from '../components/ui';
import { formatMoney } from '../utils/format';
import { alertStatus, toneClass } from '../utils/status';

const statusOptions = [
    { value: '', label: 'Todos' },
    { value: 'queued', label: 'En cola' },
    { value: 'sent', label: 'Enviado' },
    { value: 'delivered', label: 'Entregado' },
    { value: 'read', label: 'Leído' },
    { value: 'failed', label: 'Fallido' },
];

const emptyFilters = { date_from: '', date_to: '', status: '' };

export default function OwnerAlerts() {
    const { user } = useAuth();
    const [items, setItems] = useState([]);
    const [filters, setFilters] = useState(emptyFilters);
    const [meta, setMeta] = useState(null);
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
            const response = await api.get('/owner-alerts', { params });
            setItems(response.data.data);
            setMeta(response.data.meta);
        } catch (loadError) {
            setError(errorMessage(loadError, 'No se pudo cargar las alertas.'));
        } finally {
            setLoading(false);
        }
    }

    useEffect(() => {
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

    const timeZone = user?.business?.timezone;
    const hasFilters = Object.values(filters).some((value) => value !== '');

    return (
        <div>
            <h1 className="text-xl font-bold text-slate-900">Alertas al dueño</h1>

            <div className={`${cardClass} mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3`}>
                <div>
                    <label htmlFor="alert-from" className={labelClass}>
                        Desde
                    </label>
                    <input
                        id="alert-from"
                        type="date"
                        className={fieldClass}
                        value={filters.date_from}
                        onChange={(event) => updateFilter('date_from', event.target.value)}
                    />
                </div>
                <div>
                    <label htmlFor="alert-to" className={labelClass}>
                        Hasta
                    </label>
                    <input
                        id="alert-to"
                        type="date"
                        className={fieldClass}
                        value={filters.date_to}
                        onChange={(event) => updateFilter('date_to', event.target.value)}
                    />
                </div>
                <div>
                    <label htmlFor="alert-status" className={labelClass}>
                        Estado de entrega
                    </label>
                    <select
                        id="alert-status"
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
                        Aún no se envió ninguna alerta. Aparecerán aquí cuando un pago
                        parezca sospechoso o el celular se desconecte.
                    </p>
                ) : (
                    <table className="w-full min-w-[600px] text-left text-sm">
                        <thead>
                            <tr className="border-b border-slate-200 text-slate-600">
                                <th className="px-6 py-3 font-semibold">Fecha</th>
                                <th className="px-4 py-3 font-semibold">Para</th>
                                <th className="px-4 py-3 font-semibold">Verificación</th>
                                <th className="px-4 py-3 font-semibold">Motivo</th>
                                <th className="px-4 py-3 font-semibold">Entrega</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-200">
                            {items.map((item) => {
                                const state = alertStatus(item);
                                return (
                                    <tr key={item.id}>
                                        <td className="px-6 py-3 text-slate-900">
                                            <DateCell value={item.created_at} timeZone={timeZone} />
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-slate-900">
                                            {item.to}
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3">
                                            {item.verification ? (
                                                <Link
                                                    to={`/app/verificaciones/${item.verification.id}`}
                                                    className="font-semibold text-emerald-800 hover:underline"
                                                >
                                                    {formatMoney(item.verification.amount)}
                                                </Link>
                                            ) : (
                                                <span className="text-slate-600">—</span>
                                            )}
                                        </td>
                                        <td
                                            className="max-w-[240px] truncate px-4 py-3 text-slate-600"
                                            title={item.reason}
                                        >
                                            {item.reason}
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3">
                                            <span className={toneClass[state.tone]}>{state.label}</span>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                )}
            </div>

            <Pagination meta={meta} onChange={(next) => load(next)} />
        </div>
    );
}
