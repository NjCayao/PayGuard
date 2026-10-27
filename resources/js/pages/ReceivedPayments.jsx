import { useEffect, useState } from 'react';
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

export default function ReceivedPayments() {
    const { user } = useAuth();
    const [payments, setPayments] = useState([]);
    const [page, setPage] = useState(1);
    const [lastPage, setLastPage] = useState(1);
    const [date, setDate] = useState('');
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');

    async function load(requestedPage = 1, requestedDate = date) {
        setLoading(true);
        setError('');
        try {
            const params = { page: requestedPage };
            if (requestedDate !== '') {
                params.date = requestedDate;
            }
            const response = await api.get('/received-payments', { params });
            setPayments(response.data.data);
            setPage(response.data.meta.current_page);
            setLastPage(response.data.meta.last_page);
        } catch (loadError) {
            setError(errorMessage(loadError, 'No se pudo cargar los pagos.'));
        } finally {
            setLoading(false);
        }
    }

    useEffect(() => {
        load(1, '');
    }, []);

    function handleDateChange(event) {
        const value = event.target.value;
        setDate(value);
        load(1, value);
    }

    function clearDate() {
        setDate('');
        load(1, '');
    }

    const timeZone = user?.business?.timezone;

    return (
        <div>
            <div className="flex flex-wrap items-end justify-between gap-3">
                <h1 className="text-xl font-bold text-slate-900">Pagos recibidos</h1>
                <div className="flex items-end gap-2">
                    <div>
                        <label htmlFor="payment-date" className={labelClass}>
                            Fecha
                        </label>
                        <input
                            id="payment-date"
                            type="date"
                            className={fieldClass}
                            value={date}
                            onChange={handleDateChange}
                        />
                    </div>
                    {date !== '' && (
                        <button type="button" onClick={clearDate} className={secondaryButtonClass}>
                            Ver todos
                        </button>
                    )}
                </div>
            </div>

            {error !== '' && <p className={`${alertErrorClass} mt-4`}>{error}</p>}

            <div className={`${cardClass} mt-4 overflow-x-auto p-0`}>
                {loading ? (
                    <p className="p-6 text-slate-600">Cargando...</p>
                ) : payments.length === 0 ? (
                    <p className="p-6 text-slate-600">
                        Aún no hay pagos registrados. Aparecerán aquí cuando la app del
                        celular envíe las notificaciones de Yape.
                    </p>
                ) : (
                    <table className="w-full min-w-[640px] text-left text-sm">
                        <thead>
                            <tr className="border-b border-slate-200 text-slate-600">
                                <th className="px-6 py-3 font-semibold">Fecha y hora</th>
                                <th className="px-4 py-3 font-semibold">Monto</th>
                                <th className="px-4 py-3 font-semibold">Quién pagó</th>
                                <th className="px-4 py-3 font-semibold">Código</th>
                                <th className="px-4 py-3 font-semibold">Dispositivo</th>
                                <th className="px-4 py-3 font-semibold">Estado</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-200">
                            {payments.map((payment) => (
                                <tr key={payment.id}>
                                    <td className="whitespace-nowrap px-6 py-3 text-slate-900">
                                        {formatDateTime(payment.received_at, timeZone)}
                                    </td>
                                    <td className="whitespace-nowrap px-4 py-3 font-semibold text-slate-900">
                                        {formatMoney(payment.amount)}
                                    </td>
                                    <td className="px-4 py-3 text-slate-900">
                                        {payment.payer_name ?? '—'}
                                    </td>
                                    <td className="px-4 py-3 text-slate-900">
                                        {payment.security_code ?? '—'}
                                    </td>
                                    <td className="px-4 py-3 text-slate-600">
                                        {payment.device.name}
                                    </td>
                                    <td className="px-4 py-3">
                                        {payment.parsed ? (
                                            <span className="rounded-full bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-900">
                                                Leído
                                            </span>
                                        ) : (
                                            <span className="rounded-full bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-900">
                                                No se pudo leer
                                            </span>
                                        )}
                                    </td>
                                </tr>
                            ))}
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
