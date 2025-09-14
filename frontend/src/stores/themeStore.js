import { create } from 'zustand';
import { persist } from 'zustand/middleware';

/**
 * Store per la gestione del tema e personalizzazione
 */
const useThemeStore = create(
  persist(
    (set, get) => ({
      isDarkMode: false,
      primaryColor: '#2563EB',
      tenantBranding: {
        logo: null,
        favicon: null,
        companyName: 'NexioSolution',
        primaryColor: '#2563EB',
        secondaryColor: '#10B981'
      },

      // Toggle dark mode
      toggleTheme: () => {
        const newMode = !get().isDarkMode;
        set({ isDarkMode: newMode });

        // Applica classe al DOM
        if (newMode) {
          document.documentElement.classList.add('dark');
        } else {
          document.documentElement.classList.remove('dark');
        }
      },

      // Set dark mode
      setDarkMode: (isDark) => {
        set({ isDarkMode: isDark });
        if (isDark) {
          document.documentElement.classList.add('dark');
        } else {
          document.documentElement.classList.remove('dark');
        }
      },

      // Update primary color
      setPrimaryColor: (color) => {
        set({ primaryColor: color });
        // Aggiorna variabili CSS
        document.documentElement.style.setProperty('--color-primary', color);
      },

      // Update tenant branding
      setTenantBranding: (branding) => {
        set(state => ({
          tenantBranding: {
            ...state.tenantBranding,
            ...branding
          }
        }));

        // Applica colori personalizzati
        if (branding.primaryColor) {
          document.documentElement.style.setProperty('--color-primary', branding.primaryColor);
        }
        if (branding.secondaryColor) {
          document.documentElement.style.setProperty('--color-secondary', branding.secondaryColor);
        }

        // Aggiorna favicon
        if (branding.favicon) {
          const link = document.querySelector("link[rel*='icon']") || document.createElement('link');
          link.type = 'image/x-icon';
          link.rel = 'shortcut icon';
          link.href = branding.favicon;
          document.getElementsByTagName('head')[0].appendChild(link);
        }
      },

      // Initialize theme
      initTheme: () => {
        const isDark = get().isDarkMode;
        const branding = get().tenantBranding;

        // Applica dark mode
        if (isDark) {
          document.documentElement.classList.add('dark');
        }

        // Applica colori personalizzati
        if (branding.primaryColor) {
          document.documentElement.style.setProperty('--color-primary', branding.primaryColor);
        }
        if (branding.secondaryColor) {
          document.documentElement.style.setProperty('--color-secondary', branding.secondaryColor);
        }
      }
    }),
    {
      name: 'theme-storage',
      partialize: (state) => ({
        isDarkMode: state.isDarkMode,
        primaryColor: state.primaryColor,
        tenantBranding: state.tenantBranding
      })
    }
  )
);

export { useThemeStore };