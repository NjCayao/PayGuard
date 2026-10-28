import { useEffect, useState } from 'react';
import { api, errorMessage, fieldErrors } from '../api/client';
import {
    alertErrorClass,
    alertOkClass,
    cardClass,
    dangerButtonClass,
    fieldClass,
    fieldErrorClass,
    labelClass,
    primaryButtonClass,
    secondaryButtonClass,
} from '../components/ui';

const emptyForm = { name: '', whatsapp: '' };

export default function Cashiers() {
    const [cashiers, setCashiers] = useState([]);
    const [page, setPage] = useState(1);
    const [lastPage, setLastPage] = useState(1);
    const [loading, setLoading] = useState(true);
    const [showForm, setShowForm] = useState(false);
    const [editing, setEditing] = useState(null);
    const [form, setForm] = useState(emptyForm);
    const [errors, setErrors] = useState({});
    const [notice, setNotice] = useState('');
    const [formError, setFormError] = useState('');
    const [sending, setSending] = useState(false);

    async function load(requestedPage = 1) {
        setLoading(true);
        try {
            const response = await api.get('/cashiers', { params: { page: requestedPage } });
            setCashiers(response.data.data);
            setPage(response.data.meta.current_page);
            setLastPage(response.data.meta.last_page);
        } catch {
            setNotice('');
            setFormError('No se pudo cargar la lista de cajeros.');
        } finally {
            setLoading(false);
        }
    }

    useEffect(() => {
        load(1);
    }, []);

    function openCreate() {
        setEditing(null);
        setForm(emptyForm);
        setErrors({});
        setFormError('');
        setShowForm(true);
    }

    function openEdit(cashier) {
        setEditing(cashier);
        setForm({ name: cashier.name, whatsapp: cashier.whatsapp });
        setErrors({});
        setFormError('');
        setShowForm(true);
    }

    function update(field, value) {
        setForm((current) => ({ ...current, [field]: value }));
    }

    async function handleSubmit(event) {
        event.preventDefault();
        setSending(true);
        setErrors({});
        setFormError('');

        try {
            if (editing) {
                await api.put(`/cashiers/${editing.id}`, form);
                setNotice('Cajero actualizado.');
            } else {
                await api.post('/cashiers', form);
                setNotice('Cajero agregado.');
            }
            setShowForm(false);
            await load(editing ? page : 1);
        } catch (error) {
            const validation = fieldErrors(error);
            setErrors(validation);
            if (Object.keys(validation).length === 0) {
                setFormError(errorMessage(error, 'No se pudo guardar el cajero.'));
            }
        } finally {
            setSending(false);
        }
    }

    async function toggleActive(cashier) {
        setNotice('');
        const action = cashier.is_active ? 'deactivate' : 'activate';
        try {
            await api.patch(`/cashiers/${cashier.id}/${action}`);
            await load(page);
        } catch (error) {
            setNotice(errorMessage(error, 'No se pudo cambiar el estado.'));
        }
    }

    async function remove(cashier) {
        if (!window.confirm(`¿Eliminar a ${cashier.name}?`)) {
            return;
        }

        setNotice('');
        try {
            await api.delete(`/cashiers/${cashier.id}`);
            setNotice('Cajero eliminado.');
            await load(page);
        } catch (error) {
            setNotice(errorMessage(error, 'No se pudo eliminar el cajero.'));
        }
    }

    return (
        <div>
            <div className="flex items-center justify-between">
                <h1 className="text-xl font-bold text-slate-900">Cajeros</h1>
                <button type="button" onClick={openCreate} className={primaryButtonClass}>
                    Agregar cajero
                </button>
            </div>

            {notice !== '' && <p className={`${alertOkClass} mt-4`}>{notice}</p>}
            {formError !== '' && !showForm && (
                <p className={`${alertErrorClass} mt-4`}>{formError}</p>
            )}

            {showForm && (
                <div className={`${cardClass} mt-4`}>
                    <h2 className="font-semibold text-slate-900">
                        {editing ? 'Editar cajero' : 'Agregar cajero'}
                    </h2>
                    {formError !== '' && <p className={`${alertErrorClass} mt-3`}>{formError}</p>}
                    <form onSubmit={handleSubmit} className="mt-4 space-y-4">
                        <div>
                            <label htmlFor="cashier-name" className={labelClass}>
                                Nombre
                            </label>
                            <input
                                id="cashier-name"
                                className={fieldClass}
                                value={form.name}
                                onChange={(event) => update('name', event.target.value)}
                            />
                            {errors.name && <p className={fieldErrorClass}>{errors.name[0]}</p>}
                        </div>
                        <div>
                            <label htmlFor="cashier-whatsapp" className={labelClass}>
                                WhatsApp
                            </label>
                            <input
                                id="cashier-whatsapp"
                                className={fieldClass}
                                inputMode="tel"
                                placeholder="+51 999 888 777"
                                value={form.whatsapp}
                                onChange={(event) => update('whatsapp', event.target.value)}
                            />
                            {errors.whatsapp && (
                                <p className={fieldErrorClass}>{errors.whatsapp[0]}</p>
                            )}
                        </div>
                        <div className="flex gap-2">
                            <button
                                type="submit"
                                disabled={sending}
                                className={primaryButtonClass}
                            >
                                {sending ? 'Guardando...' : 'Guardar'}
                            </button>
                            <button
                                type="button"
                                onClick={() => setShowForm(false)}
                                className={secondaryButtonClass}
                            >
                                Cancelar
                            </button>
                        </div>
                    </form>
                </div>
            )}

            <div className={`${cardClass} mt-4 p-0`}>
                {loading ? (
                    <p className="p-6 text-slate-600">Cargando...</p>
                ) : cashiers.length === 0 ? (
                    <p className="p-6 text-slate-600">
                        Aún no hay cajeros. Agrega el primero con el botón de arriba.
                    </p>
                ) : (
                    <ul className="divide-y divide-slate-200">
                        {cashiers.map((cashier) => (
                            <li
                                key={cashier.id}
                                className="flex flex-wrap items-center gap-x-4 gap-y-2 px-6 py-4"
                            >
                                <div className="min-w-0 flex-1">
                                    <p className="truncate font-semibold text-slate-900">
                                        {cashier.name}
                                    </p>
                                    <p className="text-sm text-slate-600">
                                        {cashier.whatsapp}
                                        {!cashier.is_active && ' · Desactivado'}
                                    </p>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        onClick={() => openEdit(cashier)}
                                        className={secondaryButtonClass}
                                    >
                                        Editar
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => toggleActive(cashier)}
                                        className={secondaryButtonClass}
                                    >
                                        {cashier.is_active ? 'Desactivar' : 'Activar'}
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => remove(cashier)}
                                        className={dangerButtonClass}
                                    >
                                        Eliminar
                                    </button>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            {lastPage > 1 && (
                <div className="mt-4 flex items-center justify-between">
                    <button
                        type="button"
                        disabled={page <= 1}
                        onClick={() => load(page - 1)}
                        className={secondaryButtonClass}
                    >
                        Anterior
                    </button>
                    <p className="text-sm text-slate-600">
                        Página {page} de {lastPage}
                    </p>
                    <button
                        type="button"
                        disabled={page >= lastPage}
                        onClick={() => load(page + 1)}
                        className={secondaryButtonClass}
                    >
                        Siguiente
                    </button>
                </div>
            )}
        </div>
    );
}
