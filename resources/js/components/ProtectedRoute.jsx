import { Navigate, useLocation } from 'react-router-dom';
import { useAuth } from '../auth/AuthContext';

export default function ProtectedRoute({ children }) {
    const { user, loading } = useAuth();
    const location = useLocation();

    if (loading) {
        return <p className="p-8 text-slate-600">Cargando...</p>;
    }

    if (!user) {
        return <Navigate to="/app/iniciar-sesion" replace state={{ from: location }} />;
    }

    return children;
}
