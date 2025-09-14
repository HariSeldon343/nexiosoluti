/**
 * Hook personalizzato per la gestione del tema
 * Fornisce accesso e controllo del sistema di theming
 */

import { useState, useEffect, useCallback, useContext, createContext } from 'react';
import {
  applyThemeToDocument,
  saveThemeToStorage,
  loadThemeFromStorage,
  DEFAULT_THEMES,
  mergeThemes
} from '../utils/theme';

// Context per il tema
export const ThemeContext = createContext(null);

/**
 * Hook per utilizzare il tema corrente
 * @returns {object} Tema corrente e funzioni di gestione
 */
export const useTheme = () => {
  const context = useContext(ThemeContext);

  if (!context) {
    throw new Error('useTheme deve essere utilizzato all\'interno di un ThemeProvider');
  }

  return context;
};

/**
 * Hook per la gestione completa del tema
 * @param {string} tenantId - ID del tenant corrente
 * @param {object} initialTheme - Tema iniziale (opzionale)
 * @returns {object} Stato del tema e funzioni di gestione
 */
export const useThemeManager = (tenantId, initialTheme = null) => {
  // Stato principale del tema
  const [theme, setTheme] = useState(() => {
    // Prova a caricare dal localStorage
    const stored = loadThemeFromStorage(tenantId);
    if (stored) return stored;

    // Altrimenti usa il tema iniziale o quello di default
    return initialTheme || DEFAULT_THEMES.professional;
  });

  // Stato per la modalità dark
  const [isDarkMode, setIsDarkMode] = useState(() => {
    // Controlla preferenza sistema
    if (window.matchMedia) {
      return window.matchMedia('(prefers-color-scheme: dark)').matches;
    }
    return false;
  });

  // Stato per il caricamento
  const [isLoading, setIsLoading] = useState(false);

  // Stato per modifiche non salvate
  const [hasUnsavedChanges, setHasUnsavedChanges] = useState(false);

  // Tema temporaneo per preview
  const [previewTheme, setPreviewTheme] = useState(null);

  // Storia delle modifiche per undo/redo
  const [themeHistory, setThemeHistory] = useState([theme]);
  const [historyIndex, setHistoryIndex] = useState(0);

  /**
   * Applica il tema al documento
   */
  useEffect(() => {
    const activeTheme = previewTheme || theme;
    const themeToApply = {
      ...activeTheme,
      dark: isDarkMode
    };

    applyThemeToDocument(themeToApply);
  }, [theme, previewTheme, isDarkMode]);

  /**
   * Listener per cambio preferenza sistema dark mode
   */
  useEffect(() => {
    if (!window.matchMedia) return;

    const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
    const handleChange = (e) => {
      setIsDarkMode(e.matches);
    };

    mediaQuery.addEventListener('change', handleChange);
    return () => mediaQuery.removeEventListener('change', handleChange);
  }, []);

  /**
   * Aggiorna il tema
   */
  const updateTheme = useCallback((updates) => {
    setTheme(current => {
      const newTheme = typeof updates === 'function'
        ? updates(current)
        : mergeThemes(current, updates);

      // Aggiungi alla storia
      setThemeHistory(history => {
        const newHistory = [...history.slice(0, historyIndex + 1), newTheme];
        setHistoryIndex(newHistory.length - 1);
        return newHistory;
      });

      setHasUnsavedChanges(true);
      return newTheme;
    });
  }, [historyIndex]);

  /**
   * Salva il tema
   */
  const saveTheme = useCallback(async () => {
    setIsLoading(true);
    try {
      // Salva nel localStorage
      saveThemeToStorage(tenantId, theme);

      // Qui potresti fare una chiamata API per salvare sul server
      // await api.saveTheme(tenantId, theme);

      setHasUnsavedChanges(false);
      return true;
    } catch (error) {
      console.error('Errore nel salvataggio del tema:', error);
      return false;
    } finally {
      setIsLoading(false);
    }
  }, [tenantId, theme]);

  /**
   * Resetta al tema di default
   */
  const resetTheme = useCallback((themeName = 'professional') => {
    const defaultTheme = DEFAULT_THEMES[themeName];
    if (defaultTheme) {
      setTheme(defaultTheme);
      setHasUnsavedChanges(true);

      // Reset storia
      setThemeHistory([defaultTheme]);
      setHistoryIndex(0);
    }
  }, []);

  /**
   * Preview temporaneo di un tema
   */
  const startPreview = useCallback((themeToPreview) => {
    setPreviewTheme(themeToPreview);
  }, []);

  /**
   * Ferma il preview
   */
  const stopPreview = useCallback(() => {
    setPreviewTheme(null);
  }, []);

  /**
   * Applica il tema in preview
   */
  const applyPreview = useCallback(() => {
    if (previewTheme) {
      updateTheme(previewTheme);
      setPreviewTheme(null);
    }
  }, [previewTheme, updateTheme]);

  /**
   * Toggle dark mode
   */
  const toggleDarkMode = useCallback(() => {
    setIsDarkMode(prev => !prev);
  }, []);

  /**
   * Undo ultima modifica
   */
  const undo = useCallback(() => {
    if (historyIndex > 0) {
      const newIndex = historyIndex - 1;
      setHistoryIndex(newIndex);
      setTheme(themeHistory[newIndex]);
      setHasUnsavedChanges(true);
    }
  }, [historyIndex, themeHistory]);

  /**
   * Redo modifica
   */
  const redo = useCallback(() => {
    if (historyIndex < themeHistory.length - 1) {
      const newIndex = historyIndex + 1;
      setHistoryIndex(newIndex);
      setTheme(themeHistory[newIndex]);
      setHasUnsavedChanges(true);
    }
  }, [historyIndex, themeHistory]);

  /**
   * Cambia colore primario
   */
  const setPrimaryColor = useCallback((color) => {
    updateTheme({ primary: color });
  }, [updateTheme]);

  /**
   * Cambia colore secondario
   */
  const setSecondaryColor = useCallback((color) => {
    updateTheme({ secondary: color });
  }, [updateTheme]);

  /**
   * Cambia font
   */
  const setFont = useCallback((fontType, fontFamily) => {
    updateTheme(current => ({
      ...current,
      font: {
        ...current.font,
        [fontType]: fontFamily
      }
    }));
  }, [updateTheme]);

  /**
   * Carica tema da file JSON
   */
  const loadThemeFromFile = useCallback(async (file) => {
    setIsLoading(true);
    try {
      const text = await file.text();
      const imported = JSON.parse(text);

      // Verifica struttura base del tema
      if (imported.theme) {
        updateTheme(imported.theme);
        return true;
      } else if (imported.primary) {
        // Assume che sia direttamente un oggetto tema
        updateTheme(imported);
        return true;
      }

      throw new Error('Formato tema non valido');
    } catch (error) {
      console.error('Errore nel caricamento del tema:', error);
      return false;
    } finally {
      setIsLoading(false);
    }
  }, [updateTheme]);

  return {
    // Stato
    theme,
    isDarkMode,
    isLoading,
    hasUnsavedChanges,
    isPreviewActive: !!previewTheme,
    canUndo: historyIndex > 0,
    canRedo: historyIndex < themeHistory.length - 1,

    // Funzioni principali
    updateTheme,
    saveTheme,
    resetTheme,

    // Preview
    startPreview,
    stopPreview,
    applyPreview,

    // Modifiche specifiche
    setPrimaryColor,
    setSecondaryColor,
    setFont,
    toggleDarkMode,

    // Storia
    undo,
    redo,

    // Import/Export
    loadThemeFromFile,

    // Temi disponibili
    availableThemes: DEFAULT_THEMES
  };
};

export default useTheme;