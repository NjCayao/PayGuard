import { useEffect, useRef } from 'react';

export function turnstileSiteKey() {
    return document.querySelector('meta[name="turnstile-site-key"]')?.content ?? '';
}

export default function Turnstile({ onToken }) {
    const container = useRef(null);
    const callback = useRef(onToken);
    callback.current = onToken;

    useEffect(() => {
        let widgetId;
        let cancelled = false;

        function render() {
            if (cancelled || !window.turnstile || !container.current || widgetId !== undefined) {
                return;
            }
            widgetId = window.turnstile.render(container.current, {
                sitekey: turnstileSiteKey(),
                callback: (token) => callback.current(token),
                'expired-callback': () => callback.current(''),
                'error-callback': () => callback.current(''),
            });
        }

        if (window.turnstile) {
            render();
        } else if (!document.querySelector('script[data-turnstile]')) {
            const script = document.createElement('script');
            script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
            script.async = true;
            script.defer = true;
            script.dataset.turnstile = '1';
            script.onload = render;
            document.head.appendChild(script);
        } else {
            const timer = setInterval(() => {
                if (window.turnstile) {
                    clearInterval(timer);
                    render();
                }
            }, 200);
            return () => {
                clearInterval(timer);
                cancelled = true;
            };
        }

        return () => {
            cancelled = true;
            if (widgetId !== undefined && window.turnstile) {
                window.turnstile.remove(widgetId);
            }
        };
    }, []);

    return <div ref={container} />;
}
