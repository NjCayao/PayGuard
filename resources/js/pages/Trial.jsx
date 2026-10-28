import { useEffect, useState } from 'react';
import { Navigate } from 'react-router-dom';
import { api, errorMessage } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import { isDemo } from '../utils/demo';
import Bilingual from '../components/Bilingual';
import {
    alertErrorClass,
    cardClass,
    fieldClass,
    labelClass,
    primaryButtonClass,
    secondaryButtonClass,
} from '../components/ui';
import { formatDateTime, formatMoney } from '../utils/format';
import { toneClass, verificationStatus } from '../utils/status';

const STORAGE_KEY = 'payguard_trial_key';

function storedKey() {
    let key = sessionStorage.getItem(STORAGE_KEY);

    if (!key) {
        key = newKey();
        sessionStorage.setItem(STORAGE_KEY, key);
    }

    return key;
}

function newKey() {
    if (crypto?.randomUUID) {
        return crypto.randomUUID();
    }

    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (char) => {
        const random = (Math.random() * 16) | 0;
        return (char === 'x' ? random : (random & 0x3) | 0x8).toString(16);
    });
}

function Step({ number, es, en, children }) {
    return (
        <div className={`${cardClass} mt-4`}>
            <h2 className="text-sm font-semibold text-slate-900">
                <span className="mr-2 inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-700 text-xs font-bold text-white">
                    {number}
                </span>
                <Bilingual es={es} en={en} />
            </h2>
            <div className="mt-3">{children}</div>
        </div>
    );
}

function Row({ label, value }) {
    return (
        <div className="flex flex-wrap gap-1 py-1">
            <dt className="w-36 shrink-0 text-sm text-slate-600">{label}</dt>
            <dd className="text-sm font-medium text-slate-900">{value}</dd>
        </div>
    );
}

function AiStep({ result, timeZone }) {
    const { ai } = result;

    if (ai.raw_text !== undefined) {
        return (
            <dl className="divide-y divide-slate-100">
                <Row label="Consulta / Query" value={ai.raw_text ?? '—'} />
                <Row label="Billetera / Wallet" value={ai.wallet === 'plin' ? 'Plin' : 'Yape'} />
                <Row label="Monto / Amount" value={formatMoney(ai.amount)} />
                <Row label="Nombre buscado / Name" value={ai.payer_name ?? '—'} />
                <Row label="Tiempo IA / AI time" value={`${ai.ai_ms ?? 0} ms`} />
            </dl>
        );
    }

    return (
        <dl className="divide-y divide-slate-100">
            <Row label="Billetera / Wallet" value={ai.wallet === 'plin' ? 'Plin' : 'Yape'} />
            <Row label="Monto / Amount" value={formatMoney(ai.amount)} />
            <Row label="Destinatario / To" value={ai.recipient_name ?? '—'} />
            <Row
                label="Fecha y hora / Date"
                value={ai.paid_at ? formatDateTime(ai.paid_at, timeZone) : '—'}
            />
            <Row label="Código / Code" value={ai.security_code ?? '—'} />
            <Row label="Operación / Operation" value={ai.operation_code ?? '—'} />
            <Row
                label="Señales de edición / Edits"
                value={ai.edit_signals?.length > 0 ? ai.edit_signals.join('; ') : 'Ninguna / None'}
            />
            <Row label="Tiempo IA / AI time" value={`${ai.ai_ms ?? 0} ms`} />
        </dl>
    );
}

function MatchStep({ result, timeZone }) {
    if (!result.match) {
        return <p className="text-sm text-slate-600">No se buscó ningún pago. / No payment searched.</p>;
    }

    const { searched, found } = result.match;

    return (
        <div>
            <p className="text-sm text-slate-600">
                Se buscó un pago {searched.wallet === 'plin' ? 'Plin' : 'Yape'} de{' '}
                {formatMoney(searched.amount)} en ±{searched.window_minutes} minutos.
                <span className="block text-xs text-slate-500">
                    Searched for a {searched.wallet === 'plin' ? 'Plin' : 'Yape'} payment of{' '}
                    {formatMoney(searched.amount)} within ±{searched.window_minutes} minutes.
                </span>
            </p>
            {found ? (
                <dl className="mt-2 divide-y divide-slate-100">
                    <Row label="Monto / Amount" value={formatMoney(found.amount)} />
                    <Row label="Quién pagó / Payer" value={found.payer_name ?? '—'} />
                    <Row label="Código / Code" value={found.security_code ?? '—'} />
                    <Row
                        label="Recibido / Received"
                        value={formatDateTime(found.received_at, timeZone)}
                    />
                </dl>
            ) : (
                <p className="mt-2 text-sm font-medium text-slate-900">
                    No se encontró ningún pago así. / No such payment found.
                </p>
            )}
        </div>
    );
}

export default function Trial() {
    const { user, loading: authLoading } = useAuth();
    const [key, setKey] = useState(storedKey);
    const [tab, setTab] = useState('image');
    const [file, setFile] = useState(null);
    const [preview, setPreview] = useState(null);
    const [text, setText] = useState('');
    const [session, setSession] = useState(null);
    const [result, setResult] = useState(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');

    const headers = { 'X-Trial-Key': key };
    const timeZone = user?.business?.timezone;

    useEffect(() => {
        api.get('/trial/session', { headers: { 'X-Trial-Key': key } })
            .then((response) => setSession(response.data.data))
            .catch(() => setSession(null));
    }, [key]);

    if (!authLoading && !isDemo(user)) {
        return <Navigate to="/app" replace />;
    }

    function pickFile(event) {
        const chosen = event.target.files?.[0] ?? null;

        setFile(chosen);
        setPreview(chosen ? URL.createObjectURL(chosen) : null);
        setResult(null);
        setError('');
    }

    function reset() {
        const fresh = newKey();

        sessionStorage.setItem(STORAGE_KEY, fresh);
        setKey(fresh);
        setFile(null);
        setPreview(null);
        setText('');
        setResult(null);
        setError('');
    }

    function failureMessage(failure) {
        if (failure?.response?.status === 429) {
            return 'Límite de pruebas alcanzado, espera una hora. / Hourly test limit reached, please wait.';
        }

        return errorMessage(failure, 'No se pudo completar la prueba. / The test failed.');
    }

    async function submitImage(event) {
        event.preventDefault();

        if (!file || loading) {
            return;
        }

        setLoading(true);
        setError('');
        setResult(null);

        const form = new FormData();
        form.append('image', file);

        try {
            const response = await api.post('/trial/image', form, { headers });
            setResult(response.data.data);
        } catch (failure) {
            setError(failureMessage(failure));
        } finally {
            setLoading(false);
        }
    }

    async function submitQuery(event) {
        event.preventDefault();

        if (text.trim() === '' || loading) {
            return;
        }

        setLoading(true);
        setError('');
        setResult(null);

        try {
            const response = await api.post('/trial/query', { text: text.trim() }, { headers });
            setResult(response.data.data);
        } catch (failure) {
            setError(failureMessage(failure));
        } finally {
            setLoading(false);
        }
    }

    const badge = result ? verificationStatus(result) : null;

    return (
        <div className="max-w-3xl">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 className="text-xl font-bold text-slate-900">
                        <Bilingual es="Probar PayGuard" en="Try PayGuard" />
                    </h1>
                    <p className="mt-1 text-sm text-slate-600">
                        <Bilingual
                            es="Prueba el motor real con pagos de ejemplo. Nada se envía por WhatsApp."
                            en="Test the real engine with sample payments. Nothing is sent over WhatsApp."
                        />
                    </p>
                </div>
                <button type="button" onClick={reset} className={secondaryButtonClass}>
                    <Bilingual es="Reiniciar mis pruebas" en="Reset my tests" />
                </button>
            </div>

            <div className={`${cardClass} mt-4`}>
                <h2 className="text-sm font-semibold text-slate-900">
                    <Bilingual es="Capturas de ejemplo" en="Sample receipts" />
                </h2>
                <div className="mt-3 flex flex-wrap gap-2">
                    <a
                        href={`/api/panel/trial/samples/valid?trial_key=${key}`}
                        className={secondaryButtonClass}
                    >
                        <Bilingual es="Descargar captura válida" en="Expects ✅" />
                    </a>
                    <a
                        href={`/api/panel/trial/samples/fake?trial_key=${key}`}
                        className={secondaryButtonClass}
                    >
                        <Bilingual es="Descargar captura falsa (monto editado)" en="Expects ❌ mismatch" />
                    </a>
                </div>
                <ol className="mt-3 list-decimal space-y-1 pl-5 text-sm text-slate-600">
                    <li>
                        <Bilingual
                            es="Sube la válida: verás ✅ pago verificado."
                            en="Upload the valid one: you will see ✅ verified."
                        />
                    </li>
                    <li>
                        <Bilingual
                            es="Súbela otra vez: verás “captura ya usada”."
                            en="Upload it again: you will see “already used”."
                        />
                    </li>
                    <li>
                        <Bilingual
                            es="Pulsa Reiniciar y sube la falsa: verás ❌ no coincide."
                            en="Press Reset and upload the fake one: you will see ❌ mismatch."
                        />
                    </li>
                </ol>
            </div>

            <div className="mt-4 flex gap-2">
                <button
                    type="button"
                    onClick={() => setTab('image')}
                    className={tab === 'image' ? primaryButtonClass : secondaryButtonClass}
                >
                    <Bilingual es="Subir captura" en="Upload receipt" />
                </button>
                <button
                    type="button"
                    onClick={() => setTab('query')}
                    className={tab === 'query' ? primaryButtonClass : secondaryButtonClass}
                >
                    <Bilingual es="Escribir consulta" en="Type a query" />
                </button>
            </div>

            {tab === 'image' ? (
                <form onSubmit={submitImage} className={`${cardClass} mt-4`}>
                    <label htmlFor="trial-image" className={labelClass}>
                        <Bilingual es="Captura del pago" en="Payment screenshot" />
                    </label>
                    <input
                        id="trial-image"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        onChange={pickFile}
                        className={`${fieldClass} cursor-pointer`}
                    />
                    {preview && (
                        <img
                            src={preview}
                            alt="Vista previa de la captura / Receipt preview"
                            className="mt-3 max-h-64 rounded-md border border-slate-200"
                        />
                    )}
                    <button
                        type="submit"
                        disabled={!file || loading}
                        className={`${primaryButtonClass} mt-3`}
                    >
                        <Bilingual es="Verificar captura" en="Verify receipt" />
                    </button>
                </form>
            ) : (
                <form onSubmit={submitQuery} className={`${cardClass} mt-4`}>
                    <label htmlFor="trial-text" className={labelClass}>
                        <Bilingual es="Pregunta como un cajero" en="Ask like a cashier" />
                    </label>
                    <input
                        id="trial-text"
                        type="text"
                        value={text}
                        maxLength={500}
                        onChange={(event) => setText(event.target.value)}
                        placeholder="yape de 50"
                        className={fieldClass}
                    />
                    <div className="mt-2 flex flex-wrap gap-2">
                        {['yape de 50', '¿llegó un yape de 20 de Luz?'].map((suggestion) => (
                            <button
                                key={suggestion}
                                type="button"
                                onClick={() => setText(suggestion)}
                                className={secondaryButtonClass}
                            >
                                {suggestion}
                            </button>
                        ))}
                    </div>
                    <button
                        type="submit"
                        disabled={text.trim() === '' || loading}
                        className={`${primaryButtonClass} mt-3`}
                    >
                        <Bilingual es="Preguntar" en="Ask" />
                    </button>
                </form>
            )}

            {loading && (
                <p className="mt-4 animate-pulse text-sm font-medium text-slate-700">
                    <Bilingual
                        es="Analizando con el motor real…"
                        en="Analyzing with the real engine…"
                    />
                </p>
            )}

            {error !== '' && <p className={`${alertErrorClass} mt-4`}>{error}</p>}

            {result && badge && (
                <div>
                    <Step number="1" es="Qué leyó la IA" en="What the AI read">
                        <AiStep result={result} timeZone={timeZone} />
                    </Step>

                    <Step number="2" es="Qué pago real buscó" en="Real payment searched">
                        <MatchStep result={result} timeZone={timeZone} />
                    </Step>

                    <Step
                        number="3"
                        es="Lo que recibiría el cajero por WhatsApp"
                        en="What the cashier would get on WhatsApp"
                    >
                        <div className="rounded-lg rounded-tl-none bg-emerald-100 p-3 shadow-sm">
                            <p className="text-sm text-slate-900">{result.reply}</p>
                        </div>
                        <p className="mt-2">
                            <span className={toneClass[badge.tone]}>{badge.label}</span>
                        </p>
                        {result.alert_simulated && (
                            <p className="mt-2 text-sm text-slate-600">
                                <Bilingual
                                    es="Se registró una alerta simulada para el dueño."
                                    en="A simulated owner alert was logged."
                                />
                            </p>
                        )}
                    </Step>
                </div>
            )}

            {session && (
                <p className="mt-4 text-xs text-slate-500">
                    {session.limits.per_hour} pruebas por hora · {session.limits.max_image_mb} MB
                    máximo por imagen. / {session.limits.per_hour} tests per hour ·{' '}
                    {session.limits.max_image_mb} MB max per image.
                </p>
            )}
        </div>
    );
}
