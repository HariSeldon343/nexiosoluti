/**
 * Utility per la gestione dei colori nel sistema di theming
 * Supporta conversioni, generazione palette e verifica accessibilità
 */

/**
 * Converte un colore HEX in RGB
 * @param {string} hex - Colore in formato HEX
 * @returns {object} Oggetto con valori r, g, b
 */
export const hexToRgb = (hex) => {
  const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
  return result ? {
    r: parseInt(result[1], 16),
    g: parseInt(result[2], 16),
    b: parseInt(result[3], 16)
  } : null;
};

/**
 * Converte RGB in HEX
 * @param {number} r - Rosso (0-255)
 * @param {number} g - Verde (0-255)
 * @param {number} b - Blu (0-255)
 * @returns {string} Colore in formato HEX
 */
export const rgbToHex = (r, g, b) => {
  return "#" + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1);
};

/**
 * Converte RGB in HSL
 * @param {number} r - Rosso (0-255)
 * @param {number} g - Verde (0-255)
 * @param {number} b - Blu (0-255)
 * @returns {object} Oggetto con valori h, s, l
 */
export const rgbToHsl = (r, g, b) => {
  r /= 255;
  g /= 255;
  b /= 255;

  const max = Math.max(r, g, b);
  const min = Math.min(r, g, b);
  let h, s, l = (max + min) / 2;

  if (max === min) {
    h = s = 0;
  } else {
    const d = max - min;
    s = l > 0.5 ? d / (2 - max - min) : d / (max + min);

    switch (max) {
      case r: h = ((g - b) / d + (g < b ? 6 : 0)) / 6; break;
      case g: h = ((b - r) / d + 2) / 6; break;
      case b: h = ((r - g) / d + 4) / 6; break;
    }
  }

  return {
    h: Math.round(h * 360),
    s: Math.round(s * 100),
    l: Math.round(l * 100)
  };
};

/**
 * Converte HSL in RGB
 * @param {number} h - Hue (0-360)
 * @param {number} s - Saturation (0-100)
 * @param {number} l - Lightness (0-100)
 * @returns {object} Oggetto con valori r, g, b
 */
export const hslToRgb = (h, s, l) => {
  h /= 360;
  s /= 100;
  l /= 100;

  let r, g, b;

  if (s === 0) {
    r = g = b = l;
  } else {
    const hue2rgb = (p, q, t) => {
      if (t < 0) t += 1;
      if (t > 1) t -= 1;
      if (t < 1/6) return p + (q - p) * 6 * t;
      if (t < 1/2) return q;
      if (t < 2/3) return p + (q - p) * (2/3 - t) * 6;
      return p;
    };

    const q = l < 0.5 ? l * (1 + s) : l + s - l * s;
    const p = 2 * l - q;

    r = hue2rgb(p, q, h + 1/3);
    g = hue2rgb(p, q, h);
    b = hue2rgb(p, q, h - 1/3);
  }

  return {
    r: Math.round(r * 255),
    g: Math.round(g * 255),
    b: Math.round(b * 255)
  };
};

/**
 * Genera una palette di colori basata su un colore principale
 * @param {string} baseColor - Colore base in formato HEX
 * @returns {object} Palette con diverse tonalità
 */
export const generatePalette = (baseColor) => {
  const rgb = hexToRgb(baseColor);
  const hsl = rgbToHsl(rgb.r, rgb.g, rgb.b);

  const palette = {
    50: null,
    100: null,
    200: null,
    300: null,
    400: null,
    500: baseColor,
    600: null,
    700: null,
    800: null,
    900: null,
    950: null
  };

  // Genera tonalità più chiare
  const lightSteps = [95, 90, 80, 70, 60];
  const lightKeys = [50, 100, 200, 300, 400];

  lightKeys.forEach((key, index) => {
    const newRgb = hslToRgb(hsl.h, hsl.s, lightSteps[index]);
    palette[key] = rgbToHex(newRgb.r, newRgb.g, newRgb.b);
  });

  // Genera tonalità più scure
  const darkSteps = [40, 30, 20, 10, 5];
  const darkKeys = [600, 700, 800, 900, 950];

  darkKeys.forEach((key, index) => {
    const newRgb = hslToRgb(hsl.h, hsl.s, darkSteps[index]);
    palette[key] = rgbToHex(newRgb.r, newRgb.g, newRgb.b);
  });

  return palette;
};

/**
 * Calcola il colore complementare
 * @param {string} color - Colore in formato HEX
 * @returns {string} Colore complementare in formato HEX
 */
export const getComplementary = (color) => {
  const rgb = hexToRgb(color);
  const hsl = rgbToHsl(rgb.r, rgb.g, rgb.b);

  // Aggiungi 180 gradi per ottenere il complementare
  const complementaryHue = (hsl.h + 180) % 360;
  const complementaryRgb = hslToRgb(complementaryHue, hsl.s, hsl.l);

  return rgbToHex(complementaryRgb.r, complementaryRgb.g, complementaryRgb.b);
};

/**
 * Genera una triade di colori
 * @param {string} color - Colore in formato HEX
 * @returns {array} Array con tre colori in formato HEX
 */
export const getTriadic = (color) => {
  const rgb = hexToRgb(color);
  const hsl = rgbToHsl(rgb.r, rgb.g, rgb.b);

  const triadic1 = (hsl.h + 120) % 360;
  const triadic2 = (hsl.h + 240) % 360;

  const rgb1 = hslToRgb(triadic1, hsl.s, hsl.l);
  const rgb2 = hslToRgb(triadic2, hsl.s, hsl.l);

  return [
    color,
    rgbToHex(rgb1.r, rgb1.g, rgb1.b),
    rgbToHex(rgb2.r, rgb2.g, rgb2.b)
  ];
};

/**
 * Genera colori analoghi
 * @param {string} color - Colore in formato HEX
 * @returns {array} Array con colori analoghi
 */
export const getAnalogous = (color) => {
  const rgb = hexToRgb(color);
  const hsl = rgbToHsl(rgb.r, rgb.g, rgb.b);

  const analogous1 = (hsl.h + 30) % 360;
  const analogous2 = (hsl.h - 30 + 360) % 360;

  const rgb1 = hslToRgb(analogous1, hsl.s, hsl.l);
  const rgb2 = hslToRgb(analogous2, hsl.s, hsl.l);

  return [
    rgbToHex(rgb2.r, rgb2.g, rgb2.b),
    color,
    rgbToHex(rgb1.r, rgb1.g, rgb1.b)
  ];
};

/**
 * Calcola la luminanza relativa di un colore
 * @param {string} color - Colore in formato HEX
 * @returns {number} Luminanza relativa
 */
export const getLuminance = (color) => {
  const rgb = hexToRgb(color);

  const [r, g, b] = [rgb.r, rgb.g, rgb.b].map(val => {
    val = val / 255;
    return val <= 0.03928 ? val / 12.92 : Math.pow((val + 0.055) / 1.055, 2.4);
  });

  return 0.2126 * r + 0.7152 * g + 0.0722 * b;
};

/**
 * Calcola il rapporto di contrasto tra due colori (WCAG)
 * @param {string} color1 - Primo colore in formato HEX
 * @param {string} color2 - Secondo colore in formato HEX
 * @returns {number} Rapporto di contrasto
 */
export const getContrastRatio = (color1, color2) => {
  const lum1 = getLuminance(color1);
  const lum2 = getLuminance(color2);

  const brightest = Math.max(lum1, lum2);
  const darkest = Math.min(lum1, lum2);

  return (brightest + 0.05) / (darkest + 0.05);
};

/**
 * Verifica se il contrasto soddisfa gli standard WCAG
 * @param {string} foreground - Colore del testo
 * @param {string} background - Colore dello sfondo
 * @param {string} level - Livello WCAG ('AA' o 'AAA')
 * @returns {object} Risultati per testo normale e grande
 */
export const checkWCAGContrast = (foreground, background, level = 'AA') => {
  const ratio = getContrastRatio(foreground, background);

  const standards = {
    AA: { normal: 4.5, large: 3 },
    AAA: { normal: 7, large: 4.5 }
  };

  const standard = standards[level];

  return {
    ratio: Math.round(ratio * 100) / 100,
    normalText: ratio >= standard.normal,
    largeText: ratio >= standard.large,
    level,
    recommendation: ratio >= standard.normal ? 'Accessibile' :
                   ratio >= standard.large ? 'Accessibile solo per testo grande' :
                   'Non accessibile'
  };
};

/**
 * Suggerisce il colore del testo (bianco o nero) basato sullo sfondo
 * @param {string} backgroundColor - Colore dello sfondo in formato HEX
 * @returns {string} '#ffffff' o '#000000'
 */
export const getTextColorForBackground = (backgroundColor) => {
  const luminance = getLuminance(backgroundColor);
  return luminance > 0.5 ? '#000000' : '#ffffff';
};

/**
 * Genera variazioni di colore per stati interattivi
 * @param {string} color - Colore base in formato HEX
 * @returns {object} Colori per hover, active, disabled
 */
export const generateInteractiveStates = (color) => {
  const rgb = hexToRgb(color);
  const hsl = rgbToHsl(rgb.r, rgb.g, rgb.b);

  // Hover: più luminoso
  const hoverLightness = Math.min(hsl.l + 10, 100);
  const hoverRgb = hslToRgb(hsl.h, hsl.s, hoverLightness);

  // Active: più scuro
  const activeLightness = Math.max(hsl.l - 10, 0);
  const activeRgb = hslToRgb(hsl.h, hsl.s, activeLightness);

  // Disabled: meno saturato e più chiaro
  const disabledSaturation = Math.max(hsl.s - 50, 0);
  const disabledLightness = Math.min(hsl.l + 30, 90);
  const disabledRgb = hslToRgb(hsl.h, disabledSaturation, disabledLightness);

  return {
    base: color,
    hover: rgbToHex(hoverRgb.r, hoverRgb.g, hoverRgb.b),
    active: rgbToHex(activeRgb.r, activeRgb.g, activeRgb.b),
    disabled: rgbToHex(disabledRgb.r, disabledRgb.g, disabledRgb.b)
  };
};

/**
 * Estrae i colori dominanti da un'immagine (simulato)
 * In produzione, utilizzare una libreria come color-thief
 * @param {string} imageUrl - URL dell'immagine
 * @returns {Promise<array>} Array di colori dominanti
 */
export const extractColorsFromImage = async (imageUrl) => {
  // Simulazione - in produzione usare color-thief o simili
  return new Promise((resolve) => {
    setTimeout(() => {
      resolve([
        '#3B82F6', // Blu
        '#EF4444', // Rosso
        '#10B981', // Verde
        '#F59E0B', // Arancione
        '#8B5CF6'  // Viola
      ]);
    }, 500);
  });
};

/**
 * Genera un tema completo da un colore principale
 * @param {string} primaryColor - Colore principale in formato HEX
 * @returns {object} Tema completo con tutti i colori necessari
 */
export const generateThemeFromColor = (primaryColor) => {
  const primaryPalette = generatePalette(primaryColor);
  const complementary = getComplementary(primaryColor);
  const secondaryPalette = generatePalette(complementary);

  return {
    primary: primaryPalette,
    secondary: secondaryPalette,
    neutral: {
      50: '#fafafa',
      100: '#f5f5f5',
      200: '#e5e5e5',
      300: '#d4d4d4',
      400: '#a3a3a3',
      500: '#737373',
      600: '#525252',
      700: '#404040',
      800: '#262626',
      900: '#171717',
      950: '#0a0a0a'
    },
    semantic: {
      success: '#10b981',
      warning: '#f59e0b',
      error: '#ef4444',
      info: '#3b82f6'
    },
    text: {
      primary: getTextColorForBackground('#ffffff'),
      secondary: '#6b7280',
      disabled: '#9ca3af',
      inverse: getTextColorForBackground(primaryColor)
    }
  };
};

export default {
  hexToRgb,
  rgbToHex,
  rgbToHsl,
  hslToRgb,
  generatePalette,
  getComplementary,
  getTriadic,
  getAnalogous,
  getLuminance,
  getContrastRatio,
  checkWCAGContrast,
  getTextColorForBackground,
  generateInteractiveStates,
  extractColorsFromImage,
  generateThemeFromColor
};