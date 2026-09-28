(() => {
    const shell = document.querySelector('.chat-shell');
    const form = document.getElementById('chat-form');
    const input = document.getElementById('chat-input');
    const sendBtn = document.getElementById('chat-send-btn');
    const messages = document.getElementById('messages');
    const authStatus = document.getElementById('auth-status');
    const backBtn = document.getElementById('header-back-btn');
    const starterContainer = document.getElementById('starter-buttons');

    // Theme & Menu elements
    const menuBtn = document.getElementById('header-menu-btn');
    const headerDropdown = document.getElementById('header-dropdown');
    const menuAppearanceBtn = document.getElementById('menu-appearance-btn');
    const menuResetBtn = document.getElementById('menu-reset-btn');
    const themePanel = document.getElementById('theme-panel');
    const themeBackdrop = document.getElementById('theme-panel-backdrop');
    const themeCloseBtn = document.getElementById('theme-panel-close-btn');
    const themeResetBtn = document.getElementById('theme-reset-btn');
    const customPrimaryInput = document.getElementById('theme-custom-primary');
    const customPrimarySwatch = document.getElementById('custom-primary-preview');
    const userBubbleInput = document.getElementById('theme-user-bubble');
    const userBubbleSwatch = document.getElementById('user-bubble-preview');

    if (!shell || !form || !input || !messages || !authStatus) return;

    const apiUrl = shell.dataset.apiUrl;
    const enabled = shell.dataset.enabled === '1';

    // Preserve Admin-defined defaults
    const adminTheme = {
        primaryColor: shell.dataset.adminPrimary || '#00B957',
        headerTextColor: shell.dataset.adminHeaderText || '#FFFFFF',
        backgroundColor: shell.dataset.adminBg || '#FFFFFF',
        userBubbleColor: shell.dataset.adminUserBubble || '#ECFDF3',
        assistantBubbleColor: shell.dataset.adminAssistantBubble || '#EAEAEA',
        textColor: shell.dataset.adminText || '#111827',
    };

    const THEME_STORAGE_KEY = 'getmore_ai_student_theme';
    let currentTheme = { ...adminTheme };

    // Luminance and contrast helper
    function getContrastTextColor(hexColor) {
        if (!hexColor) return '#FFFFFF';
        let hex = hexColor.replace('#', '').trim();
        if (hex.length === 3) {
            hex = hex.split('').map(c => c + c).join('');
        }
        if (hex.length !== 6) return '#FFFFFF';

        const r = parseInt(hex.substring(0, 2), 16) / 255;
        const g = parseInt(hex.substring(2, 4), 16) / 255;
        const b = parseInt(hex.substring(4, 6), 16) / 255;

        const toLinear = (c) => c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
        const luminance = 0.2126 * toLinear(r) + 0.7152 * toLinear(g) + 0.0722 * toLinear(b);

        return luminance > 0.45 ? '#111827' : '#FFFFFF';
    }

    // Soft bubble tint derived from primary color
    function deriveUserBubbleColor(hex) {
        let c = (hex || '#00B957').replace('#', '').trim();
        if (c.length === 3) c = c.split('').map(x => x + x).join('');
        if (c.length !== 6) return '#ECFDF3';
        const r = parseInt(c.substring(0, 2), 16);
        const g = parseInt(c.substring(2, 4), 16);
        const b = parseInt(c.substring(4, 6), 16);
        const nr = Math.round(r * 0.12 + 255 * 0.88);
        const ng = Math.round(g * 0.12 + 255 * 0.88);
        const nb = Math.round(b * 0.12 + 255 * 0.88);
        return '#' + [nr, ng, nb].map(x => x.toString(16).padStart(2, '0')).join('');
    }

    function updatePanelControls(primaryColor, backgroundColor, userBubbleColor) {
        // Primary color preset buttons
        document.querySelectorAll('.color-preset-btn').forEach(btn => {
            const c = (btn.dataset.color || '').toLowerCase();
            if (c === (primaryColor || '').toLowerCase()) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });

        // Custom primary picker & swatch
        if (customPrimaryInput) {
            customPrimaryInput.value = primaryColor;
        }
        if (customPrimarySwatch) {
            customPrimarySwatch.style.backgroundColor = primaryColor;
        }

        // Background preset buttons
        document.querySelectorAll('.bg-preset-btn').forEach(btn => {
            const bg = (btn.dataset.bg || '').toLowerCase();
            if (bg === (backgroundColor || '').toLowerCase()) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });

        // User bubble picker & swatch
        if (userBubbleInput) {
            userBubbleInput.value = userBubbleColor;
        }
        if (userBubbleSwatch) {
            userBubbleSwatch.style.backgroundColor = userBubbleColor;
        }
    }

    function applyTheme(theme, save = false) {
        currentTheme = {
            ...currentTheme,
            ...theme,
        };

        const primaryColor = currentTheme.primaryColor || adminTheme.primaryColor;
        const backgroundColor = currentTheme.backgroundColor || adminTheme.backgroundColor;
        const userBubbleColor = currentTheme.userBubbleColor || adminTheme.userBubbleColor;

        const headerTextColor = getContrastTextColor(primaryColor);
        const userBubbleTextColor = getContrastTextColor(userBubbleColor);
        const isDarkBg = backgroundColor.toLowerCase() === '#111827';

        document.documentElement.style.setProperty('--primary-color', primaryColor);
        document.documentElement.style.setProperty('--header-text-color', headerTextColor);
        document.documentElement.style.setProperty('--chat-bg-color', backgroundColor);
        document.documentElement.style.setProperty('--user-bubble-color', userBubbleColor);
        document.documentElement.style.setProperty('--user-bubble-text-color', userBubbleTextColor);

        if (isDarkBg) {
            shell.setAttribute('data-dark-mode', 'true');
            document.documentElement.style.setProperty('--text-color', '#F9FAFB');
            document.documentElement.style.setProperty('--assistant-bubble-color', '#1F2937');
        } else {
            shell.removeAttribute('data-dark-mode');
            document.documentElement.style.setProperty('--text-color', adminTheme.textColor || '#111827');
            document.documentElement.style.setProperty('--assistant-bubble-color', adminTheme.assistantBubbleColor || '#EAEAEA');
        }

        updatePanelControls(primaryColor, backgroundColor, userBubbleColor);

        // Notify parent iframe container (e.g. widget button)
        window.parent.postMessage(
            { type: 'GETMORE_AI_THEME', primaryColor },
            window.location.origin
        );

        if (save) {
            try {
                localStorage.setItem(THEME_STORAGE_KEY, JSON.stringify({
                    primaryColor,
                    backgroundColor,
                    userBubbleColor,
                }));
            } catch (e) {}
        }
    }

    function resetTheme() {
        try {
            localStorage.removeItem(THEME_STORAGE_KEY);
        } catch (e) {}

        applyTheme({
            primaryColor: adminTheme.primaryColor,
            backgroundColor: adminTheme.backgroundColor,
            userBubbleColor: adminTheme.userBubbleColor,
        }, false);
    }

    function initTheme() {
        try {
            const saved = localStorage.getItem(THEME_STORAGE_KEY);
            if (saved) {
                const parsed = JSON.parse(saved);
                if (parsed && typeof parsed === 'object') {
                    applyTheme({
                        primaryColor: parsed.primaryColor || adminTheme.primaryColor,
                        backgroundColor: parsed.backgroundColor || adminTheme.backgroundColor,
                        userBubbleColor: parsed.userBubbleColor || adminTheme.userBubbleColor,
                    }, false);
                    return;
                }
            }
        } catch (e) {}

        applyTheme(adminTheme, false);
    }

    // Initialize Theme on startup
    initTheme();

    // Dropdown and Appearance Panel controls
    function closeDropdown() {
        if (headerDropdown) {
            headerDropdown.hidden = true;
        }
        if (menuBtn) {
            menuBtn.setAttribute('aria-expanded', 'false');
        }
    }

    function openDropdown() {
        if (headerDropdown) {
            headerDropdown.hidden = false;
        }
        if (menuBtn) {
            menuBtn.setAttribute('aria-expanded', 'true');
        }
    }

    function openThemePanel() {
        closeDropdown();
        if (themePanel) themePanel.hidden = false;
        if (themeBackdrop) themeBackdrop.hidden = false;
    }

    function closeThemePanel() {
        if (themePanel) themePanel.hidden = true;
        if (themeBackdrop) themeBackdrop.hidden = true;
    }

    if (menuBtn && headerDropdown) {
        menuBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            if (headerDropdown.hidden) {
                openDropdown();
            } else {
                closeDropdown();
            }
        });
    }

    if (menuAppearanceBtn) {
        menuAppearanceBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            openThemePanel();
        });
    }

    if (menuResetBtn) {
        menuResetBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            closeDropdown();
            resetTheme();
        });
    }

    if (themeCloseBtn) {
        themeCloseBtn.addEventListener('click', () => {
            closeThemePanel();
        });
    }

    if (themeBackdrop) {
        themeBackdrop.addEventListener('click', () => {
            closeThemePanel();
        });
    }

    if (themeResetBtn) {
        themeResetBtn.addEventListener('click', () => {
            resetTheme();
        });
    }

    // Color preset buttons click
    document.querySelectorAll('.color-preset-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const color = btn.dataset.color;
            if (!color) return;
            const derivedBubble = deriveUserBubbleColor(color);
            applyTheme({
                primaryColor: color,
                userBubbleColor: derivedBubble,
            }, true);
        });
    });

    // Custom primary color picker
    if (customPrimaryInput) {
        customPrimaryInput.addEventListener('input', (e) => {
            const color = e.target.value;
            if (!color) return;
            const derivedBubble = deriveUserBubbleColor(color);
            applyTheme({
                primaryColor: color,
                userBubbleColor: derivedBubble,
            }, true);
        });
    }

    // Background preset buttons click
    document.querySelectorAll('.bg-preset-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const bg = btn.dataset.bg;
            if (!bg) return;
            applyTheme({
                backgroundColor: bg,
            }, true);
        });
    });

    // User bubble custom color picker
    if (userBubbleInput) {
        userBubbleInput.addEventListener('input', (e) => {
            const color = e.target.value;
            if (!color) return;
            applyTheme({
                userBubbleColor: color,
            }, true);
        });
    }

    // Global click listener to close dropdown when clicking outside
    document.addEventListener('click', (e) => {
        if (headerDropdown && !headerDropdown.hidden) {
            if (!headerDropdown.contains(e.target) && !menuBtn.contains(e.target)) {
                closeDropdown();
            }
        }
    });

    // Global keyboard listener (Escape key)
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeDropdown();
            closeThemePanel();
        }
    });

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

    // Notify parent window that chat is ready with active primary color
    window.parent.postMessage(
        { type: 'GETMORE_AI_READY', primaryColor: currentTheme.primaryColor },
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
