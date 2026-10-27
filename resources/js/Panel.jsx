import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import { AuthProvider } from './auth/AuthContext';
import ProtectedRoute from './components/ProtectedRoute';
import GuestRoute from './components/GuestRoute';
import Layout from './components/Layout';
import Login from './pages/Login';
import Register from './pages/Register';
import Home from './pages/Home';
import Cashiers from './pages/Cashiers';
import ComingSoon from './pages/ComingSoon';

export default function Panel() {
    return (
        <AuthProvider>
            <BrowserRouter>
                <Routes>
                    <Route
                        path="/app/iniciar-sesion"
                        element={
                            <GuestRoute>
                                <Login />
                            </GuestRoute>
                        }
                    />
                    <Route
                        path="/app/crear-cuenta"
                        element={
                            <GuestRoute>
                                <Register />
                            </GuestRoute>
                        }
                    />
                    <Route
                        path="/app"
                        element={
                            <ProtectedRoute>
                                <Layout />
                            </ProtectedRoute>
                        }
                    >
                        <Route index element={<Home />} />
                        <Route path="cajeros" element={<Cashiers />} />
                        <Route path="pagos" element={<ComingSoon title="Pagos recibidos" />} />
                        <Route
                            path="verificaciones"
                            element={<ComingSoon title="Verificaciones" />}
                        />
                        <Route path="dispositivo" element={<ComingSoon title="Dispositivo" />} />
                        <Route
                            path="configuracion"
                            element={<ComingSoon title="Configuración" />}
                        />
                    </Route>
                    <Route path="*" element={<Navigate to="/app" replace />} />
                </Routes>
            </BrowserRouter>
        </AuthProvider>
    );
}
