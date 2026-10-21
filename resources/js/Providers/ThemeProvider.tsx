import {
  createContext,
  ReactNode,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
} from 'react';

type Theme = 'light' | 'dark' | 'system';
type ResolvedTheme = 'light' | 'dark';

interface ThemeContextValue {
  theme: Theme;
  resolvedTheme: ResolvedTheme;
  setTheme: (theme: Theme) => void;
  toggleTheme: () => void;
}

const ThemeContext = createContext<ThemeContextValue | undefined>(undefined);
const storageKey = 'ui-theme';
const legacyStorageKey = 'legacy-ui-theme';

function getSystemTheme(): ResolvedTheme {
  if (typeof window === 'undefined') {
    return 'light';
  }

  return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

function readStoredTheme(): Theme {
  if (typeof window === 'undefined') {
    return 'system';
  }

  const stored =
    window.localStorage.getItem(storageKey) ?? window.localStorage.getItem(legacyStorageKey);
  if (stored === 'light' || stored === 'dark' || stored === 'system') {
    return stored;
  }

  const previousDarkMode = window.localStorage.getItem('darkMode');
  if (previousDarkMode === 'true') {
    return 'dark';
  }
  if (previousDarkMode === 'false') {
    return 'light';
  }

  return 'system';
}

function applyTheme(theme: ResolvedTheme) {
  if (typeof document === 'undefined') {
    return;
  }

  document.documentElement.classList.toggle('dark', theme === 'dark');
  document.documentElement.style.colorScheme = theme;
}

export function ThemeProvider({ children }: { children: ReactNode }) {
  const [theme, setThemeState] = useState<Theme>('system');
  const [systemTheme, setSystemTheme] = useState<ResolvedTheme>('light');
  const [ready, setReady] = useState(false);
  const resolvedTheme = theme === 'system' ? systemTheme : theme;

  useEffect(() => {
    setThemeState(readStoredTheme());
    setSystemTheme(getSystemTheme());
    setReady(true);
    const media = window.matchMedia('(prefers-color-scheme: dark)');
    const handleChange = () => setSystemTheme(media.matches ? 'dark' : 'light');

    handleChange();
    media.addEventListener('change', handleChange);

    return () => media.removeEventListener('change', handleChange);
  }, []);

  useEffect(() => {
    if (ready) applyTheme(resolvedTheme);
  }, [resolvedTheme, ready]);

  const setTheme = useCallback((nextTheme: Theme) => {
    setThemeState(nextTheme);
    window.localStorage.setItem(storageKey, nextTheme);
    window.localStorage.removeItem(legacyStorageKey);
    window.localStorage.removeItem('darkMode');
  }, []);

  const toggleTheme = useCallback(() => {
    setTheme(resolvedTheme === 'dark' ? 'light' : 'dark');
  }, [resolvedTheme, setTheme]);

  const value = useMemo(
    () => ({ theme, resolvedTheme, setTheme, toggleTheme }),
    [theme, resolvedTheme, setTheme, toggleTheme]
  );

  return <ThemeContext.Provider value={value}>{children}</ThemeContext.Provider>;
}

export function useTheme() {
  const context = useContext(ThemeContext);
  if (!context) {
    throw new Error('useTheme must be used within ThemeProvider');
  }

  return context;
}
