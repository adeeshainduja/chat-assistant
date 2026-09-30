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
    const PREFERRED_LANGUAGE_STORAGE_KEY = `getmore_ai_language_${assistantKey || 'default'}`;
    let currentTheme = { ...adminTheme };
    let preferredLanguage = 'en';
    let currentConversationLanguage = 'en';

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

    // Automatic language detection & manual language handling
    function detectLatinLanguage(text) {
        const lower = text.toLowerCase();

        // High-confidence Singlish phrases
        const singlishPhrases = [
            /\b(class|classes|course|courses|batch|sir|miss|teacher|timetable|schedule|fee|fees|subject|attendance|admission|registration|register|results|result|notes|paper|revision|theory|hall|institute)\s+(eka|eke|ekak|ekata|ekada|monawada|kiyada|thiyenawada|thiyenne|nadda|kawda|denna|karanna)\b/i,
            /\b(check|register|join|enroll|apply)\s+(karanna|karanne|wenna|wenne|karannada|karamuda|puluwanda|ona|one)\b/i,
            /\b(balanna|karanna|denna|ganna|enna|yanna|join wenna|register wenna)\s+puluwanda\b/i,
            /\b(thiyenawa|thiyanawa|thiyenne|thiyena)\s+(nedda|nadda|da)\b/i,
            /\b(classes|panthi)\s+(monawada|thiyenawada|thiyenne)\b/i,
            /\b(teacher|sir|miss)\s+kawda\b/i,
            /\bfee\s+(eka\s+)?kiyada\b/i,
            /\b(ada|heta|iye)\s+(classes|class|panthi|thiyenawa|thiyenawada|thiyenne)\b/i,
            /\b(sir|miss)\s+ge\b/i,
            /\b(wenna|karanna|balanna|denna)\s+(ona|one|puluwanda)\b/i,
        ];

        let singlishScore = 0;
        for (const pattern of singlishPhrases) {
            if (pattern.test(lower)) {
                singlishScore += 3;
            }
        }

        // Distinctive Singlish words
        const singlishWords = new Set([
            'monawada', 'monada', 'mokakda', 'mokadda', 'mokak', 'mokada', 'moko',
            'kohomada', 'kohomadha', 'koheda', 'kohedha', 'kawda', 'kauda', 'kiyada', 'kiyadha',
            'kiyatada', 'kiyathada', 'kiyathadha', 'kawadda', 'kawadha', 'kavadada', 'aei', 'aeyi',
            'mata', 'mage', 'magen', 'mama', 'oya', 'oyage', 'oyaage', 'oyata', 'oyala', 'oyalage', 'oyalata',
            'ape', 'apata', 'apita', 'eya', 'eyage', 'eyata', 'eyala', 'eyalage',
            'meka', 'meke', 'mekata', 'mewa', 'mewaye', 'mehe', 'methana', 'methanata',
            'araka', 'arake', 'ethan', 'ethana', 'othan', 'othana', 'ohe',
            'thiyenawa', 'thiyenawada', 'thiyanawa', 'thiyanawada', 'thiyenne', 'thiyenna',
            'thiyeda', 'thiyenam', 'thiyenawanam', 'thiyena', 'thiyana', 'thibba', 'thibeda', 'thibbada',
            'nadda', 'nedda', 'naeda', 'naedda', 'naha', 'naa', 'nehe', 'nee',
            'puluwanda', 'puluwan', 'barida', 'beri', 'baa', 'baha', 'epa',
            'oneda', 'onada', 'ona', 'oona', 'ooneda',
            'balanna', 'balamuda', 'balamu', 'denna', 'denawada', 'dennako', 'denne',
            'ganna', 'gannawada', 'gannako', 'ganne', 'kiyanna', 'kiyanne', 'kiyanawada', 'kiyannako',
            'ahanna', 'ahanne', 'ahanawada', 'ewanna', 'ewannako', 'evanna',
            'danna', 'dannawada', 'danaganna', 'yanna', 'enna', 'liyanna', 'hoyanna', 'hoyaganna',
            'karanna', 'karanne', 'karalada', 'karamuda', 'wenna', 'wenne', 'wela', 'unada', 'wunada',
            'eka', 'eke', 'ekata', 'ekak', 'ekaka', 'ekada', 'ekakda',
            'gana', 'gena', 'visthara', 'wistara', 'wisthara', 'poddak', 'godak', 'tikak',
            'thamai', 'thamayi', 'thama', 'neda', 'needa', 'thawa', 'thavath', 'tawath', 'wage', 'vage',
            'ada', 'heta', 'hete', 'iye', 'udenma', 'hawasa', 'hawasta', 'dawalta', 'dawasa',
            'dawasata', 'sathiyata', 'sathiya', 'maaseta', 'maseta', 'maase', 'aurudda', 'awurudda',
            'panthi', 'panthiya', 'panthiye', 'padam', 'padama', 'aluth', 'parana', 'lamai', 'sedisi', 'welawa', 'velawa'
        ]);

        const tokens = lower.split(/[\s,.;:!?()[\]{}"'\\/<>+=_-]+/).filter(Boolean);
        for (const token of tokens) {
            if (singlishWords.has(token)) {
                singlishScore += 2;
            } else if (/(wada|nawada|nadda|puluwanda|kiyada|karanna|balanna|denna|wenne|karanne|thiyenne)$/i.test(token)) {
                singlishScore += 1;
            }
        }

        // Distinctive English grammatical phrases
        const englishPhrases = [
            /\b(what|where|when|who|why|how|which)\s+(is|are|was|were|do|does|did|can|could|will|would|should|have|has)\b/i,
            /\b(can|could|will|would|do|does|did|is|are)\s+you\b/i,
            /\b(is|are)\s+there\b/i,
            /\b(i\s+want|i\s+would\s+like|i\s+need|please\s+(tell|give|show|check|send|help))\b/i,
            /\bhow\s+can\s+i\b/i,
        ];

        let englishScore = 0;
        for (const pattern of englishPhrases) {
            if (pattern.test(lower)) {
                englishScore += 3;
            }
        }

        const englishStopwords = new Set([
            'what', 'where', 'when', 'who', 'why', 'how', 'which',
            'is', 'are', 'am', 'was', 'were', 'do', 'does', 'did',
            'can', 'could', 'will', 'would', 'should', 'shall', 'may', 'might', 'must',
            'have', 'has', 'had', 'please', 'tell', 'show', 'check', 'find', 'list',
            'about', 'for', 'the', 'this', 'that', 'these', 'those', 'there', 'their',
            'our', 'your', 'my', 'any', 'some', 'all', 'want', 'need', 'know',
            'available', 'today', 'tomorrow', 'yesterday', 'institute', 'classes',
            'class', 'course', 'courses', 'student', 'lecturer', 'teacher', 'attendance',
            'information', 'details', 'contact', 'fee', 'fees', 'timetable', 'schedule',
            'registration', 'register', 'address', 'location', 'timing', 'timings', 'give',
            'provide', 'help', 'with', 'from', 'much', 'many', 'cost'
        ]);

        for (const token of tokens) {
            if (englishStopwords.has(token)) {
                englishScore += 1;
            }
        }

        if (singlishScore >= 2 && singlishScore >= englishScore) {
            return 'si';
        }

        if (englishScore >= 2 && singlishScore === 0) {
            return 'en';
        }

        if (englishScore > singlishScore && singlishScore === 0) {
            return 'en';
        }

        if (singlishScore >= 2) {
            return 'si';
        }

        return 'auto';
    }

    function detectLanguage(text) {
        if (!text || typeof text !== 'string') return null;
        const trimmed = text.trim();
        if (!trimmed) return null;

        // Pure numbers, phone numbers (+9477..., 077..., 12345), or symbols/emojis only
        if (/^[\d\s+\-().,/#]+$/.test(trimmed)) {
            return null;
        }

        const normalized = trimmed.toLowerCase().replace(/^[^\p{L}\p{N}]+|[^\p{L}\p{N}]+$/gu, '').trim();

        // Common short student ID patterns like STU001, STU-2026-001, ID1234
        if (/^[a-z]{1,5}[-_]?\d+[-_]?\d*$/i.test(normalized)) {
            return null;
        }

        // Must contain at least one letter
        if (!/[\u0D80-\u0DFF\u0B80-\u0BFFa-zA-Z]/.test(trimmed)) {
            return null;
        }

        // Common ambiguous greeting / acknowledgement words that should not trigger language switch
        const ambiguousWords = [
            'hi', 'hello', 'hey', 'ok', 'okay', 'yes', 'no',
            'thanks', 'thank you', 'thx', 'bye', 'goodbye', 'k'
        ];
        if (ambiguousWords.includes(normalized)) {
            return null;
        }

        const sinhalaChars = (trimmed.match(/[\u0D80-\u0DFF]/g) || []).length;
        const tamilChars = (trimmed.match(/[\u0B80-\u0BFF]/g) || []).length;
        const latinChars = (trimmed.match(/[a-zA-Z]/g) || []).length;

        // Sinhala Unicode dominant / mixed
        if (sinhalaChars > 0 && tamilChars === 0) {
            if (latinChars === 0) {
                return 'si';
            }
            const tokens = trimmed.split(/[\s,.;:!?()[\]{}"'\\/<>+=_-]+/).filter(Boolean);
            let sinhalaWordCount = 0;
            let englishWordCount = 0;
            for (const token of tokens) {
                if (/[\u0D80-\u0DFF]/.test(token)) {
                    sinhalaWordCount++;
                } else if (/^[a-zA-Z]+$/.test(token)) {
                    englishWordCount++;
                }
            }
            return (sinhalaWordCount >= englishWordCount || sinhalaChars >= latinChars) ? 'si' : 'en';
        }

        // Tamil Unicode dominant / mixed
        if (tamilChars > 0 && sinhalaChars === 0) {
            if (latinChars === 0) {
                return 'ta';
            }
            const tokens = trimmed.split(/[\s,.;:!?()[\]{}"'\\/<>+=_-]+/).filter(Boolean);
            let tamilWordCount = 0;
            let englishWordCount = 0;
            for (const token of tokens) {
                if (/[\u0B80-\u0BFF]/.test(token)) {
                    tamilWordCount++;
                } else if (/^[a-zA-Z]+$/.test(token)) {
                    englishWordCount++;
                }
            }
            return (tamilWordCount >= englishWordCount || tamilChars >= latinChars) ? 'ta' : 'en';
        }

        if (sinhalaChars > tamilChars) return 'si';
        if (tamilChars > sinhalaChars) return 'ta';

        // Latin only (English, Singlish, or ambiguous)
        if (latinChars > 0) {
            return detectLatinLanguage(trimmed);
        }

        return null;
    }

    function setPreferredLanguage(lang, save = false) {
        if (!['en', 'si', 'ta'].includes(lang)) {
            lang = 'en';
        }
        preferredLanguage = lang;

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
                localStorage.setItem(PREFERRED_LANGUAGE_STORAGE_KEY, lang);
            } catch (e) {}
        }
    }

    function initLanguage() {
        let savedLang = 'en';
        try {
            const stored = localStorage.getItem(PREFERRED_LANGUAGE_STORAGE_KEY);
            if (stored && ['en', 'si', 'ta'].includes(stored)) {
                savedLang = stored;
            }
        } catch (e) {}
        preferredLanguage = savedLang;
        currentConversationLanguage = savedLang;
        setPreferredLanguage(savedLang, false);
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
            setPreferredLanguage(chosen, true);
            currentConversationLanguage = chosen;
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

    // Notify parent window that chat is ready with active primary color
    try {
        window.parent.postMessage(
            { type: 'GETMORE_AI_READY', primaryColor: currentTheme.primaryColor },
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

    const GRADUATION_HAT_SVG = '<span class="assistant-item-icon" aria-hidden="true">' +
        '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">' +
        '<path d="M22 10v6M2 10l10-5 10 5-10 5z"/>' +
        '<path d="M6 12v5c3 3 9 3 12 0v-5"/>' +
        '</svg>' +
        '</span>';

    function cleanInlineMarkdown(text) {
        if (!text) return '';

        // 1. Escape HTML entities for safety
        let s = text
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');

        // 2. Bold: **text** or __text__
        s = s.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        s = s.replace(/__(.+?)__/g, '<strong>$1</strong>');

        // 3. Italic: *text* (when surrounded by spaces or punctuation)
        s = s.replace(/(^|[^\w*])\*([^*\n]+?)\*([^\w*]|$)/g, '$1<em>$2</em>$3');

        // 4. Code: `code`
        s = s.replace(/`([^`]+)`/g, '<code>$1</code>');

        // 5. Remove any leftover stray asterisks or markdown artifacts
        s = s.replace(/\*\*/g, '');
        s = s.replace(/(^|\s)\*+(\s|$)/g, '$1$2');
        s = s.replace(/\*/g, '');

        // 6. Clean leading/trailing orphan dots
        s = s.replace(/^\s*\.\s+/, '');

        return s.trim();
    }

    function resolveListType(items, prevParagraphText, messageListType = 'default') {
        if (messageListType === 'teacher') return 'teacher';
        if (messageListType === 'class') return 'class';
        if (messageListType === 'institute' || messageListType === 'attendance') return 'default';

        const context = ((prevParagraphText || '') + ' ' + items.join(' ')).toLowerCase();

        // If context is explicitly about institute details, contact info, facilities, or attendance -> normal dot bullet
        const isInstituteOrMeta = /\b(institute|about|address|contact|phone|email|website|opening hours|hours|facilities|wi-fi|wifi|parking|air-conditioned|classrooms|attendance|present|absent|date:|status:|index number|mobile number|ලිපිනය|දුරකථන|විස්තරය|පැමිණීම|තොරතුරු|வசதிகள்|முகவரி|தொலைபேசி)\b/i.test(context);
        if (isInstituteOrMeta) {
            return 'default';
        }

        // Check for Teacher / Lecturer markers
        const isTeacher = /\b(dr\.|prof\.|lecturer|teacher|sir|miss|rev\.|mr\.|mrs\.|ආචාර්ය|මහාචාර්ය|ගුරු|දේශක|ஆசிரியர்|விரிவுரையாளர்)\b/i.test(context);
        if (isTeacher) {
            return 'teacher';
        }

        // Check for Class / Course markers
        const isClass = /\b(grade\s+\d+|theory|revision|course|courses|classes|class|batch|subject|hall|fee|fees|enrollment|starting soon|schedule|start date|ශ්‍රේණිය|පන්ති|පාඨමාලා|වර්ගය|வகுப்பு)\b/i.test(context);
        if (isClass) {
            return 'class';
        }

        return 'default';
    }

    function formatAssistantReply(rawText, messageListType = 'default') {
        if (!rawText || typeof rawText !== 'string') return '';

        const lines = rawText.split(/\r?\n/);
        const blocks = [];
        let currentList = null;
        let lastParagraph = '';

        // Matches bullet styles: "* item", "- item", "+ item", "• item", ". item", "1. item", "1) item"
        // Also supports: ". **item**", "* **item**", etc.
        const bulletRegex = /^\s*(?:[*\-+•\u2022\u25cf\u25cb\u25aa\u25ab]\s*|\d+[\.)]\s+|\.(?!\d)\s*)(.*)$/;

        // Matches lines starting with bold item e.g. "**Teacher Name**: Class Details"
        const boldItemRegex = /^\s*(\*\*[^*]+?\*\*:\s*.*)$/;

        for (let i = 0; i < lines.length; i++) {
            const line = lines[i].trim();

            if (!line) {
                continue;
            }

            let itemText = null;
            const bulletMatch = line.match(bulletRegex);
            if (bulletMatch && bulletMatch[1].trim()) {
                itemText = bulletMatch[1].trim();
            } else {
                const boldMatch = line.match(boldItemRegex);
                if (boldMatch && boldMatch[1].trim()) {
                    itemText = boldMatch[1].trim();
                }
            }

            if (itemText !== null) {
                // Strip any secondary bullet markers that might have been doubled in raw text (e.g. "• • Address")
                itemText = itemText.replace(/^[\s*\-+•:\u2022\u25cf\u25cb\u25aa\u25ab.]+\s*/, '');

                if (!currentList) {
                    currentList = [];
                    blocks.push({ type: 'list', items: currentList, prevParagraph: lastParagraph });
                }
                currentList.push(cleanInlineMarkdown(itemText));
            } else {
                currentList = null;
                const cleaned = cleanInlineMarkdown(line);
                lastParagraph = cleaned;
                blocks.push({ type: 'paragraph', text: cleaned });
            }
        }

        if (blocks.length === 0) {
            return '';
        }

        let html = '<div class="assistant-msg-content">';
        for (const block of blocks) {
            if (block.type === 'paragraph') {
                html += '<p class="assistant-paragraph">' + block.text + '</p>';
            } else if (block.type === 'list') {
                const listCategory = resolveListType(block.items, block.prevParagraph, messageListType);

                if (listCategory === 'teacher' || listCategory === 'class') {
                    const ulClass = (listCategory === 'teacher')
                        ? 'assistant-list assistant-list--teacher assistant-list-teacher'
                        : 'assistant-list assistant-list--class assistant-list-class';

                    html += '<ul class="' + ulClass + '">';
                    for (const item of block.items) {
                        html += '<li class="assistant-list-item">' +
                            GRADUATION_HAT_SVG +
                            '<span class="assistant-item-text">' + item + '</span>' +
                            '</li>';
                    }
                    html += '</ul>';
                } else {
                    html += '<ul class="assistant-list assistant-list--normal">';
                    for (const item of block.items) {
                        html += '<li class="assistant-list-item"><span class="assistant-item-text">' + item + '</span></li>';
                    }
                    html += '</ul>';
                }
            }
        }
        html += '</div>';

        return html;
    }

    const addMessage = (role, text, extraClass = '', listType = 'default') => {
        const wrap = document.createElement('div');
        wrap.className = `message ${role} ${extraClass}`.trim();

        const bubble = document.createElement('div');
        bubble.className = `bubble ${role}-bubble`;

        if (role === 'assistant' && extraClass !== 'typing') {
            bubble.innerHTML = formatAssistantReply(text, listType);
        } else {
            bubble.textContent = text;
        }

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

        const detected = detectLanguage(message);
        let responseLanguage = 'en';

        // Language priority:
        // 1. Clearly detectable language of the CURRENT user message
        // 2. Current conversation language
        // 3. Manually selected preferred language
        // 4. English fallback
        if (detected) {
            responseLanguage = detected;
            if (detected !== 'auto') {
                currentConversationLanguage = detected;
            }
        } else if (currentConversationLanguage && ['en', 'si', 'ta'].includes(currentConversationLanguage)) {
            responseLanguage = currentConversationLanguage;
        } else if (preferredLanguage && ['en', 'si', 'ta'].includes(preferredLanguage)) {
            responseLanguage = preferredLanguage;
        } else {
            responseLanguage = 'en';
        }

        const uiStrings = UI_STRINGS[preferredLanguage] || UI_STRINGS.en;
        const turnStrings = UI_STRINGS[responseLanguage] || UI_STRINGS[currentConversationLanguage] || uiStrings;
        const typing = addMessage('assistant', turnStrings.thinking, 'typing');

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
                    preferred_language: preferredLanguage,
                    detected_language: detected,
                    response_language: responseLanguage,
                    language: responseLanguage,
                }),
            });

            const data = await response.json().catch(() => ({}));
            typing.remove();

            if (!response.ok) {
                addMessage('assistant', data.error || turnStrings.error);
                return;
            }

            if (data.response_language && ['en', 'si', 'ta'].includes(data.response_language)) {
                currentConversationLanguage = data.response_language;
            }

            const reply = data.reply || 'I could not produce a response.';
            const listType = data.list_type || 'default';
            addMessage('assistant', reply, '', listType);

            history.push({ role: 'user', content: message });
            history.push({ role: 'assistant', content: reply });

            if (history.length > 20) {
                history.splice(0, history.length - 20);
            }
        } catch (error) {
            typing.remove();
            addMessage('assistant', turnStrings.busy);
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

    const welcomeBubble = document.querySelector('.welcome-bubble');
    if (welcomeBubble) {
        const welcomeText = (welcomeBubble.innerText || welcomeBubble.textContent || '').trim();
        if (welcomeText) {
            welcomeBubble.innerHTML = formatAssistantReply(welcomeText);
        }
    }

    if (enabled && input) {
        input.focus();
    }
})();
