/**
 * Utility per la gestione del sistema di theming white-label
 * Gestisce import/export, generazione e applicazione dei temi
 */

import { generateThemeFromColor, extractColorsFromImage } from './colors';

/**
 * Temi predefiniti disponibili nel sistema
 */
export const DEFAULT_THEMES = {
  professional: {
    name: 'Professional',
    primary: '#2563eb',
    secondary: '#64748b',
    accent: '#0ea5e9',
    neutral: '#f1f5f9',
    dark: false,
    font: {
      heading: 'Inter',
      body: 'Inter'
    }
  },
  creative: {
    name: 'Creative',
    primary: '#a855f7',
    secondary: '#ec4899',
    accent: '#f97316',
    neutral: '#faf5ff',
    dark: false,
    font: {
      heading: 'Poppins',
      body: 'Open Sans'
    }
  },
  nature: {
    name: 'Nature',
    primary: '#16a34a',
    secondary: '#a16207',
    accent: '#0891b2',
    neutral: '#f0fdf4',
    dark: false,
    font: {
      heading: 'Playfair Display',
      body: 'Source Sans Pro'
    }
  },
  minimal: {
    name: 'Minimal',
    primary: '#18181b',
    secondary: '#71717a',
    accent: '#3f3f46',
    neutral: '#fafafa',
    dark: false,
    font: {
      heading: 'Helvetica Neue',
      body: 'Helvetica Neue'
    }
  },
  corporate: {
    name: 'Corporate',
    primary: '#1e3a8a',
    secondary: '#ca8a04',
    accent: '#dc2626',
    neutral: '#f8fafc',
    dark: false,
    font: {
      heading: 'Montserrat',
      body: 'Roboto'
    }
  }
};

/**
 * Genera un tema completo dal logo caricato
 * @param {File|string} logo - File del logo o URL
 * @returns {Promise<object>} Tema generato
 */
export const generateThemeFromLogo = async (logo) => {
  try {
    let imageUrl = logo;

    // Se è un file, crea un URL temporaneo
    if (logo instanceof File) {
      imageUrl = URL.createObjectURL(logo);
    }

    // Estrai i colori dominanti dall'immagine
    const dominantColors = await extractColorsFromImage(imageUrl);

    // Usa il primo colore dominante come primario
    const primaryColor = dominantColors[0];
    const theme = generateThemeFromColor(primaryColor);

    // Aggiungi colori secondari e accent dai colori dominanti
    if (dominantColors[1]) {
      theme.secondary = generateThemeFromColor(dominantColors[1]).primary;
    }
    if (dominantColors[2]) {
      theme.accent = dominantColors[2];
    }

    // Cleanup URL temporaneo se creato
    if (logo instanceof File) {
      URL.revokeObjectURL(imageUrl);
    }

    return {
      ...theme,
      generated: true,
      sourceImage: logo instanceof File ? logo.name : logo
    };
  } catch (error) {
    console.error('Errore nella generazione del tema dal logo:', error);
    throw error;
  }
};

/**
 * Applica le variabili CSS del tema al documento
 * @param {object} theme - Oggetto tema da applicare
 */
export const applyThemeToDocument = (theme) => {
  const root = document.documentElement;

  // Applica colori primari
  if (theme.primary) {
    if (typeof theme.primary === 'object') {
      Object.entries(theme.primary).forEach(([key, value]) => {
        root.style.setProperty(`--color-primary-${key}`, value);
      });
    } else {
      root.style.setProperty('--color-primary', theme.primary);
    }
  }

  // Applica colori secondari
  if (theme.secondary) {
    if (typeof theme.secondary === 'object') {
      Object.entries(theme.secondary).forEach(([key, value]) => {
        root.style.setProperty(`--color-secondary-${key}`, value);
      });
    } else {
      root.style.setProperty('--color-secondary', theme.secondary);
    }
  }

  // Applica colori neutri
  if (theme.neutral) {
    if (typeof theme.neutral === 'object') {
      Object.entries(theme.neutral).forEach(([key, value]) => {
        root.style.setProperty(`--color-neutral-${key}`, value);
      });
    } else {
      root.style.setProperty('--color-neutral', theme.neutral);
    }
  }

  // Applica colori semantici
  if (theme.semantic) {
    Object.entries(theme.semantic).forEach(([key, value]) => {
      root.style.setProperty(`--color-${key}`, value);
    });
  }

  // Applica font
  if (theme.font) {
    if (theme.font.heading) {
      root.style.setProperty('--font-heading', theme.font.heading);
    }
    if (theme.font.body) {
      root.style.setProperty('--font-body', theme.font.body);
    }
  }

  // Applica altre proprietà personalizzate
  if (theme.borderRadius) {
    root.style.setProperty('--border-radius', theme.borderRadius);
  }
  if (theme.spacing) {
    if (theme.spacing.base) {
      root.style.setProperty('--spacing-base', `${theme.spacing.base}px`);
    }
  }

  // Applica modalità dark/light
  if (theme.dark !== undefined) {
    root.classList.toggle('dark', theme.dark);
  }
};

/**
 * Esporta il tema corrente in formato JSON
 * @param {object} theme - Tema da esportare
 * @returns {string} JSON del tema
 */
export const exportTheme = (theme) => {
  const exportData = {
    version: '1.0.0',
    timestamp: new Date().toISOString(),
    theme: theme
  };

  return JSON.stringify(exportData, null, 2);
};

/**
 * Importa un tema da JSON
 * @param {string} jsonString - Stringa JSON del tema
 * @returns {object} Tema importato
 */
export const importTheme = (jsonString) => {
  try {
    const data = JSON.parse(jsonString);

    // Verifica versione e struttura
    if (!data.theme) {
      throw new Error('Formato tema non valido');
    }

    return data.theme;
  } catch (error) {
    console.error('Errore nell\'importazione del tema:', error);
    throw new Error('Impossibile importare il tema: formato non valido');
  }
};

/**
 * Salva il tema nel localStorage
 * @param {string} tenantId - ID del tenant
 * @param {object} theme - Tema da salvare
 */
export const saveThemeToStorage = (tenantId, theme) => {
  const key = `theme_${tenantId}`;
  localStorage.setItem(key, JSON.stringify(theme));
};

/**
 * Carica il tema dal localStorage
 * @param {string} tenantId - ID del tenant
 * @returns {object|null} Tema salvato o null
 */
export const loadThemeFromStorage = (tenantId) => {
  const key = `theme_${tenantId}`;
  const stored = localStorage.getItem(key);

  if (stored) {
    try {
      return JSON.parse(stored);
    } catch (error) {
      console.error('Errore nel caricamento del tema:', error);
      return null;
    }
  }

  return null;
};

/**
 * Genera CSS personalizzato dal tema
 * @param {object} theme - Tema da convertire
 * @returns {string} Stringa CSS
 */
export const generateCSSFromTheme = (theme) => {
  let css = ':root {\n';

  // Genera variabili CSS per i colori
  if (theme.primary) {
    if (typeof theme.primary === 'object') {
      Object.entries(theme.primary).forEach(([key, value]) => {
        css += `  --color-primary-${key}: ${value};\n`;
      });
    } else {
      css += `  --color-primary: ${theme.primary};\n`;
    }
  }

  if (theme.secondary) {
    if (typeof theme.secondary === 'object') {
      Object.entries(theme.secondary).forEach(([key, value]) => {
        css += `  --color-secondary-${key}: ${value};\n`;
      });
    } else {
      css += `  --color-secondary: ${theme.secondary};\n`;
    }
  }

  if (theme.neutral) {
    if (typeof theme.neutral === 'object') {
      Object.entries(theme.neutral).forEach(([key, value]) => {
        css += `  --color-neutral-${key}: ${value};\n`;
      });
    }
  }

  if (theme.semantic) {
    Object.entries(theme.semantic).forEach(([key, value]) => {
      css += `  --color-${key}: ${value};\n`;
    });
  }

  // Genera variabili per i font
  if (theme.font) {
    if (theme.font.heading) {
      css += `  --font-heading: ${theme.font.heading};\n`;
    }
    if (theme.font.body) {
      css += `  --font-body: ${theme.font.body};\n`;
    }
  }

  // Altre proprietà
  if (theme.borderRadius) {
    css += `  --border-radius: ${theme.borderRadius};\n`;
  }

  css += '}\n';

  return css;
};

/**
 * Scarica il tema come file CSS
 * @param {object} theme - Tema da scaricare
 * @param {string} filename - Nome del file
 */
export const downloadThemeAsCSS = (theme, filename = 'theme.css') => {
  const css = generateCSSFromTheme(theme);
  const blob = new Blob([css], { type: 'text/css' });
  const url = URL.createObjectURL(blob);

  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
};

/**
 * Scarica il tema come file JSON
 * @param {object} theme - Tema da scaricare
 * @param {string} filename - Nome del file
 */
export const downloadThemeAsJSON = (theme, filename = 'theme.json') => {
  const json = exportTheme(theme);
  const blob = new Blob([json], { type: 'application/json' });
  const url = URL.createObjectURL(blob);

  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
};

/**
 * Verifica la compatibilità del tema con gli standard di accessibilità
 * @param {object} theme - Tema da verificare
 * @returns {object} Risultati della verifica
 */
export const checkThemeAccessibility = (theme) => {
  const results = {
    passed: [],
    warnings: [],
    errors: []
  };

  // Verifica contrasti colori principali
  // Questo è un esempio semplificato - in produzione usare verifiche più complete
  if (theme.primary && theme.neutral) {
    const primaryColor = typeof theme.primary === 'object' ? theme.primary[500] : theme.primary;
    const backgroundColor = typeof theme.neutral === 'object' ? theme.neutral[50] : theme.neutral;

    // Qui andrebbero aggiunte le verifiche di contrasto reali
    results.passed.push('Colori principali definiti');
  }

  if (theme.font) {
    if (theme.font.body && theme.font.heading) {
      results.passed.push('Font accessibili definiti');
    } else {
      results.warnings.push('Font non completamente definiti');
    }
  }

  return results;
};

/**
 * Merge di due temi (utile per override parziali)
 * @param {object} baseTheme - Tema base
 * @param {object} overrides - Override da applicare
 * @returns {object} Tema merged
 */
export const mergeThemes = (baseTheme, overrides) => {
  return {
    ...baseTheme,
    ...overrides,
    primary: { ...baseTheme.primary, ...overrides.primary },
    secondary: { ...baseTheme.secondary, ...overrides.secondary },
    neutral: { ...baseTheme.neutral, ...overrides.neutral },
    semantic: { ...baseTheme.semantic, ...overrides.semantic },
    font: { ...baseTheme.font, ...overrides.font }
  };
};

export default {
  DEFAULT_THEMES,
  generateThemeFromLogo,
  applyThemeToDocument,
  exportTheme,
  importTheme,
  saveThemeToStorage,
  loadThemeFromStorage,
  generateCSSFromTheme,
  downloadThemeAsCSS,
  downloadThemeAsJSON,
  checkThemeAccessibility,
  mergeThemes
};