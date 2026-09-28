(() => {
    const shell = document.querySelector('.chat-shell');
    const form = document.getElementById('chat-form');
    const input = document.getElementById('chat-input');
    const sendBtn = document.getElementById('chat-send-btn');
    const messages = document.getElementById('messages');
    const authStatus = document.getElementById('auth-status');
    const backBtn = document.getElementById('header-back-btn');
    const starterContainer = document.getElementById('starter-buttons');

    if (!shell || !form || !input || !messages || !authStatus) return;

    const apiUrl = shell.dataset.apiUrl;
    const enabled = shell.dataset.enabled === '1';
    const primaryColor = shell.dataset.primaryColor || '#00B957';

    let authToken = null;
    let sending = false;
    const history = [];

    const addMessage = (role, text, extraClass = '') => {
        const wrap = document.createElement('div');
        wrap.className = `message ${role} ${extraClass}`.trim();

        const bubble = document.createElement('div');
        bubble.className = `bubble ${role}-bubble`;
        bubble.textContent = text;

        wrap.appendChild(bubble);
        messages.appendChild(wrap);
        messages.scrollTop = messages.scrollHeight;

        return wrap;
    };

    const setAuthState = (text, state = '') => {
        authStatus.textContent = text;
        authStatus.className = `auth-status ${state}`.trim();
    };

    window.addEventListener('message', (event) => {
        if (event.origin !== window.location.origin) return;

        const data = event.data || {};

        if (data.type === 'GETMORE_AI_AUTH' && typeof data.token === 'string') {
            authToken = data.token;
            setAuthState('Securely connected to your GETMORE student account.', 'ready');
            if (enabled) input.focus();
        }
    });

    // Notify parent window that chat is ready
    window.parent.postMessage(
        { type: 'GETMORE_AI_READY', primaryColor },
        window.location.origin
    );

    // Header back button closes the widget if embedded in iframe
    if (backBtn) {
        backBtn.addEventListener('click', () => {
            window.parent.postMessage(
                { type: 'GETMORE_AI_CLOSE' },
                window.location.origin
            );
        });
    }

    // Centralized message sending function
    async function sendMessage(message) {
        message = (message || '').trim();
        if (!enabled || sending || !message) return;

        if (!authToken) {
            setAuthState('Waiting for GETMORE login authentication…', 'error');
            window.parent.postMessage(
                { type: 'GETMORE_AI_REFRESH_AUTH' },
                window.location.origin
            );
            return;
        }

        // Hide starter buttons after first user message
        if (starterContainer) {
            starterContainer.style.display = 'none';
        }

        sending = true;
        input.disabled = true;
        if (sendBtn) sendBtn.disabled = true;

        addMessage('user', message);
        input.value = '';

        const typing = addMessage('assistant', 'Thinking…', 'typing');

        try {
            const response = await fetch(apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${authToken}`,
                },
                body: JSON.stringify({
                    message,
                    history: history.slice(-10),
                }),
            });

            const data = await response.json().catch(() => ({}));

            typing.remove();

            if (response.status === 401) {
                setAuthState('Your secure AI session expired. Refreshing…', 'error');

                window.parent.postMessage(
                    { type: 'GETMORE_AI_REFRESH_AUTH' },
                    window.location.origin
                );

                addMessage('assistant', 'Your secure session expired. Please send the message again in a moment.');
                return;
            }

            if (!response.ok) {
                addMessage('assistant', data.error || 'I could not process that request.');
                return;
            }

            const reply = data.reply || 'I could not produce a response.';
            addMessage('assistant', reply);

            history.push({ role: 'user', content: message });
            history.push({ role: 'assistant', content: reply });

            if (history.length > 20) {
                history.splice(0, history.length - 20);
            }
        } catch (error) {
            typing.remove();
            addMessage('assistant', 'The assistant is temporarily busy. Please try again in a moment.');
        } finally {
            sending = false;
            input.disabled = false;
            if (sendBtn) sendBtn.disabled = false;
            input.focus();
        }
    }

    // Form submit sends typed message
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        sendMessage(input.value);
    });

    // Suggested / starter question buttons
    document.querySelectorAll('.starter-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const msg = btn.dataset.message || btn.textContent.trim();
            if (msg) {
                input.value = msg;
                sendMessage(msg);
            }
        });
    });
})();
