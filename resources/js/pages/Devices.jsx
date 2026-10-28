import { useEffect, useState } from 'react';
import { api, errorMessage } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import {
    alertErrorClass,
    alertOkClass,
    cardClass,
    dangerButtonClass,
} from '../components/ui';
import { formatDateTime } from '../utils/format';

export default function Devices() {
    const { user } = useAuth();
    const [devices, setDevices] = useState([]);
    const [loading, setLoading] = useState(true);
    const [notice, setNotice] = useState('');
    const [error, setError] = useState('');
    const [workingId, setWorkingId] = useState(null);

    async function load() {
        setLoading(true);
        setError('');
        try {
            const response = await api.get('/devices');
            setDevices(response.data.data);
        } catch (loadError) {
            setError(errorMessage(loadError, 'No se pudo cargar los dispositivos.'));
        } finally {
            setLoading(false);
        }
    }

    useEffect(() => {
        load();
    }, []);

    async function unlink(device) {
        if (!window.confirm(`¿Desvincular ${device.name}? La app de ese celular dejará de enviar pagos.`)) {
            return;
        }

        setNotice('');
        setError('');
        setWorkingId(device.id);
        try {
            await api.post(`/devices/${device.id}/unlink`);
            setNotice(`${device.name} desvinculado.`);
            await load();
        } catch (unlinkError) {
            setError(errorMessage(unlinkError, 'No se pudo desvincular el dispositivo.'));
        } finally {
            setWorkingId(null);
        }
    }

    const timeZone = user?.business?.timezone;

    return (
        <div>
            <h1 className="text-xl font-bold text-slate-900">Dispositivo</h1>

            {notice !== '' && <p className={`${alertOkClass} mt-4`}>{notice}</p>}
            {error !== '' && <p className={`${alertErrorClass} mt-4`}>{error}</p>}

            <div className={`${cardClass} mt-4 p-0`}>
                {loading ? (
                    <p className="p-6 text-slate-600">Cargando...</p>
                ) : devices.length === 0 ? (
                    <p className="p-6 text-slate-600">
                        Aún no hay dispositivos vinculados. Instala la app en el celular
                        que recibe los pagos e inicia sesión con tu cuenta.
                    </p>
                ) : (
                    <ul className="divide-y divide-slate-200">
                        {devices.map((device) => (
                            <li
                                key={device.id}
                                className="flex flex-wrap items-center gap-x-4 gap-y-2 px-6 py-4"
                            >
                                <div className="min-w-0 flex-1">
                                    <p className="truncate font-semibold text-slate-900">
                                        {device.name}
                                    </p>
                                    <p className="text-sm text-slate-600">
                                        Último contacto:{' '}
                                        {device.last_seen_at
                                            ? formatDateTime(device.last_seen_at, timeZone)
                                            : 'nunca'}
                                    </p>
                                    <p className="text-sm text-slate-600">
                                        Último pago:{' '}
                                        {device.last_payment_at
                                            ? formatDateTime(device.last_payment_at, timeZone)
                                            : 'aún no envía pagos'}
                                    </p>
                                </div>
                                <div className="flex items-center gap-3">
                                    {device.is_online ? (
                                        <span className="rounded-full bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-900">
                                            Conectado
                                        </span>
                                    ) : (
                                        <span className="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-600">
                                            Desconectado
                                        </span>
                                    )}
                                    <button
                                        type="button"
                                        disabled={workingId === device.id}
                                        onClick={() => unlink(device)}
                                        className={dangerButtonClass}
                                    >
                                        {workingId === device.id ? 'Desvinculando...' : 'Desvincular'}
                                    </button>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </div>
    );
}
