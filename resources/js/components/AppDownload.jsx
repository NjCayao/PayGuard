import QRCode from 'react-qr-code';
import { cardClass, primaryButtonClass, secondaryButtonClass } from './ui';

const TITLE = 'Instala la app en el celular donde recibes los Yapes del negocio';
const COMING_SOON = 'La app estará disponible pronto.';
const CASHIER_NOTE = 'Los cajeros no necesitan la app: solo usan WhatsApp.';

function shareText(url, version) {
    const base = `Instala PayGuard en el celular donde recibes los Yapes del negocio: ${url}`;

    return version ? `${base} (versión ${version})` : base;
}

function versionLine(version, date) {
    const parts = [];

    if (version) {
        parts.push(`Versión ${version}`);
    }

    if (date) {
        parts.push(date);
    }

    return parts.join(' · ');
}

export default function AppDownload({ download, variant = 'compact' }) {
    const prominent = variant === 'prominent';
    const frame = prominent
        ? 'rounded-lg border-2 border-emerald-600 bg-emerald-50 p-6 shadow-sm'
        : cardClass;

    if (!download.url) {
        return (
            <section className={frame}>
                <h2 className="font-bold text-slate-900">{TITLE}</h2>
                <p className="mt-2 text-slate-600">{COMING_SOON}</p>
            </section>
        );
    }

    const line = versionLine(download.version, download.date);
    const whatsappHref = `https://wa.me/?text=${encodeURIComponent(
        shareText(download.url, download.version),
    )}`;

    return (
        <section className={frame}>
            <h2 className="font-bold text-slate-900">{TITLE}</h2>

            <div className="mt-4 flex flex-col gap-6 sm:flex-row">
                <div className="flex-1">
                    <div className="flex flex-col gap-3 sm:flex-row">
                        <a href={download.url} download className={primaryButtonClass}>
                            Descargar app
                        </a>
                        <a
                            href={whatsappHref}
                            target="_blank"
                            rel="noreferrer"
                            className={secondaryButtonClass}
                        >
                            Enviar enlace por WhatsApp
                        </a>
                    </div>

                    {line !== '' && <p className="mt-3 text-sm text-slate-600">{line}</p>}

                    <ol className="mt-4 list-decimal space-y-1 pl-5 text-sm text-slate-700">
                        <li>Abre este enlace o escanea el código QR en el celular del negocio.</li>
                        <li>Permite instalar apps de esta fuente cuando Android lo pida.</li>
                        <li>
                            En Android 13 o superior: Ajustes &gt; Apps &gt; PayGuard &gt;
                            menú (tres puntos) &gt; &quot;Permitir ajustes
                            restringidos&quot;.
                        </li>
                        <li>
                            Abre PayGuard, activa la lectura de notificaciones y acepta
                            ignorar la optimización de batería.
                        </li>
                        <li>Inicia sesión con tu misma cuenta del panel.</li>
                    </ol>
                </div>

                <div className="shrink-0 self-start rounded-md border border-slate-200 bg-white p-3">
                    <QRCode value={download.url} size={160} aria-label="Código QR de descarga" />
                </div>
            </div>

            <p className="mt-4 rounded-md border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700">
                {CASHIER_NOTE}
            </p>
        </section>
    );
}
