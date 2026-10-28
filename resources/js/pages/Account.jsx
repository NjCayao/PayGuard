import { useEffect, useState } from 'react';
import { api, errorMessage, fieldErrors } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import DemoNotice from '../components/DemoNotice';
import {
    alertErrorClass,
    alertOkClass,
    cardClass,
    fieldClass,
    fieldErrorClass,
    labelClass,
    primaryButtonClass,
} from '../components/ui';
import { DEMO_BLOCKED_MESSAGE, isDemo } from '../utils/demo';

function FieldError({ errors, name }) {
    return errors[name] ? <p className={fieldErrorClass}>{errors[name][0]}</p> : null;
}

export default function Account() {
    const { user, refreshUser } = useAuth();
    const demo = isDemo(user);

    const [account, setAccount] = useState(null);
    const [loading, setLoading] = useState(true);
    const [loadError, setLoadError] = useState('');

    const [emailForm, setEmailForm] = useState({ email: '', current_password: '' });
    const [emailErrors, setEmailErrors] = useState({});
    const [emailNotice, setEmailNotice] = useState('');
    const [emailSending, setEmailSending] = useState(false);

    const [passwordForm, setPasswordForm] = useState({
        current_password: '',
        password: '',
        password_confirmation: '',
    });
    const [passwordErrors, setPasswordErrors] = useState({});
    const [passwordNotice, setPasswordNotice] = useState('');
    const [passwordSending, setPasswordSending] = useState(false);

    const [whatsappForm, setWhatsappForm] = useState({ owner_whatsapp: '' });
    const [whatsappErrors, setWhatsappErrors] = useState({});
    const [whatsappNotice, setWhatsappNotice] = useState('');
    const [whatsappSending, setWhatsappSending] = useState(false);

    useEffect(() => {
        api.get('/account')
            .then((response) => {
                const data = response.data.data;
                setAccount(data);
                setEmailForm({ email: data.email ?? '', current_password: '' });
                setWhatsappForm({ owner_whatsapp: data.business?.owner_whatsapp ?? '' });
            })
            .catch((error) => setLoadError(errorMessage(error, 'No se pudo cargar tu cuenta.')))
            .finally(() => setLoading(false));
    }, []);

    async function submitEmail(event) {
        event.preventDefault();
        setEmailSending(true);
        setEmailErrors({});
        setEmailNotice('');

        try {
            const response = await api.put('/account/email', emailForm);
            setAccount(response.data.data);
            setEmailForm({ email: response.data.data.email, current_password: '' });
            setEmailNotice('Correo actualizado.');
            refreshUser();
        } catch (error) {
            const validation = fieldErrors(error);
            setEmailErrors(validation);
            if (Object.keys(validation).length === 0) {
                setEmailErrors({ form: [errorMessage(error, 'No se pudo actualizar el correo.')] });
            }
        } finally {
            setEmailSending(false);
        }
    }

    async function submitPassword(event) {
        event.preventDefault();
        setPasswordSending(true);
        setPasswordErrors({});
        setPasswordNotice('');

        try {
            await api.put('/account/password', passwordForm);
            setPasswordForm({ current_password: '', password: '', password_confirmation: '' });
            setPasswordNotice('Contraseña actualizada. Se cerraron tus otras sesiones.');
        } catch (error) {
            const validation = fieldErrors(error);
            setPasswordErrors(validation);
            if (Object.keys(validation).length === 0) {
                setPasswordErrors({ form: [errorMessage(error, 'No se pudo actualizar la contraseña.')] });
            }
        } finally {
            setPasswordSending(false);
        }
    }

    async function submitWhatsapp(event) {
        event.preventDefault();
        setWhatsappSending(true);
        setWhatsappErrors({});
        setWhatsappNotice('');

        try {
            const response = await api.put('/account/owner-whatsapp', whatsappForm);
            setAccount(response.data.data);
            setWhatsappNotice('WhatsApp actualizado.');
            refreshUser();
        } catch (error) {
            const validation = fieldErrors(error);
            setWhatsappErrors(validation);
            if (Object.keys(validation).length === 0) {
                setWhatsappErrors({ form: [errorMessage(error, 'No se pudo actualizar el WhatsApp.')] });
            }
        } finally {
            setWhatsappSending(false);
        }
    }

    if (loading) {
        return <p className="text-slate-600">Cargando...</p>;
    }

    if (loadError !== '') {
        return <p className={alertErrorClass}>{loadError}</p>;
    }

    const blockedTitle = demo ? DEMO_BLOCKED_MESSAGE : undefined;

    return (
        <div>
            <h1 className="text-xl font-bold text-slate-900">Mi cuenta</h1>
            <p className="mt-1 text-sm text-slate-600">
                {account?.name} · {account?.business?.name}
            </p>

            {demo && <DemoNotice />}

            <form onSubmit={submitEmail} className={`${cardClass} mt-4 space-y-4`}>
                <h2 className="font-semibold text-slate-900">Cambiar correo</h2>

                {emailNotice !== '' && <p className={alertOkClass}>{emailNotice}</p>}
                {emailErrors.form && <p className={alertErrorClass}>{emailErrors.form[0]}</p>}

                <div>
                    <label htmlFor="account-email" className={labelClass}>
                        Correo
                    </label>
                    <input
                        id="account-email"
                        type="email"
                        className={fieldClass}
                        disabled={demo}
                        title={blockedTitle}
                        value={emailForm.email}
                        onChange={(event) =>
                            setEmailForm((current) => ({ ...current, email: event.target.value }))
                        }
                    />
                    <FieldError errors={emailErrors} name="email" />
                </div>

                <div>
                    <label htmlFor="account-email-password" className={labelClass}>
                        Contraseña actual
                    </label>
                    <input
                        id="account-email-password"
                        type="password"
                        autoComplete="current-password"
                        className={fieldClass}
                        disabled={demo}
                        title={blockedTitle}
                        value={emailForm.current_password}
                        onChange={(event) =>
                            setEmailForm((current) => ({ ...current, current_password: event.target.value }))
                        }
                    />
                    <FieldError errors={emailErrors} name="current_password" />
                </div>

                <button
                    type="submit"
                    disabled={demo || emailSending}
                    title={blockedTitle}
                    className={primaryButtonClass}
                >
                    {emailSending ? 'Guardando...' : 'Guardar correo'}
                </button>
            </form>

            <form onSubmit={submitPassword} className={`${cardClass} mt-4 space-y-4`}>
                <h2 className="font-semibold text-slate-900">Cambiar contraseña</h2>

                {passwordNotice !== '' && <p className={alertOkClass}>{passwordNotice}</p>}
                {passwordErrors.form && <p className={alertErrorClass}>{passwordErrors.form[0]}</p>}

                <div>
                    <label htmlFor="account-current-password" className={labelClass}>
                        Contraseña actual
                    </label>
                    <input
                        id="account-current-password"
                        type="password"
                        autoComplete="current-password"
                        className={fieldClass}
                        disabled={demo}
                        title={blockedTitle}
                        value={passwordForm.current_password}
                        onChange={(event) =>
                            setPasswordForm((current) => ({ ...current, current_password: event.target.value }))
                        }
                    />
                    <FieldError errors={passwordErrors} name="current_password" />
                </div>

                <div>
                    <label htmlFor="account-new-password" className={labelClass}>
                        Nueva contraseña
                    </label>
                    <input
                        id="account-new-password"
                        type="password"
                        autoComplete="new-password"
                        className={fieldClass}
                        disabled={demo}
                        title={blockedTitle}
                        value={passwordForm.password}
                        onChange={(event) =>
                            setPasswordForm((current) => ({ ...current, password: event.target.value }))
                        }
                    />
                    <FieldError errors={passwordErrors} name="password" />
                </div>

                <div>
                    <label htmlFor="account-new-password-confirm" className={labelClass}>
                        Repetir nueva contraseña
                    </label>
                    <input
                        id="account-new-password-confirm"
                        type="password"
                        autoComplete="new-password"
                        className={fieldClass}
                        disabled={demo}
                        title={blockedTitle}
                        value={passwordForm.password_confirmation}
                        onChange={(event) =>
                            setPasswordForm((current) => ({
                                ...current,
                                password_confirmation: event.target.value,
                            }))
                        }
                    />
                </div>

                <button
                    type="submit"
                    disabled={demo || passwordSending}
                    title={blockedTitle}
                    className={primaryButtonClass}
                >
                    {passwordSending ? 'Guardando...' : 'Guardar contraseña'}
                </button>
            </form>

            <form onSubmit={submitWhatsapp} className={`${cardClass} mt-4 space-y-4`}>
                <h2 className="font-semibold text-slate-900">WhatsApp del dueño</h2>
                <p className="text-sm text-slate-500">
                    Aquí recibes las alertas de pagos sospechosos.
                </p>

                {whatsappNotice !== '' && <p className={alertOkClass}>{whatsappNotice}</p>}
                {whatsappErrors.form && <p className={alertErrorClass}>{whatsappErrors.form[0]}</p>}

                <div>
                    <label htmlFor="account-whatsapp" className={labelClass}>
                        Número de WhatsApp
                    </label>
                    <input
                        id="account-whatsapp"
                        type="text"
                        inputMode="tel"
                        placeholder="51999888777"
                        className={fieldClass}
                        disabled={demo}
                        title={blockedTitle}
                        value={whatsappForm.owner_whatsapp}
                        onChange={(event) => setWhatsappForm({ owner_whatsapp: event.target.value })}
                    />
                    <FieldError errors={whatsappErrors} name="owner_whatsapp" />
                </div>

                <button
                    type="submit"
                    disabled={demo || whatsappSending}
                    title={blockedTitle}
                    className={primaryButtonClass}
                >
                    {whatsappSending ? 'Guardando...' : 'Guardar WhatsApp'}
                </button>
            </form>
        </div>
    );
}
