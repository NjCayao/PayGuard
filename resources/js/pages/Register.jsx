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

const emptyForm = {
    business_name: '',
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    owner_whatsapp: '',
};

export default function Register() {
    const { register } = useAuth();
    const navigate = useNavigate();
    const [form, setForm] = useState(emptyForm);
    const [errors, setErrors] = useState({});
    const [generalError, setGeneralError] = useState('');
    const [sending, setSending] = useState(false);

    function update(field, value) {
        setForm((current) => ({ ...current, [field]: value }));
    }

    function field(name, label, props = {}) {
        return (
            <div>
                <label htmlFor={name} className={labelClass}>
                    {label}
                </label>
                <input
                    id={name}
                    className={fieldClass}
                    value={form[name]}
                    onChange={(event) => update(name, event.target.value)}
                    {...props}
                />
                {errors[name] && <p className={fieldErrorClass}>{errors[name][0]}</p>}
            </div>
        );
    }

    async function handleSubmit(event) {
        event.preventDefault();
        setSending(true);
        setErrors({});
        setGeneralError('');

        try {
            await register(form);
            navigate('/app');
        } catch (error) {
            const validation = fieldErrors(error);
            setErrors(validation);
            if (Object.keys(validation).length === 0) {
                setGeneralError(errorMessage(error, 'No se pudo crear la cuenta.'));
            }
        } finally {
            setSending(false);
        }
    }

    return (
        <div className="mx-auto w-full max-w-md px-4 py-12">
            <h1 className="text-center text-2xl font-bold text-slate-900">PayGuard</h1>
            <p className="mt-1 text-center text-sm text-slate-600">Crear cuenta</p>
            <div className={`${cardClass} mt-6`}>
                {generalError !== '' && <p className={alertErrorClass}>{generalError}</p>}
                <form onSubmit={handleSubmit} className="mt-4 space-y-4">
                    {field('business_name', 'Nombre del negocio', {
                        autoComplete: 'organization',
                    })}
                    {field('name', 'Tu nombre', { autoComplete: 'name' })}
                    {field('email', 'Correo', { type: 'email', autoComplete: 'email' })}
                    {field('owner_whatsapp', 'Tu WhatsApp', {
                        inputMode: 'tel',
                        autoComplete: 'tel',
                        placeholder: '+51 982 226 835',
                    })}
                    {field('password', 'Contraseña', {
                        type: 'password',
                        autoComplete: 'new-password',
                    })}
                    {field('password_confirmation', 'Repite la contraseña', {
                        type: 'password',
                        autoComplete: 'new-password',
                    })}
                    <button type="submit" disabled={sending} className={primaryButtonClass}>
                        {sending ? 'Creando cuenta...' : 'Crear cuenta'}
                    </button>
                </form>
            </div>
            <p className="mt-4 text-center text-sm text-slate-600">
                ¿Ya tienes cuenta?{' '}
                <Link to="/app/iniciar-sesion" className={linkButtonClass}>
                    Iniciar sesión
                </Link>
            </p>
        </div>
    );
}
