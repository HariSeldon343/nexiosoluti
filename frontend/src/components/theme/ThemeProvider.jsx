/**
 * Provider per il sistema di theming white-label
 * Gestisce il caricamento e l'applicazione dei temi per tenant
 */

import React, { useEffect, useState, useMemo } from 'react';
import { ThemeContext } from '../../hooks/useTheme';
import { useThemeManager } from '../../hooks/useTheme';
import { DEFAULT_THEMES } from '../../utils/theme';

/**
 * Provider del tema che wrappa l'intera applicazione
 * @param {object} props - Props del componente
 * @param {React.ReactNode} props.children - Componenti figli
 * @param {string} props.tenantId - ID del tenant corrente
 * @param {object} props.initialTheme - Tema iniziale (opzionale)
 * @param {boolean} props.loadFromAPI - Se caricare il tema da API
 */
const ThemeProvider = ({
  children,
  tenantId = 'default',
  initialTheme = null,
  loadFromAPI = false
}) => {
  // Usa il theme manager hook
  const themeManager = useThemeManager(tenantId, initialTheme);

  // Stato per il caricamento iniziale
  const [isInitialized, setIsInitialized] = useState(false);

  /**
   * Carica il tema da API se richiesto
   */
  useEffect(() => {
    const loadTheme = async () => {
      if (loadFromAPI) {
        try {
          // Simula caricamento da API
          // In produzione, sostituire con vera chiamata API
          const response = await fetch(`/api/tenants/${tenantId}/theme`);

          if (response.ok) {
            const themeData = await response.json();
            themeManager.updateTheme(themeData);
          }
        } catch (error) {
          console.error('Errore nel caricamento del tema da API:', error);
          // Fallback al tema di default
          themeManager.resetTheme('professional');
        }
      }

      setIsInitialized(true);
    };

    loadTheme();
  }, [tenantId, loadFromAPI]);

  /**
   * Effetto per gestire le transizioni del tema
   */
  useEffect(() => {
    // Aggiungi classe per abilitare le transizioni CSS
    document.documentElement.classList.add('theme-transition');

    // Rimuovi dopo l'animazione
    const timer = setTimeout(() => {
      document.documentElement.classList.remove('theme-transition');
    }, 300);

    return () => clearTimeout(timer);
  }, [themeManager.theme]);

  /**
   * Effetto per gestire la modalità dark dal sistema
   */
  useEffect(() => {
    const handleSystemThemeChange = (e) => {
      if (!localStorage.getItem(`dark_mode_override_${tenantId}`)) {
        // Se non c'è un override manuale, segui il sistema
        if (e.matches !== themeManager.isDarkMode) {
          themeManager.toggleDarkMode();
        }
      }
    };

    const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
    mediaQuery.addEventListener('change', handleSystemThemeChange);

    return () => {
      mediaQuery.removeEventListener('change', handleSystemThemeChange);
    };
  }, [tenantId, themeManager]);

  /**
   * Effetto per sincronizzare con altre schede/finestre
   */
  useEffect(() => {
    const handleStorageChange = (e) => {
      if (e.key === `theme_${tenantId}`) {
        // Il tema è stato modificato in un'altra scheda
        if (e.newValue) {
          try {
            const newTheme = JSON.parse(e.newValue);
            themeManager.updateTheme(newTheme);
          } catch (error) {
            console.error('Errore nella sincronizzazione del tema:', error);
          }
        }
      }
    };

    window.addEventListener('storage', handleStorageChange);
    return () => window.removeEventListener('storage', handleStorageChange);
  }, [tenantId, themeManager]);

  /**
   * Valore del context memoizzato per performance
   */
  const contextValue = useMemo(() => ({
    ...themeManager,
    tenantId,
    isInitialized
  }), [themeManager, tenantId, isInitialized]);

  // Loading state
  if (!isInitialized) {
    return (
      <div className="flex items-center justify-center min-h-screen bg-gray-50">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-500 mx-auto mb-4"></div>
          <p className="text-gray-600">Caricamento tema...</p>
        </div>
      </div>
    );
  }

  return (
    <ThemeContext.Provider value={contextValue}>
      {children}
    </ThemeContext.Provider>
  );
};

/**
 * HOC per wrappare componenti con accesso al tema
 * @param {React.ComponentType} Component - Componente da wrappare
 * @returns {React.ComponentType} Componente wrappato
 */
export const withTheme = (Component) => {
  return function ThemedComponent(props) {
    return (
      <ThemeContext.Consumer>
        {theme => <Component {...props} theme={theme} />}
      </ThemeContext.Consumer>
    );
  };
};

/**
 * Componente per applicare stili condizionali basati sul tema
 * @param {object} props - Props del componente
 * @param {React.ReactNode} props.children - Contenuto
 * @param {string} props.variant - Variante del tema da applicare
 */
export const ThemedSection = ({ children, variant = 'default', className = '' }) => {
  const variantClasses = {
    default: 'bg-white dark:bg-gray-900',
    primary: 'bg-primary-50 dark:bg-primary-900',
    secondary: 'bg-secondary-50 dark:bg-secondary-900',
    muted: 'bg-gray-50 dark:bg-gray-800',
    accent: 'bg-accent-50 dark:bg-accent-900'
  };

  return (
    <div className={`${variantClasses[variant]} ${className}`}>
      {children}
    </div>
  );
};

export default ThemeProvider;