/**
 * Componente ColorPicker avanzato con supporto per palette e verifica accessibilità
 */

import React, { useState, useEffect, useCallback } from 'react';
import {
  hexToRgb,
  rgbToHex,
  rgbToHsl,
  generatePalette,
  getComplementary,
  getTriadic,
  getAnalogous,
  checkWCAGContrast,
  getTextColorForBackground
} from '../../utils/colors';

/**
 * ColorPicker avanzato con funzionalità complete
 * @param {object} props - Props del componente
 * @param {string} props.value - Valore corrente del colore
 * @param {Function} props.onChange - Callback per cambio colore
 * @param {string} props.label - Etichetta del campo
 * @param {boolean} props.showPalette - Mostra palette generata
 * @param {boolean} props.showHarmony - Mostra suggerimenti armonia colori
 * @param {boolean} props.showContrast - Mostra verifica contrasto
 * @param {string} props.contrastWith - Colore per verifica contrasto
 */
const ColorPicker = ({
  value = '#3B82F6',
  onChange,
  label = 'Seleziona colore',
  showPalette = true,
  showHarmony = true,
  showContrast = true,
  contrastWith = '#ffffff',
  className = ''
}) => {
  // Stati locali
  const [color, setColor] = useState(value);
  const [rgb, setRgb] = useState(hexToRgb(value));
  const [hsl, setHsl] = useState(null);
  const [palette, setPalette] = useState(null);
  const [harmony, setHarmony] = useState(null);
  const [contrast, setContrast] = useState(null);
  const [inputMode, setInputMode] = useState('hex'); // hex, rgb, hsl
  const [isPickerOpen, setIsPickerOpen] = useState(false);

  // Colori predefiniti popolari
  const presetColors = [
    '#000000', '#FFFFFF', '#FF0000', '#00FF00', '#0000FF',
    '#FFFF00', '#00FFFF', '#FF00FF', '#C0C0C0', '#808080',
    '#800000', '#808000', '#008000', '#800080', '#008080',
    '#000080', '#FFA500', '#A52A2A', '#DEB887', '#5F9EA0',
    '#7FFF00', '#D2691E', '#FF7F50', '#6495ED', '#DC143C'
  ];

  /**
   * Aggiorna tutti i valori derivati quando cambia il colore
   */
  useEffect(() => {
    if (color && color !== value) {
      const rgbVal = hexToRgb(color);
      setRgb(rgbVal);

      if (rgbVal) {
        const hslVal = rgbToHsl(rgbVal.r, rgbVal.g, rgbVal.b);
        setHsl(hslVal);

        if (showPalette) {
          setPalette(generatePalette(color));
        }

        if (showHarmony) {
          setHarmony({
            complementary: getComplementary(color),
            triadic: getTriadic(color),
            analogous: getAnalogous(color)
          });
        }

        if (showContrast) {
          setContrast(checkWCAGContrast(color, contrastWith));
        }
      }

      // Notifica il cambio al parent
      if (onChange) {
        onChange(color);
      }
    }
  }, [color, value, showPalette, showHarmony, showContrast, contrastWith, onChange]);

  /**
   * Gestisce il cambio dal color input nativo
   */
  const handleColorChange = useCallback((e) => {
    setColor(e.target.value);
  }, []);

  /**
   * Gestisce il cambio dall'input testuale HEX
   */
  const handleHexInput = useCallback((e) => {
    let value = e.target.value;
    if (!value.startsWith('#')) {
      value = '#' + value;
    }
    if (/^#[0-9A-Fa-f]{0,6}$/.test(value)) {
      setColor(value);
    }
  }, []);

  /**
   * Gestisce il cambio dall'input RGB
   */
  const handleRgbInput = useCallback((component, value) => {
    const newRgb = { ...rgb, [component]: parseInt(value) || 0 };
    if (newRgb.r >= 0 && newRgb.r <= 255 &&
        newRgb.g >= 0 && newRgb.g <= 255 &&
        newRgb.b >= 0 && newRgb.b <= 255) {
      const hex = rgbToHex(newRgb.r, newRgb.g, newRgb.b);
      setColor(hex);
    }
  }, [rgb]);

  /**
   * Seleziona un colore dalla palette o preset
   */
  const selectColor = useCallback((newColor) => {
    setColor(newColor);
    setIsPickerOpen(false);
  }, []);

  /**
   * Copia il colore negli appunti
   */
  const copyToClipboard = useCallback(() => {
    navigator.clipboard.writeText(color);
    // Potresti aggiungere un toast notification qui
  }, [color]);

  return (
    <div className={`color-picker-container ${className}`}>
      {/* Header con label */}
      <div className="mb-2">
        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
          {label}
        </label>
      </div>

      {/* Input principale */}
      <div className="flex gap-2 mb-4">
        {/* Color preview e picker */}
        <div className="relative">
          <button
            onClick={() => setIsPickerOpen(!isPickerOpen)}
            className="w-12 h-12 rounded-lg border-2 border-gray-300 dark:border-gray-600 shadow-sm hover:shadow-md transition-shadow cursor-pointer"
            style={{ backgroundColor: color }}
            aria-label="Apri selettore colore"
          />

          {/* Native color picker (nascosto) */}
          <input
            type="color"
            value={color}
            onChange={handleColorChange}
            className="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
          />
        </div>

        {/* Input testuale */}
        <div className="flex-1">
          <div className="flex rounded-lg border border-gray-300 dark:border-gray-600 overflow-hidden">
            {/* Selettore modalità input */}
            <select
              value={inputMode}
              onChange={(e) => setInputMode(e.target.value)}
              className="px-2 py-2 bg-gray-50 dark:bg-gray-800 border-r border-gray-300 dark:border-gray-600 text-sm"
            >
              <option value="hex">HEX</option>
              <option value="rgb">RGB</option>
              <option value="hsl">HSL</option>
            </select>

            {/* Input basato sulla modalità */}
            {inputMode === 'hex' && (
              <input
                type="text"
                value={color}
                onChange={handleHexInput}
                className="flex-1 px-3 py-2 bg-white dark:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-primary-500"
                placeholder="#000000"
              />
            )}

            {inputMode === 'rgb' && rgb && (
              <div className="flex-1 flex">
                <input
                  type="number"
                  value={rgb.r}
                  onChange={(e) => handleRgbInput('r', e.target.value)}
                  className="w-1/3 px-2 py-2 bg-white dark:bg-gray-900 text-center focus:outline-none"
                  min="0"
                  max="255"
                  placeholder="R"
                />
                <input
                  type="number"
                  value={rgb.g}
                  onChange={(e) => handleRgbInput('g', e.target.value)}
                  className="w-1/3 px-2 py-2 bg-white dark:bg-gray-900 text-center border-x border-gray-200 dark:border-gray-700 focus:outline-none"
                  min="0"
                  max="255"
                  placeholder="G"
                />
                <input
                  type="number"
                  value={rgb.b}
                  onChange={(e) => handleRgbInput('b', e.target.value)}
                  className="w-1/3 px-2 py-2 bg-white dark:bg-gray-900 text-center focus:outline-none"
                  min="0"
                  max="255"
                  placeholder="B"
                />
              </div>
            )}

            {inputMode === 'hsl' && hsl && (
              <div className="flex-1 px-3 py-2 bg-white dark:bg-gray-900 text-sm">
                H: {hsl.h}° S: {hsl.s}% L: {hsl.l}%
              </div>
            )}

            {/* Bottone copia */}
            <button
              onClick={copyToClipboard}
              className="px-3 py-2 bg-gray-50 dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-700 border-l border-gray-300 dark:border-gray-600"
              title="Copia colore"
            >
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
              </svg>
            </button>
          </div>
        </div>
      </div>

      {/* Colori preset */}
      {isPickerOpen && (
        <div className="mb-4 p-4 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
          <p className="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Colori predefiniti</p>
          <div className="grid grid-cols-10 gap-1">
            {presetColors.map((presetColor) => (
              <button
                key={presetColor}
                onClick={() => selectColor(presetColor)}
                className="w-8 h-8 rounded border-2 border-gray-300 dark:border-gray-600 hover:scale-110 transition-transform"
                style={{ backgroundColor: presetColor }}
                title={presetColor}
              />
            ))}
          </div>
        </div>
      )}

      {/* Palette generata */}
      {showPalette && palette && (
        <div className="mb-4">
          <p className="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Palette generata</p>
          <div className="flex gap-1">
            {Object.entries(palette).map(([key, paletteColor]) => (
              <button
                key={key}
                onClick={() => selectColor(paletteColor)}
                className="flex-1 h-10 rounded hover:scale-105 transition-transform relative group"
                style={{ backgroundColor: paletteColor }}
                title={`${key}: ${paletteColor}`}
              >
                <span className="absolute bottom-0 left-0 right-0 bg-black bg-opacity-50 text-white text-xs py-0.5 opacity-0 group-hover:opacity-100 transition-opacity">
                  {key}
                </span>
              </button>
            ))}
          </div>
        </div>
      )}

      {/* Armonia colori */}
      {showHarmony && harmony && (
        <div className="mb-4">
          <p className="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Armonia colori</p>

          {/* Complementare */}
          <div className="mb-2">
            <p className="text-xs text-gray-600 dark:text-gray-400 mb-1">Complementare</p>
            <div className="flex gap-2">
              <button
                onClick={() => selectColor(color)}
                className="w-12 h-12 rounded border-2 border-gray-300 dark:border-gray-600"
                style={{ backgroundColor: color }}
              />
              <button
                onClick={() => selectColor(harmony.complementary)}
                className="w-12 h-12 rounded border-2 border-gray-300 dark:border-gray-600 hover:scale-110 transition-transform"
                style={{ backgroundColor: harmony.complementary }}
              />
            </div>
          </div>

          {/* Triadica */}
          <div className="mb-2">
            <p className="text-xs text-gray-600 dark:text-gray-400 mb-1">Triadica</p>
            <div className="flex gap-2">
              {harmony.triadic.map((triadicColor, index) => (
                <button
                  key={index}
                  onClick={() => selectColor(triadicColor)}
                  className="w-12 h-12 rounded border-2 border-gray-300 dark:border-gray-600 hover:scale-110 transition-transform"
                  style={{ backgroundColor: triadicColor }}
                />
              ))}
            </div>
          </div>

          {/* Analoga */}
          <div className="mb-2">
            <p className="text-xs text-gray-600 dark:text-gray-400 mb-1">Analoga</p>
            <div className="flex gap-2">
              {harmony.analogous.map((analogousColor, index) => (
                <button
                  key={index}
                  onClick={() => selectColor(analogousColor)}
                  className="w-12 h-12 rounded border-2 border-gray-300 dark:border-gray-600 hover:scale-110 transition-transform"
                  style={{ backgroundColor: analogousColor }}
                />
              ))}
            </div>
          </div>
        </div>
      )}

      {/* Verifica contrasto WCAG */}
      {showContrast && contrast && (
        <div className="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
          <div className="flex items-center justify-between mb-2">
            <p className="text-sm font-medium text-gray-700 dark:text-gray-300">
              Contrasto WCAG
            </p>
            <span className={`text-sm font-bold ${
              contrast.normalText ? 'text-green-600' : 'text-red-600'
            }`}>
              {contrast.ratio}:1
            </span>
          </div>

          <div className="flex gap-4 text-xs">
            <div className="flex items-center gap-1">
              <span className={`w-3 h-3 rounded-full ${
                contrast.normalText ? 'bg-green-500' : 'bg-red-500'
              }`} />
              <span>Testo normale</span>
            </div>
            <div className="flex items-center gap-1">
              <span className={`w-3 h-3 rounded-full ${
                contrast.largeText ? 'bg-green-500' : 'bg-red-500'
              }`} />
              <span>Testo grande</span>
            </div>
          </div>

          <p className="text-xs text-gray-600 dark:text-gray-400 mt-2">
            {contrast.recommendation}
          </p>

          {/* Preview testo */}
          <div
            className="mt-2 p-2 rounded text-center"
            style={{
              backgroundColor: contrastWith,
              color: color
            }}
          >
            Testo di esempio
          </div>
        </div>
      )}
    </div>
  );
};

export default ColorPicker;