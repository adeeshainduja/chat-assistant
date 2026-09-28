(() => {
    const shell = document.querySelector('.chat-shell');
    const form = document.getElementById('chat-form');
    const input = document.getElementById('chat-input');
    const messages = document.getElementById('messages');
    const authStatus = document.getElementById('auth-status');

    if (!shell || !form || !input || !messages || !authStatus) return;

    const apiUrl = shell.dataset.apiUrl;
    const enabled = shell.dataset.enabled === '1';

    let authToken = null;
    let sending = false;
    const history = [];

    const addMessage = (role, text, extraClass = '') => {
        const wrap = document.createElement('div');
        wrap.className = `message ${role} ${extraClass}`.trim();

        const bubble = document.createElement('div');
        bubble.className = 'bubble';
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

    window.parent.postMessage(
        { type: 'GETMORE_AI_READY' },
        window.location.origin
    );

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (!enabled || sending) return;

        const message = input.value.trim();

        if (!message) return;

        if (!authToken) {
            setAuthState('Waiting for GETMORE login authentication…', 'error');
            window.parent.postMessage(
                { type: 'GETMORE_AI_REFRESH_AUTH' },
                window.location.origin
            );
            return;
        }

        sending = true;
        input.disabled = true;

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
            addMessage('assistant', 'The AI service is temporarily unavailable. Please try again.');
        } finally {
            sending = false;
            input.disabled = false;
            input.focus();
        }
    });
})();
