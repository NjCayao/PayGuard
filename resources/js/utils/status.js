export const toneClass = {
    ok: 'rounded-full bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-900',
    wait: 'rounded-full bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-900',
    warn: 'rounded-full bg-orange-50 px-2 py-1 text-xs font-semibold text-orange-900',
    bad: 'rounded-full bg-red-50 px-2 py-1 text-xs font-semibold text-red-900',
    info: 'rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700',
};

export const inputTypeLabel = {
    image: 'Imagen',
    audio: 'Audio',
    text: 'Texto',
};

export function verificationStatus(item) {
    if (item.reason_code === 'offline') {
        return item.status === 'pending'
            ? { label: 'Pendiente: celular desconectado', tone: 'warn' }
            : { label: 'No confirmado: celular desconectado', tone: 'warn' };
    }

    if (item.reason_code === 'multiple_matches') {
        return { label: 'Varios pagos: se pidió captura', tone: 'wait' };
    }

    if (item.reason_code === 'already_confirmed') {
        return { label: 'Ya confirmado antes', tone: 'info' };
    }

    switch (item.status) {
        case 'verified':
            return { label: 'Verificado', tone: 'ok' };
        case 'processing':
            return { label: 'En revisión', tone: 'wait' };
        case 'pending':
            return { label: 'Pendiente', tone: 'wait' };
        case 'reused':
            return { label: 'Reutilizada', tone: 'bad' };
        case 'mismatch':
            return { label: 'No coincide', tone: 'bad' };
        case 'error':
            return { label: 'Error', tone: 'bad' };
        default:
            return { label: 'No encontrado', tone: 'bad' };
    }
}

export function alertStatus(item) {
    switch (item.status) {
        case 'queued':
            return { label: 'En cola', tone: 'wait' };
        case 'sent':
            return { label: 'Enviado', tone: 'info' };
        case 'delivered':
            return { label: 'Entregado', tone: 'ok' };
        case 'read':
            return { label: 'Leído', tone: 'ok' };
        default:
            return { label: 'Fallido', tone: 'bad' };
    }
}
