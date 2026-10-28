import { useEffect, useState } from 'react';
import { api, errorMessage, fieldErrors } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import {
    alertErrorClass,
    alertOkClass,
    cardClass,
    fieldClass,
    fieldErrorClass,
    labelClass,
    primaryButtonClass,
} from '../components/ui';

export default function Settings() {
    const { refreshUser } = useAuth();
    const [form, setForm] = useState({
        name: '',
        yape_holder_name: '',
        owner_whatsapp: '',
        match_window_minutes: 10,
        recheck_minutes: 2,
    });
    const [loading, setLoading] = useState(true);
    const [sending, setSending] = useState(false);
    const [errors, setErrors] = useState({});
    const [notice, setNotice] = useState('');
    const [loadError, setLoadError] = useState('');

    useEffect(() => {
        api.get('/settings')
            .then((response) => {
                const business = response.data.data.business;
                setForm({
                    name: business.name ?? '',
                    yape_holder_name: business.yape_holder_name ?? '',
                    owner_whatsapp: business.owner_whatsapp ?? '',
                    match_window_minutes: business.match_window_minutes ?? 10,
                    recheck_minutes: business.recheck_minutes ?? 2,
                });
            })
            .catch((error) => setLoadError(errorMessage(error, 'No se pudo cargar la configuración.')))
            .finally(() => setLoading(false));
    }, []);

    function update(field, value) {
        setForm((current) => ({ ...current, [field]: value }));
    }

    function FieldError({ name }) {
        return errors[name] ? <p className={fieldErrorClass}>{errors[name][0]}</p> : null;
    }

    async function handleSubmit(event) {
        event.preventDefault();
        setSending(true);
        setErrors({});
        setNotice('');

        try {
            await api.put('/settings', {
                ...form,
                yape_holder_name: form.yape_holder_name === '' ? null : form.yape_holder_name,
            });
            setNotice('Configuración guardada.');
            refreshUser();
        } catch (error) {
            const validation = fieldErrors(error);
            setErrors(validation);
            if (Object.keys(validation).length === 0) {
                setErrors({ form: [errorMessage(error, 'No se pudo guardar la configuración.')] });
            }
        } finally {
            setSending(false);
        }
    }

    if (loading) {
        return <p className="text-slate-600">Cargando...</p>;
    }

    if (loadError !== '') {
        return <p className={alertErrorClass}>{loadError}</p>;
    }

    return (
        <div>
            <h1 className="text-xl font-bold text-slate-900">Configuración</h1>

            {notice !== '' && <p className={`${alertOkClass} mt-4`}>{notice}</p>}
            {errors.form && <p className={`${alertErrorClass} mt-4`}>{errors.form[0]}</p>}

            <form onSubmit={handleSubmit} className={`${cardClass} mt-4 space-y-4`}>
                <div>
                    <label htmlFor="settings-name" className={labelClass}>
                        Nombre del negocio
                    </label>
                    <input
                        id="settings-name"
                        type="text"
                        className={fieldClass}
                        value={form.name}
                        onChange={(event) => update('name', event.target.value)}
                    />
                    <FieldError name="name" />
                </div>

                <div>
                    <label htmlFor="settings-holder" className={labelClass}>
                        Titular del Yape
                    </label>
                    <input
                        id="settings-holder"
                        type="text"
                        className={fieldClass}
                        placeholder="Como aparece en tu Yape"
                        value={form.yape_holder_name}
                        onChange={(event) => update('yape_holder_name', event.target.value)}
                    />
                    <p className="mt-1 text-sm text-slate-500">
                        El destinatario de cada captura debe coincidir con este nombre.
                    </p>
                    <FieldError name="yape_holder_name" />
                </div>

                <div>
                    <label htmlFor="settings-whatsapp" className={labelClass}>
                        WhatsApp del dueño (alertas)
                    </label>
                    <input
                        id="settings-whatsapp"
                        type="text"
                        className={fieldClass}
                        placeholder="51982226835"
                        value={form.owner_whatsapp}
                        onChange={(event) => update('owner_whatsapp', event.target.value)}
                    />
                    <FieldError name="owner_whatsapp" />
                </div>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label htmlFor="settings-window" className={labelClass}>
                            Ventana de comparación (minutos)
                        </label>
                        <input
                            id="settings-window"
                            type="number"
                            min="1"
                            max="60"
                            className={fieldClass}
                            value={form.match_window_minutes}
                            onChange={(event) => update('match_window_minutes', event.target.value)}
                        />
                        <FieldError name="match_window_minutes" />
                    </div>
                    <div>
                        <label htmlFor="settings-recheck" className={labelClass}>
                            Revisar pendientes (minutos)
                        </label>
                        <input
                            id="settings-recheck"
                            type="number"
                            min="1"
                            max="10"
                            className={fieldClass}
                            value={form.recheck_minutes}
                            onChange={(event) => update('recheck_minutes', event.target.value)}
                        />
                        <FieldError name="recheck_minutes" />
                    </div>
                </div>

                <button type="submit" disabled={sending} className={primaryButtonClass}>
                    {sending ? 'Guardando...' : 'Guardar'}
                </button>
            </form>
        </div>
    );
}
