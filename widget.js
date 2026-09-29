(() => {
    const script = document.currentScript;

    if (!script) return;

    const scriptUrl = new URL(script.src, window.location.href);
    const basePath = scriptUrl.pathname.replace(/\/widget\.js$/, '');

    const assistantKey = script.dataset.assistant || script.dataset.assistantKey || '';
    const chatUrl = script.dataset.chatUrl || `${basePath}/chat.php?assistant=${encodeURIComponent(assistantKey)}`;
    const label = script.dataset.label;

    const button = document.createElement('button');
    button.type = 'button';
    button.setAttribute('aria-label', 'Open AI Assistant');

    if (label) {
        button.textContent = label;
        button.style.fontWeight = '700';
    } else {
        button.innerHTML = '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>';
    }

    Object.assign(button.style, {
        position: 'fixed',
        right: '22px',
        bottom: '22px',
        width: '56px',
        height: '56px',
        borderRadius: '50%',
        border: '0',
        background: '#00B957',
        color: '#fff',
        display: 'grid',
        placeItems: 'center',
        cursor: 'pointer',
        zIndex: '2147483000',
        boxShadow: '0 10px 25px rgba(0,0,0,.2)',
        transition: 'transform 0.15s ease, background 0.2s ease',
    });

    button.addEventListener('mouseenter', () => {
        button.style.transform = 'scale(1.06)';
    });
    button.addEventListener('mouseleave', () => {
        button.style.transform = 'scale(1)';
    });

    const iframe = document.createElement('iframe');
    iframe.src = chatUrl;
    iframe.title = 'AI Assistant';
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
        borderRadius: '16px',
        boxShadow: '0 12px 35px rgba(0,0,0,0.18)',
        background: 'transparent',
        zIndex: '2147482999',
        display: 'none',
    });

    document.body.appendChild(iframe);
    document.body.appendChild(button);

    window.addEventListener('message', (event) => {
        const data = event.data || {};

        if (data.type === 'GETMORE_AI_READY' && data.primaryColor) {
            button.style.background = data.primaryColor;
        }

        if (data.type === 'GETMORE_AI_THEME' && data.primaryColor) {
            button.style.background = data.primaryColor;
        }

        if (data.type === 'GETMORE_AI_CLOSE') {
            iframe.style.display = 'none';
        }
    });

    button.addEventListener('click', () => {
        const opening = iframe.style.display === 'none';
        iframe.style.display = opening ? 'block' : 'none';
    });

    const applyResponsive = () => {
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
    };

    window.addEventListener('resize', applyResponsive);
    applyResponsive();
})();
