import { onMounted, ref } from 'vue';

export const THEME_KEY = 'wz_theme';

function systemPrefersDark() {
    return window.matchMedia?.('(prefers-color-scheme: dark)')?.matches ?? false;
}

export function applyTheme(theme) {
    const root = document.documentElement;
    if (theme === 'dark') {
        root.classList.add('dark');
    } else {
        root.classList.remove('dark');
    }
}

export function resolveInitialTheme() {
    if (typeof window === 'undefined') {
        return 'light';
    }

    const saved = window.localStorage.getItem(THEME_KEY);
    if (saved === 'dark' || saved === 'light') {
        return saved;
    }

    return systemPrefersDark() ? 'dark' : 'light';
}

export function useTheme() {
    const theme = ref(resolveInitialTheme());

    onMounted(() => {
        applyTheme(theme.value);
    });

    function setTheme(next) {
        theme.value = next;
        window.localStorage.setItem(THEME_KEY, next);
        applyTheme(next);
    }

    function toggleTheme() {
        setTheme(theme.value === 'dark' ? 'light' : 'dark');
    }

    return {
        theme,
        setTheme,
        toggleTheme,
        isDark: () => theme.value === 'dark',
    };
}
