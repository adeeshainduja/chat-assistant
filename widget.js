(() => {
    const script = document.currentScript;

    if (!script) return;

    const scriptUrl = new URL(script.src, window.location.href);
    const basePath = scriptUrl.pathname.replace(/\/widget\.js$/, '');

    const authEndpoint = script.dataset.authEndpoint || '/ai-auth.php';
    const chatUrl = script.dataset.chatUrl || `${basePath}/chat.php`;
    const label = script.dataset.label || 'AI';

    const button = document.createElement('button');
    button.type = 'button';
    button.setAttribute('aria-label', 'Open GETMORE AI assistant');
    button.textContent = label;

    Object.assign(button.style, {
        position: 'fixed',
        right: '22px',
        bottom: '22px',
        width: '54px',
        height: '54px',
        borderRadius: '50%',
        border: '0',
        background: '#111',
        color: '#fff',
        fontWeight: '700',
        cursor: 'pointer',
        zIndex: '2147483000',
        boxShadow: '0 12px 30px rgba(0,0,0,.25)',
    });

    const iframe = document.createElement('iframe');
    iframe.src = chatUrl;
    iframe.title = 'GETMORE AI Assistant';
    iframe.setAttribute('allow', 'clipboard-write');

    Object.assign(iframe.style, {
        position: 'fixed',
        right: '22px',
        bottom: '88px',
        width: '390px',
        maxWidth: 'calc(100vw - 28px)',
        height: '620px',
        maxHeight: 'calc(100vh - 110px)',
        border: '0',
        background: 'transparent',
        zIndex: '2147482999',
        display: 'none',
    });

    document.body.appendChild(iframe);
    document.body.appendChild(button);

    let currentToken = null;
    let refreshTimer = null;

    const sendTokenToFrame = () => {
        if (!currentToken || !iframe.contentWindow) return;

        iframe.contentWindow.postMessage(
            {
                type: 'GETMORE_AI_AUTH',
                token: currentToken,
            },
            window.location.origin
        );
    };

    const refreshAuth = async () => {
        try {
            const response = await fetch(authEndpoint, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                },
            });

            const data = await response.json().catch(() => ({}));

            if (!response.ok || !data.token) {
                currentToken = null;
                return;
            }

            currentToken = data.token;
            sendTokenToFrame();
        } catch (error) {
            currentToken = null;
        }
    };

    window.addEventListener('message', (event) => {
        if (event.origin !== window.location.origin) return;

        const data = event.data || {};

        if (data.type === 'GETMORE_AI_READY') {
            refreshAuth();
        }

        if (data.type === 'GETMORE_AI_REFRESH_AUTH') {
            refreshAuth();
        }
    });

    button.addEventListener('click', async () => {
        const opening = iframe.style.display === 'none';
        iframe.style.display = opening ? 'block' : 'none';

        if (opening) {
            await refreshAuth();

            if (!refreshTimer) {
                refreshTimer = window.setInterval(refreshAuth, 4 * 60 * 1000);
            }
        }
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth < 520) {
            iframe.style.right = '10px';
            iframe.style.bottom = '78px';
            iframe.style.width = 'calc(100vw - 20px)';
            iframe.style.height = 'calc(100vh - 95px)';
        } else {
            iframe.style.right = '22px';
            iframe.style.bottom = '88px';
            iframe.style.width = '390px';
            iframe.style.height = '620px';
        }
    });
})();
