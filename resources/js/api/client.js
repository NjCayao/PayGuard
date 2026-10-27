import axios from 'axios';

export const api = axios.create({
    baseURL: '/api/panel',
    headers: { Accept: 'application/json' },
    withCredentials: true,
});

export function csrf() {
    return axios.get('/sanctum/csrf-cookie', { withCredentials: true });
}

export function fieldErrors(error) {
    return error?.response?.data?.errors ?? {};
}

export function errorMessage(error, fallback) {
    return error?.response?.data?.message ?? fallback;
}
