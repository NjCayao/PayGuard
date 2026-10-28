export function formatDateTime(isoString, timeZone) {
    if (!isoString) {
        return '';
    }

    return new Date(isoString).toLocaleString('es-PE', {
        timeZone: timeZone || 'America/Lima',
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export function formatDate(isoString, timeZone) {
    if (!isoString) {
        return '';
    }

    return new Date(isoString).toLocaleString('es-PE', {
        timeZone: timeZone || 'America/Lima',
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
}

export function formatTime(isoString, timeZone) {
    if (!isoString) {
        return '';
    }

    return new Date(isoString).toLocaleString('es-PE', {
        timeZone: timeZone || 'America/Lima',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export function formatMoney(amount) {
    if (amount === null || amount === undefined) {
        return '—';
    }

    return `S/ ${amount}`;
}
