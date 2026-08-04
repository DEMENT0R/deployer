import { computed, ref, watchEffect } from 'vue';

const STORAGE_KEY = 'deployer.theme';

// Порядок обхода кнопкой в шапке.
export const THEME_MODES = ['system', 'light', 'dark'];

const media = window.matchMedia('(prefers-color-scheme: dark)');

const systemDark = ref(media.matches);
media.addEventListener('change', (event) => (systemDark.value = event.matches));

const stored = localStorage.getItem(STORAGE_KEY);
const mode = ref(THEME_MODES.includes(stored) ? stored : 'system');

const isDark = computed(() =>
    mode.value === 'system' ? systemDark.value : mode.value === 'dark',
);

// Тот же класс ставит инлайновый скрипт в app.blade.php — там до первой отрисовки,
// здесь на каждую смену режима.
watchEffect(() => {
    document.documentElement.classList.toggle('dark', isDark.value);
});

const setMode = (value) => {
    mode.value = THEME_MODES.includes(value) ? value : 'system';

    if (mode.value === 'system') {
        localStorage.removeItem(STORAGE_KEY);
    } else {
        localStorage.setItem(STORAGE_KEY, mode.value);
    }
};

const cycleMode = () => {
    const next = (THEME_MODES.indexOf(mode.value) + 1) % THEME_MODES.length;

    setMode(THEME_MODES[next]);
};

export function useTheme() {
    return { mode, isDark, setMode, cycleMode };
}
