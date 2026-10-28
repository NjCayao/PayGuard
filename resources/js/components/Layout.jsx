import { Link, NavLink, Outlet, useNavigate } from 'react-router-dom';
import { useAuth } from '../auth/AuthContext';

const links = [
    { to: '/app', label: 'Inicio', end: true },
    { to: '/app/pagos', label: 'Pagos recibidos' },
    { to: '/app/verificaciones', label: 'Verificaciones' },
    { to: '/app/cajeros', label: 'Cajeros' },
    { to: '/app/dispositivo', label: 'Dispositivo' },
    { to: '/app/alertas', label: 'Alertas' },
    { to: '/app/configuracion', label: 'Configuración' },
];

function navClass({ isActive }) {
    return isActive
        ? 'block rounded-md bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-900'
        : 'block rounded-md px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100';
}

export default function Layout() {
    const { user, logout } = useAuth();
    const navigate = useNavigate();

    async function handleLogout() {
        await logout();
        navigate('/app/iniciar-sesion');
    }

    return (
        <div className="min-h-screen lg:flex">
            <aside className="border-b border-slate-200 bg-white lg:flex lg:w-64 lg:flex-col lg:border-b-0 lg:border-r">
                <div className="border-b border-slate-200 px-6 py-5">
                    <p className="text-lg font-bold text-slate-900">PayGuard</p>
                    <p className="mt-1 truncate text-sm text-slate-600">
                        {user?.business?.name}
                    </p>
                </div>
                <nav className="flex gap-1 overflow-x-auto px-3 py-4 lg:flex-col">
                    {links.map((link) => (
                        <NavLink
                            key={link.to}
                            to={link.to}
                            end={link.end}
                            className={navClass}
                        >
                            {link.label}
                        </NavLink>
                    ))}
                </nav>
                <div className="mt-auto hidden border-t border-slate-200 px-6 py-4 lg:block">
                    <p className="truncate text-sm font-medium text-slate-900">
                        {user?.name}
                    </p>
                    <button
                        type="button"
                        onClick={handleLogout}
                        className="mt-1 text-sm font-semibold text-emerald-800 hover:underline"
                    >
                        Cerrar sesión
                    </button>
                </div>
            </aside>
            <div className="flex-1">
                <div className="flex items-center justify-between border-b border-slate-200 bg-white px-6 py-3 lg:hidden">
                    <p className="truncate text-sm font-medium text-slate-900">{user?.name}</p>
                    <button
                        type="button"
                        onClick={handleLogout}
                        className="text-sm font-semibold text-emerald-800 hover:underline"
                    >
                        Cerrar sesión
                    </button>
                </div>
                <main className="mx-auto w-full max-w-4xl px-4 py-8 sm:px-6">
                    {user?.business && !user.business.yape_holder_name && (
                        <p className="mb-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                            Configura el titular de tu Yape para verificar el destinatario
                            de cada pago.{' '}
                            <Link
                                to="/app/configuracion"
                                className="font-semibold text-amber-900 underline"
                            >
                                Ir a Configuración
                            </Link>
                        </p>
                    )}
                    <Outlet />
                </main>
            </div>
        </div>
    );
}
