import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../auth/AuthContext';
import { errorMessage, fieldErrors } from '../api/client';
import {
    alertErrorClass,
    cardClass,
    fieldClass,
    fieldErrorClass,
    labelClass,
    linkButtonClass,
    primaryButtonClass,
} from '../components/ui';

export default function Login() {
    const { login } = useAuth();
    const navigate = useNavigate();
    const [form, setForm] = useState({ email: '', password: '' });
    const [errors, setErrors] = useState({});
    const [generalError, setGeneralError] = useState('');
    const [sending, setSending] = useState(false);

    function update(field, value) {
        setForm((current) => ({ ...current, [field]: value }));
    }

    async function handleSubmit(event) {
        event.preventDefault();
        setSending(true);
        setErrors({});
        setGeneralError('');

        try {
            await login(form);
            navigate('/app');
        } catch (error) {
            const validation = fieldErrors(error);
            setErrors(validation);
            if (Object.keys(validation).length === 0) {
                setGeneralError(errorMessage(error, 'No se pudo iniciar sesión.'));
            }
        } finally {
            setSending(false);
        }
    }

    return (
        <div className="mx-auto w-full max-w-md px-4 py-12">
            <h1 className="text-center text-2xl font-bold text-slate-900">PayGuard</h1>
            <p className="mt-1 text-center text-sm text-slate-600">Iniciar sesión</p>
            <div className={`${cardClass} mt-6`}>
                {generalError !== '' && <p className={alertErrorClass}>{generalError}</p>}
                <form onSubmit={handleSubmit} className="mt-4 space-y-4">
                    <div>
                        <label htmlFor="email" className={labelClass}>
                            Correo
                        </label>
                        <input
                            id="email"
                            type="email"
                            autoComplete="email"
                            className={fieldClass}
                            value={form.email}
                            onChange={(event) => update('email', event.target.value)}
                        />
                        {errors.email && <p className={fieldErrorClass}>{errors.email[0]}</p>}
                    </div>
                    <div>
                        <label htmlFor="password" className={labelClass}>
                            Contraseña
                        </label>
                        <input
                            id="password"
                            type="password"
                            autoComplete="current-password"
                            className={fieldClass}
                            value={form.password}
                            onChange={(event) => update('password', event.target.value)}
                        />
                        {errors.password && (
                            <p className={fieldErrorClass}>{errors.password[0]}</p>
                        )}
                    </div>
                    <button type="submit" disabled={sending} className={primaryButtonClass}>
                        {sending ? 'Entrando...' : 'Entrar'}
                    </button>
                </form>
            </div>
            <p className="mt-4 text-center text-sm text-slate-600">
                ¿No tienes cuenta?{' '}
                <Link to="/app/crear-cuenta" className={linkButtonClass}>
                    Crear cuenta
                </Link>
            </p>
        </div>
    );
}
