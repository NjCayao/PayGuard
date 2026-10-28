export const DEMO_BLOCKED_MESSAGE = 'Cuenta de demostración: esta acción está desactivada.';

export function isDemo(user) {
    return user?.business?.is_demo === true;
}
