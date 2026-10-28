import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { api, errorMessage } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import { alertErrorClass, cardClass, linkButtonClass } from '../components/ui';
import { formatDateTime, formatMoney } from '../utils/format';
import { alertStatus, toneClass, verificationStatus } from '../utils/status';

function Row({ label, value }) {
    return (
        <div className="flex flex-wrap gap-1 py-2">
            <dt className="w-36 shrink-0 text-sm text-slate-600">{label}</dt>
            <dd className="text-sm font-medium text-slate-900">{value}</dd>
        </div>
    );
}

export default function VerificationDetail() {
    const { id } = useParams();
    const { user } = useAuth();
    const [item, setItem] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');

    useEffect(() => {
        api.get(`/verifications/${id}`)
            .then((response) => setItem(response.data.data))
            .catch((loadError) => setError(errorMessage(loadError, 'No se pudo cargar la verificación.')))
            .finally(() => setLoading(false));
    }, [id]);

    const timeZone = user?.business?.timezone;

    if (loading) {
        return <p className="text-slate-600">Cargando...</p>;
    }

    if (error !== '' || item === null) {
        return (
            <div>
                <p className={alertErrorClass}>{error}</p>
                <Link to="/app/verificaciones" className={`${linkButtonClass} mt-4 inline-block`}>
                    Volver a verificaciones
                </Link>
            </div>
        );
    }

    const badge = verificationStatus(item);

    return (
        <div>
            <Link to="/app/verificaciones" className={linkButtonClass}>
                ← Volver a verificaciones
            </Link>

            <div className="mt-3 flex flex-wrap items-center gap-3">
                <h1 className="text-xl font-bold text-slate-900">
                    Verificación del {formatDateTime(item.created_at, timeZone)}
                </h1>
                <span className={toneClass[badge.tone]}>{badge.label}</span>
            </div>

            <div className={`${cardClass} mt-4`}>
                <dl className="divide-y divide-slate-100">
                    <Row label="Cajero" value={item.cashier.name} />
                    <Row label="Monto" value={formatMoney(item.amount)} />
                    <Row label="Destinatario" value={item.recipient_name ?? '—'} />
                    <Row label="Billetera" value={item.wallet ?? '—'} />
                    <Row label="Código" value={item.security_code ?? '—'} />
                    <Row label="Operación" value={item.operation_code ?? '—'} />
                    <Row
                        label="Hora del pago"
                        value={item.paid_at ? formatDateTime(item.paid_at, timeZone) : '—'}
                    />
                    <Row label="Motivo" value={item.reason ?? '—'} />
                    {item.edit_signals && item.edit_signals.length > 0 && (
                        <Row label="Señales de edición" value={item.edit_signals.join('; ')} />
                    )}
                </dl>
            </div>

            {item.has_image && (
                <div className={`${cardClass} mt-4`}>
                    <h2 className="text-sm font-semibold text-slate-900">Captura recibida</h2>
                    <img
                        src={item.image_url}
                        alt="Captura del pago enviada por el cajero"
                        className="mt-3 max-h-[480px] rounded-md border border-slate-200"
                    />
                </div>
            )}

            {item.received_payment && (
                <div className={`${cardClass} mt-4`}>
                    <h2 className="text-sm font-semibold text-slate-900">Pago que la confirmó</h2>
                    <dl className="mt-2 divide-y divide-slate-100">
                        <Row label="Monto" value={formatMoney(item.received_payment.amount)} />
                        <Row label="Quién pagó" value={item.received_payment.payer_name ?? '—'} />
                        <Row label="Código" value={item.received_payment.security_code ?? '—'} />
                        <Row
                            label="Recibido"
                            value={formatDateTime(item.received_payment.received_at, timeZone)}
                        />
                    </dl>
                </div>
            )}

            <div className={`${cardClass} mt-4`}>
                <h2 className="text-sm font-semibold text-slate-900">Alertas al dueño</h2>
                {!item.alerts || item.alerts.length === 0 ? (
                    <p className="mt-2 text-sm text-slate-600">
                        Esta verificación no generó alertas.
                    </p>
                ) : (
                    <ul className="mt-2 divide-y divide-slate-100">
                        {item.alerts.map((alert) => {
                            const state = alertStatus(alert);
                            return (
                                <li key={alert.id} className="flex flex-wrap items-center gap-2 py-2">
                                    <span className={toneClass[state.tone]}>{state.label}</span>
                                    <span className="text-sm text-slate-600">
                                        {formatDateTime(alert.created_at, timeZone)} · al {alert.to}
                                    </span>
                                    <span className="w-full text-sm text-slate-900">{alert.reason}</span>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>
        </div>
    );
}
