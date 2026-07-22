import { loadConnectAndInitialize } from '@stripe/connect-js';

const root = document.getElementById('stripe-onboarding');

if (root) {
    const publishableKey = root.dataset.pk;
    const sessionUrl = root.dataset.sessionUrl;
    const returnUrl = root.dataset.returnUrl;
    const csrf = root.dataset.csrf;

    const mount = document.getElementById('onboarding-mount');
    const loading = document.getElementById('onboarding-loading');

    const showError = (message) => {
        if (loading) loading.style.display = 'none';
        if (mount) {
            mount.innerHTML =
                '<div style="border:1px solid #fecaca;background:#fef2f2;color:#991b1b;border-radius:1rem;padding:1rem;font-weight:600;">' +
                'Impossible de charger l’activation du portefeuille. ' +
                (message ? '(' + message + ') ' : '') +
                'Réessayez dans un instant.</div>';
        }
    };

    const fetchClientSecret = async () => {
        const res = await fetch(sessionUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
            },
        });

        if (!res.ok) {
            const data = await res.json().catch(() => ({}));
            throw new Error(data.error || 'Erreur serveur');
        }

        const { client_secret: clientSecret } = await res.json();
        return clientSecret;
    };

    try {
        const instance = loadConnectAndInitialize({
            publishableKey,
            fetchClientSecret,
            appearance: {
                overlays: 'dialog',
                variables: {
                    colorPrimary: '#0d9488',
                    borderRadius: '14px',
                    fontFamily: 'Instrument Sans, system-ui, sans-serif',
                },
            },
        });

        const onboarding = instance.create('account-onboarding');

        onboarding.setOnExit(() => {
            window.location.href = returnUrl;
        });

        if (loading) loading.style.display = 'none';
        mount.appendChild(onboarding);
    } catch (err) {
        showError(err && err.message ? err.message : '');
    }
}
