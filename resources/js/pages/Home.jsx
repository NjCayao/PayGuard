import { useAuth } from '../auth/AuthContext';
import { cardClass } from '../components/ui';

export default function Home() {
    const { user } = useAuth();

    return (
        <div>
            <h1 className="text-xl font-bold text-slate-900">Inicio</h1>
            <div className={`${cardClass} mt-4`}>
                <p className="text-lg font-semibold text-slate-900">
                    Hola, {user?.name}
                </p>
                <p className="mt-1 text-slate-600">{user?.business?.name}</p>
            </div>
        </div>
    );
}
