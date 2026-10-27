import { createContext, useContext, useEffect, useState } from 'react';
import { api, csrf } from '../api/client';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
    const [user, setUser] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        api.get('/user')
            .then((response) => setUser(response.data.data))
            .catch(() => setUser(null))
            .finally(() => setLoading(false));
    }, []);

    async function login(credentials) {
        await csrf();
        const response = await api.post('/login', credentials);
        setUser(response.data.data);
    }

    async function register(payload) {
        await csrf();
        const response = await api.post('/register', payload);
        setUser(response.data.data);
    }

    async function logout() {
        await api.post('/logout');
        setUser(null);
    }

    return (
        <AuthContext.Provider value={{ user, loading, login, register, logout }}>
            {children}
        </AuthContext.Provider>
    );
}

export function useAuth() {
    return useContext(AuthContext);
}
