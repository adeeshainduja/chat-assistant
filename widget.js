(() => {
    const script = document.currentScript;

    if (!script) return;

    const scriptUrl = new URL(script.src, window.location.href);
    const origin = scriptUrl.origin;
    const basePath = scriptUrl.pathname.replace(/\/widget\.js$/, '');
    const baseUrl = `${origin}${basePath}`;

    const assistantKey = script.dataset.assistant || script.dataset.assistantKey || '';
    const chatUrl = script.dataset.chatUrl || `${baseUrl}/chat.php?assistant=${encodeURIComponent(assistantKey)}`;
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

    // Create Pre-Chat Message Bubble
    const bubble = document.createElement('div');
    bubble.className = 'getmore-ai-prechat-bubble';
    bubble.setAttribute('role', 'dialog');
    bubble.setAttribute('aria-label', 'Assistant message');

    Object.assign(bubble.style, {
        position: 'fixed',
        right: '22px',
        bottom: '88px',
        width: 'auto',
        maxWidth: '320px',
        background: '#ffffff',
        color: '#111827',
        borderRadius: '16px',
        boxShadow: '0 10px 25px rgba(0, 0, 0, 0.12), 0 2px 6px rgba(0, 0, 0, 0.04)',
        border: '1px solid rgba(0, 0, 0, 0.08)',
        padding: '12px 14px 12px 16px',
        fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
        fontSize: '13.5px',
        lineHeight: '1.45',
        zIndex: '2147482998',
        display: 'none',
        boxSizing: 'border-box',
        cursor: 'pointer',
        wordBreak: 'break-word',
        opacity: '0',
        transform: 'translateY(6px)',
        transition: 'opacity 0.2s ease, transform 0.2s ease',
    });

    const bubbleBody = document.createElement('div');
    Object.assign(bubbleBody.style, {
        display: 'flex',
        alignItems: 'flex-start',
        gap: '10px',
    });

    const bubbleText = document.createElement('div');
    Object.assign(bubbleText.style, {
        flex: '1',
        whiteSpace: 'pre-line',
        userSelect: 'none',
        color: '#1f2937',
    });

    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'getmore-ai-prechat-close';
    closeBtn.setAttribute('aria-label', 'Close pre-chat message');
    closeBtn.innerHTML = '&times;';
    Object.assign(closeBtn.style, {
        border: '0',
        background: 'transparent',
        color: '#9ca3af',
        width: '20px',
        height: '20px',
        fontSize: '18px',
        lineHeight: '1',
        display: 'inline-flex',
        alignItems: 'center',
        justifyContent: 'center',
        borderRadius: '50%',
        cursor: 'pointer',
        flexShrink: '0',
        padding: '0',
        margin: '-2px -4px 0 0',
        transition: 'color 0.15s ease, background-color 0.15s ease',
    });

    closeBtn.addEventListener('mouseenter', () => {
        closeBtn.style.color = '#374151';
        closeBtn.style.backgroundColor = '#f3f4f6';
    });
    closeBtn.addEventListener('mouseleave', () => {
        closeBtn.style.color = '#9ca3af';
        closeBtn.style.backgroundColor = 'transparent';
    });

    const pointer = document.createElement('div');
    Object.assign(pointer.style, {
        position: 'absolute',
        bottom: '-6px',
        right: '22px',
        width: '0',
        height: '0',
        borderLeft: '6px solid transparent',
        borderRight: '6px solid transparent',
        borderTop: '6px solid #ffffff',
        filter: 'drop-shadow(0 2px 2px rgba(0,0,0,0.06))',
    });

    bubbleBody.appendChild(bubbleText);
    bubbleBody.appendChild(closeBtn);
    bubble.appendChild(bubbleBody);
    bubble.appendChild(pointer);

    document.body.appendChild(iframe);
    document.body.appendChild(bubble);
    document.body.appendChild(button);

    const storageKey = `getmore_ai_prechat_${assistantKey || 'default'}`;
    let preChatTimer = null;
    let preChatConfig = null;

    const shouldShowPreChat = (config) => {
        if (!config || !config.enabled || !config.message || !config.message.trim()) {
            return false;
        }

        const mode = config.displayMode || 'always';

        if (mode === 'once_session') {
            try {
                if (sessionStorage.getItem(storageKey) === '1') {
                    return false;
                }
            } catch (e) {}
        } else if (mode === 'once_visitor') {
            try {
                if (localStorage.getItem(storageKey) === '1') {
                    return false;
                }
            } catch (e) {}
        }

        return true;
    };

    const recordPreChatShown = (config) => {
        const mode = (config && config.displayMode) || 'always';
        if (mode === 'once_session') {
            try {
                sessionStorage.setItem(storageKey, '1');
            } catch (e) {}
        } else if (mode === 'once_visitor') {
            try {
                localStorage.setItem(storageKey, '1');
            } catch (e) {}
        }
    };

    const showBubble = () => {
        if (iframe.style.display !== 'none') {
            return;
        }
        bubble.style.display = 'block';
        requestAnimationFrame(() => {
            bubble.style.opacity = '1';
            bubble.style.transform = 'translateY(0)';
        });
        recordPreChatShown(preChatConfig);
    };

    const hideBubble = () => {
        bubble.style.opacity = '0';
        bubble.style.transform = 'translateY(6px)';
        setTimeout(() => {
            bubble.style.display = 'none';
        }, 200);
    };

    closeBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        if (preChatTimer) {
            clearTimeout(preChatTimer);
            preChatTimer = null;
        }
        hideBubble();
        recordPreChatShown(preChatConfig);
    });

    bubble.addEventListener('click', (e) => {
        if (e.target.closest('.getmore-ai-prechat-close')) {
            return;
        }
        hideBubble();
        iframe.style.display = 'block';
    });

    window.addEventListener('message', (event) => {
        const data = event.data || {};

        if (data.type === 'GETMORE_AI_READY') {
            if (data.primaryColor) {
                button.style.background = data.primaryColor;
            }
            if (data.preChat) {
                preChatConfig = data.preChat;
                bubbleText.textContent = preChatConfig.message || '';

                if (shouldShowPreChat(preChatConfig)) {
                    const delaySec = Math.max(0, parseInt(preChatConfig.delay, 10) || 0);
                    if (delaySec === 0) {
                        showBubble();
                    } else {
                        preChatTimer = setTimeout(() => {
                            showBubble();
                        }, delaySec * 1000);
                    }
                }
            }
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
        if (opening) {
            if (preChatTimer) {
                clearTimeout(preChatTimer);
                preChatTimer = null;
            }
            hideBubble();
        }
    });

    const applyResponsive = () => {
        if (window.innerWidth <= 480) {
            button.style.right = '14px';
            button.style.bottom = '14px';
            button.style.width = '50px';
            button.style.height = '50px';
            iframe.style.right = '8px';
            iframe.style.bottom = '70px';
            iframe.style.width = 'calc(100vw - 16px)';
            iframe.style.maxWidth = 'calc(100vw - 16px)';
            iframe.style.height = 'calc(100vh - 84px)';
            iframe.style.maxHeight = 'calc(100vh - 84px)';
            iframe.style.borderRadius = '14px';
            bubble.style.right = '14px';
            bubble.style.bottom = '72px';
            bubble.style.maxWidth = 'calc(100vw - 28px)';
            pointer.style.right = '20px';
        } else if (window.innerWidth <= 768) {
            button.style.right = '18px';
            button.style.bottom = '18px';
            button.style.width = '54px';
            button.style.height = '54px';
            iframe.style.right = '14px';
            iframe.style.bottom = '80px';
            iframe.style.width = 'min(380px, calc(100vw - 28px))';
            iframe.style.maxWidth = 'calc(100vw - 28px)';
            iframe.style.height = 'min(600px, calc(100vh - 96px))';
            iframe.style.maxHeight = 'calc(100vh - 96px)';
            iframe.style.borderRadius = '16px';
            bubble.style.right = '18px';
            bubble.style.bottom = '80px';
            bubble.style.maxWidth = 'min(310px, calc(100vw - 36px))';
            pointer.style.right = '22px';
        } else {
            button.style.right = '22px';
            button.style.bottom = '22px';
            button.style.width = '56px';
            button.style.height = '56px';
            iframe.style.right = '22px';
            iframe.style.bottom = '88px';
            iframe.style.width = '390px';
            iframe.style.maxWidth = 'calc(100vw - 28px)';
            iframe.style.height = '620px';
            iframe.style.maxHeight = 'calc(100vh - 110px)';
            iframe.style.borderRadius = '16px';
            bubble.style.right = '22px';
            bubble.style.bottom = '88px';
            bubble.style.maxWidth = '320px';
            pointer.style.right = '22px';
        }
    };

    window.addEventListener('resize', applyResponsive);
    applyResponsive();
})();
