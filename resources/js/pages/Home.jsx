import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api, errorMessage } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import Bilingual from '../components/Bilingual';
import { alertErrorClass, cardClass } from '../components/ui';
import { isDemo } from '../utils/demo';
import { formatDateTime, formatMoney } from '../utils/format';

function Stat({ label, value, hint }) {
    return (
        <div className={cardClass}>
            <p className="text-sm text-slate-600">{label}</p>
            <p className="mt-1 text-2xl font-bold text-slate-900">{value}</p>
            {hint && <p className="mt-1 text-sm text-slate-600">{hint}</p>}
        </div>
    );
}

export default function Home() {
    const { user } = useAuth();
    const [summary, setSummary] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');

    useEffect(() => {
        api.get('/summary')
            .then((response) => setSummary(response.data.data))
            .catch((loadError) => setError(errorMessage(loadError, 'No se pudo cargar el resumen.')))
            .finally(() => setLoading(false));
    }, []);

    const timeZone = user?.business?.timezone;

    return (
        <div>
            <h1 className="text-xl font-bold text-slate-900">Inicio</h1>
            <p className="mt-1 text-slate-600">
                Hola, {user?.name} · {user?.business?.name}
            </p>

            {isDemo(user) && (
                <Link
                    to="/app/probar"
                    className="mt-4 inline-block rounded-lg bg-emerald-700 px-6 py-3 text-center font-bold text-white shadow-sm hover:bg-emerald-800"
                >
                    <Bilingual es="Probar PayGuard" en="Try PayGuard" />
                </Link>
            )}

            {error !== '' && <p className={`${alertErrorClass} mt-4`}>{error}</p>}

            {loading ? (
                <p className="mt-4 text-slate-600">Cargando...</p>
            ) : (
                summary && (
                    <div className="mt-4">
                        <h2 className="text-sm font-semibold text-slate-700">
                            Resumen de hoy
                        </h2>
                        <div className="mt-2 grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <Stat
                                label="Pagos recibidos"
                                value={summary.received_payments.count}
                                hint={`Total ${formatMoney(summary.received_payments.total)}`}
                            />
                            <Stat
                                label="Celular del negocio"
                                value={summary.device.online ? 'Conectado' : 'Desconectado'}
                                hint={
                                    summary.device.last_seen_at
                                        ? `Última señal ${formatDateTime(summary.device.last_seen_at, timeZone)}`
                                        : 'Sin señal todavía'
                                }
                            />
                            <Stat label="Verificados" value={summary.verifications.verified} />
                            <Stat
                                label="Pendientes"
                                value={summary.verifications.pending}
                            />
                            <Stat
                                label="No confirmados"
                                value={summary.verifications.failed}
                            />
                            <Stat
                                label="Reutilizadas"
                                value={summary.verifications.reused}
                            />
                            <Stat
                                label="Varios pagos: se pidió captura"
                                value={summary.verifications.needs_capture}
                            />
                            <Stat
                                label="Ya confirmados antes"
                                value={summary.verifications.already_confirmed}
                            />
                            <Stat label="Alertas enviadas" value={summary.owner_alerts_sent} />
                        </div>
                    </div>
                )
            )}
        </div>
    );
}
