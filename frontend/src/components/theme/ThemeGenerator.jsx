/**
 * Generatore di temi automatico basato su immagini o colori
 * Estrae palette e genera temi completi
 */

import React, { useState, useRef, useCallback } from 'react';
import { useTheme } from '../../hooks/useTheme';
import {
  generatePalette,
  generateThemeFromColor,
  extractColorsFromImage,
  getComplementary,
  getTriadic,
  getAnalogous
} from '../../utils/colors';
import { generateThemeFromLogo } from '../../utils/theme';

/**
 * Generatore automatico di temi
 */
const ThemeGenerator = ({ className = '', onThemeGenerated }) => {
  const theme = useTheme();
  const [generationMethod, setGenerationMethod] = useState('color'); // color, image, ai
  const [isGenerating, setIsGenerating] = useState(false);
  const [generatedTheme, setGeneratedTheme] = useState(null);
  const [selectedColor, setSelectedColor] = useState('#3B82F6');
  const [uploadedImage, setUploadedImage] = useState(null);
  const [extractedColors, setExtractedColors] = useState([]);
  const [selectedHarmony, setSelectedHarmony] = useState('complementary');

  const imageInputRef = useRef(null);

  /**
   * Genera tema da colore singolo
   */
  const generateFromColor = useCallback(async () => {
    setIsGenerating(true);
    try {
      const newTheme = generateThemeFromColor(selectedColor);

      // Aggiungi armonie di colore basate sulla selezione
      let harmonyColors = [];
      switch (selectedHarmony) {
        case 'complementary':
          harmonyColors = [selectedColor, getComplementary(selectedColor)];
          break;
        case 'triadic':
          harmonyColors = getTriadic(selectedColor);
          break;
        case 'analogous':
          harmonyColors = getAnalogous(selectedColor);
          break;
        case 'monochromatic':
          harmonyColors = [selectedColor];
          break;
        default:
          harmonyColors = [selectedColor];
      }

      // Applica armonia al tema
      if (harmonyColors.length > 1) {
        newTheme.secondary = generatePalette(harmonyColors[1]);
      }
      if (harmonyColors.length > 2) {
        newTheme.accent = harmonyColors[2];
      }

      setGeneratedTheme(newTheme);

      // Callback opzionale
      if (onThemeGenerated) {
        onThemeGenerated(newTheme);
      }
    } catch (error) {
      console.error('Errore nella generazione del tema:', error);
    } finally {
      setIsGenerating(false);
    }
  }, [selectedColor, selectedHarmony, onThemeGenerated]);

  /**
   * Gestisce upload immagine
   */
  const handleImageUpload = useCallback((e) => {
    const file = e.target.files[0];
    if (file) {
      const reader = new FileReader();
      reader.onload = (event) => {
        setUploadedImage(event.target.result);
        // Estrai colori dall'immagine
        extractColorsFromUploadedImage(file);
      };
      reader.readAsDataURL(file);
    }
  }, []);

  /**
   * Estrae colori dall'immagine caricata
   */
  const extractColorsFromUploadedImage = useCallback(async (file) => {
    setIsGenerating(true);
    try {
      const colors = await extractColorsFromImage(file);
      setExtractedColors(colors);

      // Genera tema automaticamente dal primo colore estratto
      if (colors.length > 0) {
        const newTheme = await generateThemeFromLogo(file);
        setGeneratedTheme(newTheme);

        if (onThemeGenerated) {
          onThemeGenerated(newTheme);
        }
      }
    } catch (error) {
      console.error('Errore nell\'estrazione dei colori:', error);
    } finally {
      setIsGenerating(false);
    }
  }, [onThemeGenerated]);

  /**
   * Genera tema con AI (simulato)
   */
  const generateWithAI = useCallback(async (prompt) => {
    setIsGenerating(true);
    try {
      // Simulazione generazione AI basata su prompt
      // In produzione, questo chiamerebbe un servizio AI reale
      const aiThemes = {
        'professionale': {
          primary: generatePalette('#2563eb'),
          secondary: generatePalette('#64748b'),
          mood: 'Professionale e affidabile'
        },
        'creativo': {
          primary: generatePalette('#a855f7'),
          secondary: generatePalette('#ec4899'),
          mood: 'Creativo e innovativo'
        },
        'naturale': {
          primary: generatePalette('#16a34a'),
          secondary: generatePalette('#a16207'),
          mood: 'Naturale e organico'
        },
        'energetico': {
          primary: generatePalette('#ef4444'),
          secondary: generatePalette('#f59e0b'),
          mood: 'Energetico e dinamico'
        },
        'elegante': {
          primary: generatePalette('#18181b'),
          secondary: generatePalette('#71717a'),
          mood: 'Elegante e sofisticato'
        }
      };

      // Seleziona tema basato su keywords nel prompt
      let selectedTheme = aiThemes.professionale;
      const lowerPrompt = prompt.toLowerCase();

      if (lowerPrompt.includes('creativ') || lowerPrompt.includes('innovativ')) {
        selectedTheme = aiThemes.creativo;
      } else if (lowerPrompt.includes('natur') || lowerPrompt.includes('organic')) {
        selectedTheme = aiThemes.naturale;
      } else if (lowerPrompt.includes('energ') || lowerPrompt.includes('dinamic')) {
        selectedTheme = aiThemes.energetico;
      } else if (lowerPrompt.includes('elegant') || lowerPrompt.includes('sofisticat')) {
        selectedTheme = aiThemes.elegante;
      }

      setGeneratedTheme(selectedTheme);

      if (onThemeGenerated) {
        onThemeGenerated(selectedTheme);
      }
    } catch (error) {
      console.error('Errore nella generazione AI:', error);
    } finally {
      setIsGenerating(false);
    }
  }, [onThemeGenerated]);

  /**
   * Applica il tema generato
   */
  const applyGeneratedTheme = useCallback(() => {
    if (generatedTheme) {
      theme.updateTheme(generatedTheme);
      // Notifica successo
      alert('Tema applicato con successo!');
    }
  }, [generatedTheme, theme]);

  /**
   * Reset generatore
   */
  const resetGenerator = useCallback(() => {
    setGeneratedTheme(null);
    setUploadedImage(null);
    setExtractedColors([]);
    setSelectedColor('#3B82F6');
  }, []);

  return (
    <div className={`theme-generator bg-white dark:bg-gray-900 rounded-lg shadow-lg p-6 ${className}`}>
      {/* Header */}
      <div className="mb-6">
        <h2 className="text-xl font-semibold text-gray-900 dark:text-white mb-2">
          Generatore di Temi
        </h2>
        <p className="text-gray-600 dark:text-gray-400">
          Crea automaticamente temi personalizzati da colori, immagini o descrizioni
        </p>
      </div>

      {/* Metodo di generazione */}
      <div className="mb-6">
        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
          Metodo di Generazione
        </label>
        <div className="grid grid-cols-3 gap-2">
          <button
            onClick={() => setGenerationMethod('color')}
            className={`p-3 rounded-lg border-2 transition-colors ${
              generationMethod === 'color'
                ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20'
                : 'border-gray-300 dark:border-gray-600 hover:border-primary-300'
            }`}
          >
            <svg className="w-6 h-6 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
            </svg>
            <span className="text-xs">Da Colore</span>
          </button>

          <button
            onClick={() => setGenerationMethod('image')}
            className={`p-3 rounded-lg border-2 transition-colors ${
              generationMethod === 'image'
                ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20'
                : 'border-gray-300 dark:border-gray-600 hover:border-primary-300'
            }`}
          >
            <svg className="w-6 h-6 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            <span className="text-xs">Da Immagine</span>
          </button>

          <button
            onClick={() => setGenerationMethod('ai')}
            className={`p-3 rounded-lg border-2 transition-colors ${
              generationMethod === 'ai'
                ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20'
                : 'border-gray-300 dark:border-gray-600 hover:border-primary-300'
            }`}
          >
            <svg className="w-6 h-6 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
            </svg>
            <span className="text-xs">Con AI</span>
          </button>
        </div>
      </div>

      {/* Contenuto basato sul metodo */}
      <div className="mb-6">
        {/* Generazione da colore */}
        {generationMethod === 'color' && (
          <div className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Colore Base
              </label>
              <div className="flex gap-2">
                <input
                  type="color"
                  value={selectedColor}
                  onChange={(e) => setSelectedColor(e.target.value)}
                  className="h-12 w-20 rounded cursor-pointer"
                />
                <input
                  type="text"
                  value={selectedColor}
                  onChange={(e) => setSelectedColor(e.target.value)}
                  className="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800"
                  placeholder="#000000"
                />
              </div>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Schema Colori
              </label>
              <select
                value={selectedHarmony}
                onChange={(e) => setSelectedHarmony(e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800"
              >
                <option value="monochromatic">Monocromatico</option>
                <option value="complementary">Complementare</option>
                <option value="triadic">Triadico</option>
                <option value="analogous">Analogo</option>
              </select>
            </div>

            <button
              onClick={generateFromColor}
              disabled={isGenerating}
              className="w-full px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              {isGenerating ? 'Generazione...' : 'Genera Tema'}
            </button>
          </div>
        )}

        {/* Generazione da immagine */}
        {generationMethod === 'image' && (
          <div className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Carica Immagine
              </label>
              <input
                ref={imageInputRef}
                type="file"
                accept="image/*"
                onChange={handleImageUpload}
                className="hidden"
              />

              {uploadedImage ? (
                <div className="relative">
                  <img
                    src={uploadedImage}
                    alt="Uploaded"
                    className="w-full h-48 object-cover rounded-lg"
                  />
                  <button
                    onClick={() => {
                      setUploadedImage(null);
                      setExtractedColors([]);
                    }}
                    className="absolute top-2 right-2 p-1 bg-red-600 text-white rounded-full hover:bg-red-700"
                  >
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </button>
                </div>
              ) : (
                <button
                  onClick={() => imageInputRef.current?.click()}
                  className="w-full h-48 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg hover:border-primary-500 transition-colors flex flex-col items-center justify-center"
                >
                  <svg className="w-12 h-12 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                  </svg>
                  <span className="text-sm text-gray-600 dark:text-gray-400">
                    Clicca per caricare un'immagine
                  </span>
                </button>
              )}
            </div>

            {/* Colori estratti */}
            {extractedColors.length > 0 && (
              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Colori Estratti
                </label>
                <div className="flex gap-2">
                  {extractedColors.map((color, index) => (
                    <button
                      key={index}
                      onClick={() => setSelectedColor(color)}
                      className="w-12 h-12 rounded-lg border-2 border-gray-300 dark:border-gray-600 hover:scale-110 transition-transform"
                      style={{ backgroundColor: color }}
                      title={color}
                    />
                  ))}
                </div>
              </div>
            )}
          </div>
        )}

        {/* Generazione con AI */}
        {generationMethod === 'ai' && (
          <div className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Descrivi il tema desiderato
              </label>
              <textarea
                id="ai-prompt"
                className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800"
                rows="4"
                placeholder="Es: Un tema professionale e moderno per un'azienda tech, con colori che trasmettono innovazione e affidabilità..."
              />
            </div>

            <div className="grid grid-cols-2 gap-2">
              {['Professionale', 'Creativo', 'Naturale', 'Energetico', 'Elegante'].map((mood) => (
                <button
                  key={mood}
                  onClick={() => {
                    const textarea = document.getElementById('ai-prompt');
                    textarea.value = `Un tema ${mood.toLowerCase()} e moderno`;
                    generateWithAI(textarea.value);
                  }}
                  className="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg hover:border-primary-500 transition-colors text-sm"
                >
                  {mood}
                </button>
              ))}
            </div>

            <button
              onClick={() => {
                const prompt = document.getElementById('ai-prompt').value;
                if (prompt) generateWithAI(prompt);
              }}
              disabled={isGenerating}
              className="w-full px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              {isGenerating ? 'Generazione con AI...' : 'Genera con AI'}
            </button>
          </div>
        )}
      </div>

      {/* Preview tema generato */}
      {generatedTheme && (
        <div className="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
          <h3 className="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
            Tema Generato
          </h3>

          {/* Preview colori */}
          <div className="mb-4">
            <div className="grid grid-cols-5 gap-2">
              {generatedTheme.primary && Object.entries(generatedTheme.primary).slice(0, 5).map(([key, color]) => (
                <div key={key} className="text-center">
                  <div
                    className="w-full h-16 rounded-lg mb-1"
                    style={{ backgroundColor: color }}
                  />
                  <span className="text-xs text-gray-600 dark:text-gray-400">
                    Primary {key}
                  </span>
                </div>
              ))}
            </div>
          </div>

          {/* Mood del tema (se generato con AI) */}
          {generatedTheme.mood && (
            <div className="mb-4 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
              <p className="text-sm text-gray-700 dark:text-gray-300">
                <span className="font-medium">Mood:</span> {generatedTheme.mood}
              </p>
            </div>
          )}

          {/* Azioni */}
          <div className="flex gap-2">
            <button
              onClick={applyGeneratedTheme}
              className="flex-1 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors"
            >
              Applica Tema
            </button>
            <button
              onClick={() => theme.startPreview(generatedTheme)}
              className="flex-1 px-4 py-2 border border-primary-600 text-primary-600 dark:text-primary-400 rounded-lg hover:bg-primary-50 dark:hover:bg-primary-900/20 transition-colors"
            >
              Preview
            </button>
            <button
              onClick={resetGenerator}
              className="px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors"
            >
              Reset
            </button>
          </div>
        </div>
      )}
    </div>
  );
};

export default ThemeGenerator;