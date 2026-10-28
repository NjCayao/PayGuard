import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import { AuthProvider } from './auth/AuthContext';
import ProtectedRoute from './components/ProtectedRoute';
import GuestRoute from './components/GuestRoute';
import Layout from './components/Layout';
import Login from './pages/Login';
import Register from './pages/Register';
import Home from './pages/Home';
import Cashiers from './pages/Cashiers';
import ReceivedPayments from './pages/ReceivedPayments';
import Verifications from './pages/Verifications';
import VerificationDetail from './pages/VerificationDetail';
import Devices from './pages/Devices';
import OwnerAlerts from './pages/OwnerAlerts';
import Settings from './pages/Settings';
import Account from './pages/Account';

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
                        <Route path="pagos" element={<ReceivedPayments />} />
                        <Route path="verificaciones" element={<Verifications />} />
                        <Route path="verificaciones/:id" element={<VerificationDetail />} />
                        <Route path="dispositivo" element={<Devices />} />
                        <Route path="alertas" element={<OwnerAlerts />} />
                        <Route path="configuracion" element={<Settings />} />
                        <Route path="mi-cuenta" element={<Account />} />
                    </Route>
                    <Route path="*" element={<Navigate to="/app" replace />} />
                </Routes>
            </BrowserRouter>
        </AuthProvider>
    );
}
