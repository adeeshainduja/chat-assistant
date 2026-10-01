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

    // Language & Submenu elements
    const menuLanguageWrap = document.getElementById('menu-language-wrap');
    const menuLanguageBtn = document.getElementById('menu-language-btn');
    const languageSubmenu = document.getElementById('language-submenu');
    const labelAppearance = document.getElementById('label-appearance');
    const labelLanguage = document.getElementById('label-language');
    const labelReset = document.getElementById('label-reset');

    if (!shell || !form || !input || !messages) return;

    const apiUrl = shell.dataset.apiUrl;
    const assistantKey = shell.dataset.assistantKey || '';
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

    const THEME_STORAGE_KEY = `getmore_ai_theme_${assistantKey || 'default'}`;
    const LANGUAGE_STORAGE_KEY = `getmore_ai_language_${assistantKey || 'default'}`;
    let currentTheme = { ...adminTheme };
    let currentLanguage = 'en';

    const UI_STRINGS = {
        en: {
            placeholder: 'Ask a question…',
            send: 'Send message',
            appearance: 'Appearance',
            language: 'Language',
            resetTheme: 'Reset Theme',
            thinking: 'Thinking…',
            busy: 'The assistant is temporarily busy. Please try again in a moment.',
            error: 'I could not process that request.',
        },
        si: {
            placeholder: 'ඔබගේ ප්‍රශ්නය මෙහි ලියන්න…',
            send: 'පණිවිඩය යවන්න',
            appearance: 'පෙනුම',
            language: 'භාෂාව',
            resetTheme: 'තේමාව යළි සකසන්න',
            thinking: 'සිතමින්…',
            busy: 'සහායකයා මොහොතකට කාර්යබහුලයි. කරුණාකර මොහොතකින් නැවත උත්සාහ කරන්න.',
            error: 'මට එම ඉල්ලීම සැකසීමට නොහැකි විය.',
        },
        ta: {
            placeholder: 'உங்கள் கேள்வியை கேட்கவும்…',
            send: 'செய்தி அனுப்பவும்',
            appearance: 'தோற்றம்',
            language: 'மொழி',
            resetTheme: 'தீமை மீட்டமை',
            thinking: 'சிந்திக்கிறது…',
            busy: 'உதவியாளர் தற்போது பிஸியாக உள்ளார். சிறிது நேரம் கழித்து மீண்டும் முயற்சிக்கவும்.',
            error: 'அந்த கோரிக்கையை செயல்படுத்த முடியவில்லை.',
        },
    };

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
        document.querySelectorAll('.color-preset-btn').forEach(btn => {
            const c = (btn.dataset.color || '').toLowerCase();
            if (c === (primaryColor || '').toLowerCase()) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });

        if (customPrimaryInput) {
            customPrimaryInput.value = primaryColor;
        }
        if (customPrimarySwatch) {
            customPrimarySwatch.style.backgroundColor = primaryColor;
        }

        document.querySelectorAll('.bg-preset-btn').forEach(btn => {
            const bg = (btn.dataset.bg || '').toLowerCase();
            if (bg === (backgroundColor || '').toLowerCase()) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });

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
        try {
            window.parent.postMessage(
                { type: 'GETMORE_AI_THEME', primaryColor },
                '*'
            );
        } catch (e) {}

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

    initTheme();

    // Language handling
    function applyLanguage(lang, save = false) {
        if (!['en', 'si', 'ta'].includes(lang)) {
            lang = 'en';
        }
        currentLanguage = lang;

        const strings = UI_STRINGS[lang] || UI_STRINGS.en;

        if (input) input.placeholder = strings.placeholder;
        if (sendBtn) sendBtn.setAttribute('aria-label', strings.send);
        if (labelAppearance) labelAppearance.textContent = strings.appearance;
        if (labelLanguage) labelLanguage.textContent = strings.language;
        if (labelReset) labelReset.textContent = strings.resetTheme;

        document.querySelectorAll('.language-option-btn').forEach(btn => {
            const isMatch = btn.dataset.lang === lang;
            btn.classList.toggle('active', isMatch);
            btn.setAttribute('aria-checked', isMatch ? 'true' : 'false');
        });

        if (save) {
            try {
                localStorage.setItem(LANGUAGE_STORAGE_KEY, lang);
            } catch (e) {}
        }
    }

    function initLanguage() {
        let savedLang = 'en';
        try {
            const stored = localStorage.getItem(LANGUAGE_STORAGE_KEY);
            if (stored && ['en', 'si', 'ta'].includes(stored)) {
                savedLang = stored;
            }
        } catch (e) {}
        applyLanguage(savedLang, false);
    }

    initLanguage();

    // Dropdown and Appearance Panel controls
    function closeLanguageSubmenu() {
        if (languageSubmenu) languageSubmenu.hidden = true;
        if (menuLanguageWrap) menuLanguageWrap.classList.remove('open');
        if (menuLanguageBtn) menuLanguageBtn.setAttribute('aria-expanded', 'false');
    }

    function openLanguageSubmenu() {
        if (languageSubmenu) languageSubmenu.hidden = false;
        if (menuLanguageWrap) menuLanguageWrap.classList.add('open');
        if (menuLanguageBtn) menuLanguageBtn.setAttribute('aria-expanded', 'true');
    }

    function toggleLanguageSubmenu(e) {
        if (e) e.stopPropagation();
        if (!languageSubmenu) return;
        if (languageSubmenu.hidden) {
            openLanguageSubmenu();
        } else {
            closeLanguageSubmenu();
        }
    }

    function closeDropdown() {
        closeLanguageSubmenu();
        if (headerDropdown) headerDropdown.hidden = true;
        if (menuBtn) menuBtn.setAttribute('aria-expanded', 'false');
    }

    function openDropdown() {
        if (headerDropdown) headerDropdown.hidden = false;
        if (menuBtn) menuBtn.setAttribute('aria-expanded', 'true');
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

    if (menuLanguageBtn) {
        menuLanguageBtn.addEventListener('click', toggleLanguageSubmenu);
    }

    document.querySelectorAll('.language-option-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const chosen = btn.dataset.lang || 'en';
            applyLanguage(chosen, true);
            closeLanguageSubmenu();
            closeDropdown();
        });
    });

    if (menuResetBtn) {
        menuResetBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            closeDropdown();
            resetTheme();
        });
    }

    if (themeCloseBtn) {
        themeCloseBtn.addEventListener('click', closeThemePanel);
    }

    if (themeBackdrop) {
        themeBackdrop.addEventListener('click', closeThemePanel);
    }

    if (themeResetBtn) {
        themeResetBtn.addEventListener('click', resetTheme);
    }

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

    document.querySelectorAll('.bg-preset-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const bg = btn.dataset.bg;
            if (!bg) return;
            applyTheme({
                backgroundColor: bg,
            }, true);
        });
    });

    if (userBubbleInput) {
        userBubbleInput.addEventListener('input', (e) => {
            const color = e.target.value;
            if (!color) return;
            applyTheme({
                userBubbleColor: color,
            }, true);
        });
    }

    document.addEventListener('click', (e) => {
        if (headerDropdown && !headerDropdown.hidden) {
            if (!headerDropdown.contains(e.target) && !menuBtn.contains(e.target)) {
                closeDropdown();
            }
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeDropdown();
            closeThemePanel();
        }
    });

    // Notify parent window that chat is ready with active primary color and pre-chat settings
    try {
        const preChatConfig = {
            enabled: shell.dataset.preChatEnabled === '1',
            message: shell.dataset.preChatMessage || '',
            delay: parseInt(shell.dataset.preChatDelay || '3', 10),
            displayMode: shell.dataset.preChatDisplayMode || 'always',
        };

        window.parent.postMessage(
            {
                type: 'GETMORE_AI_READY',
                primaryColor: currentTheme.primaryColor,
                preChat: preChatConfig,
            },
            '*'
        );
    } catch (e) {}

    // Header back button closes the widget if embedded in iframe
    if (backBtn) {
        backBtn.addEventListener('click', () => {
            try {
                window.parent.postMessage({ type: 'GETMORE_AI_CLOSE' }, '*');
            } catch (e) {}
        });
    }

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

    async function sendMessage(message) {
        message = (message || '').trim();
        if (!enabled || sending || !message) return;

        // Hide starter buttons after first user message
        if (starterContainer) {
            starterContainer.style.display = 'none';
        }

        sending = true;
        input.disabled = true;
        if (sendBtn) sendBtn.disabled = true;

        addMessage('user', message);
        input.value = '';

        const strings = UI_STRINGS[currentLanguage] || UI_STRINGS.en;
        const typing = addMessage('assistant', strings.thinking, 'typing');

        try {
            const response = await fetch(apiUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    assistant_key: assistantKey,
                    message,
                    history: history.slice(-10),
                    language: currentLanguage,
                }),
            });

            const data = await response.json().catch(() => ({}));
            typing.remove();

            if (!response.ok) {
                addMessage('assistant', data.error || strings.error);
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
            addMessage('assistant', strings.busy);
        } finally {
            sending = false;
            input.disabled = false;
            if (sendBtn) sendBtn.disabled = false;
            input.focus();
        }
    }

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        sendMessage(input.value);
    });

    document.querySelectorAll('.starter-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const msg = btn.dataset.message || btn.textContent.trim();
            if (msg) {
                input.value = msg;
                sendMessage(msg);
            }
        });
    });

    if (enabled && input) {
        input.focus();
    }
})();
