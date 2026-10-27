import { Navigate } from 'react-router-dom';
import { useAuth } from '../auth/AuthContext';

export default function GuestRoute({ children }) {
    const { user, loading } = useAuth();

    if (loading) {
        return <p className="p-8 text-slate-600">Cargando...</p>;
    }

    if (user) {
        return <Navigate to="/app" replace />;
    }

    return children;
}
