/**
 * Theme Customizer stile WordPress
 * Permette personalizzazione live del tema con preview immediato
 */

import React, { useState, useEffect, useCallback } from 'react';
import { useTheme } from '../../hooks/useTheme';
import ColorPicker from './ColorPicker';
import { generateThemeFromColor } from '../../utils/colors';

/**
 * Customizer con sidebar e preview live
 * @param {object} props - Props del componente
 * @param {React.ReactNode} props.children - Contenuto da visualizzare in preview
 * @param {boolean} props.isOpen - Stato di apertura del customizer
 * @param {Function} props.onClose - Callback per chiusura
 */
const ThemeCustomizer = ({
  children,
  isOpen = false,
  onClose,
  className = ''
}) => {
  const theme = useTheme();

  // Stati locali per modifiche temporanee
  const [tempTheme, setTempTheme] = useState(theme.theme);
  const [activeSection, setActiveSection] = useState('colors');
  const [hasChanges, setHasChanges] = useState(false);

  // Sincronizza con tema principale
  useEffect(() => {
    setTempTheme(theme.theme);
  }, [theme.theme]);

  /**
   * Applica modifiche temporanee per preview
   */
  const updateTempTheme = useCallback((updates) => {
    const newTheme = { ...tempTheme, ...updates };
    setTempTheme(newTheme);
    theme.startPreview(newTheme);
    setHasChanges(true);
  }, [tempTheme, theme]);

  /**
   * Salva le modifiche
   */
  const handleSave = useCallback(() => {
    theme.applyPreview();
    theme.saveTheme();
    setHasChanges(false);
    onClose?.();
  }, [theme, onClose]);

  /**
   * Annulla le modifiche
   */
  const handleCancel = useCallback(() => {
    theme.stopPreview();
    setTempTheme(theme.theme);
    setHasChanges(false);
    onClose?.();
  }, [theme, onClose]);

  /**
   * Sezioni disponibili nel customizer
   */
  const sections = [
    {
      id: 'colors',
      label: 'Colori',
      icon: (
        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
        </svg>
      )
    },
    {
      id: 'typography',
      label: 'Tipografia',
      icon: (
        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
      )
    },
    {
      id: 'layout',
      label: 'Layout',
      icon: (
        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z" />
        </svg>
      )
    },
    {
      id: 'spacing',
      label: 'Spaziatura',
      icon: (
        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7h8m0 0l-4-4m4 4l-4 4m0 6h8m0 0l-4-4m4 4l-4 4" />
        </svg>
      )
    }
  ];

  // Preset di colori rapidi
  const colorPresets = [
    { name: 'Blu Professionale', primary: '#2563eb', secondary: '#64748b' },
    { name: 'Verde Natura', primary: '#16a34a', secondary: '#a16207' },
    { name: 'Viola Creativo', primary: '#a855f7', secondary: '#ec4899' },
    { name: 'Rosso Energia', primary: '#dc2626', secondary: '#f97316' },
    { name: 'Nero Minimal', primary: '#18181b', secondary: '#71717a' }
  ];

  // Font presets
  const fontPresets = [
    { name: 'Moderno', heading: 'Inter', body: 'Inter' },
    { name: 'Classico', heading: 'Georgia', body: 'Times New Roman' },
    { name: 'Tech', heading: 'Roboto', body: 'Roboto' },
    { name: 'Elegante', heading: 'Playfair Display', body: 'Open Sans' },
    { name: 'Pulito', heading: 'Helvetica Neue', body: 'Arial' }
  ];

  if (!isOpen) return null;

  return (
    <div className={`fixed inset-0 z-50 flex ${className}`}>
      {/* Overlay */}
      <div
        className="absolute inset-0 bg-black bg-opacity-50"
        onClick={handleCancel}
      />

      {/* Sidebar Customizer */}
      <div className="relative w-96 bg-white dark:bg-gray-900 shadow-2xl overflow-y-auto">
        {/* Header */}
        <div className="sticky top-0 z-10 bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700 px-6 py-4">
          <div className="flex items-center justify-between">
            <h2 className="text-xl font-semibold text-gray-900 dark:text-white">
              Personalizza Tema
            </h2>
            <button
              onClick={handleCancel}
              className="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
            >
              <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          {/* Sezioni */}
          <div className="flex gap-1 mt-4">
            {sections.map((section) => (
              <button
                key={section.id}
                onClick={() => setActiveSection(section.id)}
                className={`flex-1 flex flex-col items-center gap-1 p-2 rounded-lg transition-colors ${
                  activeSection === section.id
                    ? 'bg-primary-100 dark:bg-primary-900/30 text-primary-600 dark:text-primary-400'
                    : 'hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-600 dark:text-gray-400'
                }`}
              >
                {section.icon}
                <span className="text-xs">{section.label}</span>
              </button>
            ))}
          </div>
        </div>

        {/* Contenuto Sezioni */}
        <div className="p-6">
          {/* Sezione Colori */}
          {activeSection === 'colors' && (
            <div className="space-y-6">
              {/* Preset Colori */}
              <div>
                <h3 className="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                  Preset Colori
                </h3>
                <div className="grid grid-cols-2 gap-2">
                  {colorPresets.map((preset) => (
                    <button
                      key={preset.name}
                      onClick={() => updateTempTheme({
                        primary: preset.primary,
                        secondary: preset.secondary
                      })}
                      className="flex items-center gap-2 p-2 border border-gray-200 dark:border-gray-700 rounded-lg hover:border-primary-500 transition-colors"
                    >
                      <div
                        className="w-6 h-6 rounded"
                        style={{ backgroundColor: preset.primary }}
                      />
                      <div
                        className="w-6 h-6 rounded"
                        style={{ backgroundColor: preset.secondary }}
                      />
                      <span className="text-xs text-gray-600 dark:text-gray-400">
                        {preset.name}
                      </span>
                    </button>
                  ))}
                </div>
              </div>

              {/* Colore Primario */}
              <ColorPicker
                label="Colore Primario"
                value={tempTheme?.primary || '#3B82F6'}
                onChange={(color) => updateTempTheme({ primary: color })}
                showPalette={false}
                showHarmony={false}
                showContrast={true}
              />

              {/* Colore Secondario */}
              <ColorPicker
                label="Colore Secondario"
                value={tempTheme?.secondary || '#64748B'}
                onChange={(color) => updateTempTheme({ secondary: color })}
                showPalette={false}
                showHarmony={false}
                showContrast={true}
              />

              {/* Genera da Primario */}
              <button
                onClick={() => {
                  const generated = generateThemeFromColor(tempTheme?.primary || '#3B82F6');
                  updateTempTheme(generated);
                }}
                className="w-full px-4 py-2 bg-gradient-to-r from-primary-600 to-primary-700 text-white rounded-lg hover:from-primary-700 hover:to-primary-800 transition-colors"
              >
                Genera Palette Automatica
              </button>
            </div>
          )}

          {/* Sezione Tipografia */}
          {activeSection === 'typography' && (
            <div className="space-y-6">
              {/* Preset Font */}
              <div>
                <h3 className="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                  Combinazioni Font
                </h3>
                <div className="space-y-2">
                  {fontPresets.map((preset) => (
                    <button
                      key={preset.name}
                      onClick={() => updateTempTheme({
                        font: {
                          heading: preset.heading,
                          body: preset.body
                        }
                      })}
                      className="w-full p-3 border border-gray-200 dark:border-gray-700 rounded-lg hover:border-primary-500 transition-colors text-left"
                    >
                      <p
                        className="font-semibold text-gray-900 dark:text-white"
                        style={{ fontFamily: preset.heading }}
                      >
                        {preset.name}
                      </p>
                      <p
                        className="text-sm text-gray-600 dark:text-gray-400"
                        style={{ fontFamily: preset.body }}
                      >
                        {preset.heading} / {preset.body}
                      </p>
                    </button>
                  ))}
                </div>
              </div>

              {/* Dimensione Font Base */}
              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Dimensione Font Base
                </label>
                <input
                  type="range"
                  min="14"
                  max="20"
                  value={tempTheme?.fontSize?.base || 16}
                  onChange={(e) => updateTempTheme({
                    fontSize: { ...tempTheme?.fontSize, base: parseInt(e.target.value) }
                  })}
                  className="w-full"
                />
                <div className="flex justify-between text-xs text-gray-500">
                  <span>14px</span>
                  <span>{tempTheme?.fontSize?.base || 16}px</span>
                  <span>20px</span>
                </div>
              </div>

              {/* Line Height */}
              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Altezza Linea
                </label>
                <select
                  value={tempTheme?.lineHeight || 'normal'}
                  onChange={(e) => updateTempTheme({ lineHeight: e.target.value })}
                  className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800"
                >
                  <option value="tight">Compatta</option>
                  <option value="normal">Normale</option>
                  <option value="relaxed">Rilassata</option>
                  <option value="loose">Ampia</option>
                </select>
              </div>
            </div>
          )}

          {/* Sezione Layout */}
          {activeSection === 'layout' && (
            <div className="space-y-6">
              {/* Container Width */}
              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Larghezza Container
                </label>
                <select
                  value={tempTheme?.container?.width || 'default'}
                  onChange={(e) => updateTempTheme({
                    container: { ...tempTheme?.container, width: e.target.value }
                  })}
                  className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800"
                >
                  <option value="sm">Piccolo (640px)</option>
                  <option value="md">Medio (768px)</option>
                  <option value="lg">Grande (1024px)</option>
                  <option value="xl">Extra Large (1280px)</option>
                  <option value="2xl">2X Large (1536px)</option>
                  <option value="full">Piena larghezza</option>
                </select>
              </div>

              {/* Sidebar Position */}
              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Posizione Sidebar
                </label>
                <div className="grid grid-cols-3 gap-2">
                  {['left', 'right', 'hidden'].map((position) => (
                    <button
                      key={position}
                      onClick={() => updateTempTheme({
                        layout: { ...tempTheme?.layout, sidebar: position }
                      })}
                      className={`p-2 border rounded-lg transition-colors ${
                        tempTheme?.layout?.sidebar === position
                          ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20'
                          : 'border-gray-300 dark:border-gray-600 hover:border-primary-300'
                      }`}
                    >
                      <span className="text-sm capitalize">{position === 'hidden' ? 'Nascosta' : position}</span>
                    </button>
                  ))}
                </div>
              </div>

              {/* Border Radius */}
              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Raggio Bordi
                </label>
                <input
                  type="range"
                  min="0"
                  max="24"
                  value={parseInt(tempTheme?.borderRadius) || 8}
                  onChange={(e) => updateTempTheme({ borderRadius: `${e.target.value}px` })}
                  className="w-full"
                />
                <div className="flex justify-between text-xs text-gray-500">
                  <span>0px</span>
                  <span>{tempTheme?.borderRadius || '8px'}</span>
                  <span>24px</span>
                </div>
                <div
                  className="mt-2 h-16 bg-primary-500 dark:bg-primary-600"
                  style={{ borderRadius: tempTheme?.borderRadius || '8px' }}
                />
              </div>
            </div>
          )}

          {/* Sezione Spaziatura */}
          {activeSection === 'spacing' && (
            <div className="space-y-6">
              {/* Spacing Scale */}
              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Scala Spaziatura
                </label>
                <select
                  value={tempTheme?.spacing?.scale || '1'}
                  onChange={(e) => updateTempTheme({
                    spacing: { ...tempTheme?.spacing, scale: e.target.value }
                  })}
                  className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800"
                >
                  <option value="0.75">Compatta (0.75x)</option>
                  <option value="1">Normale (1x)</option>
                  <option value="1.25">Confortevole (1.25x)</option>
                  <option value="1.5">Spaziosa (1.5x)</option>
                </select>
              </div>

              {/* Padding Components */}
              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Padding Componenti
                </label>
                <div className="grid grid-cols-4 gap-2">
                  {['xs', 'sm', 'md', 'lg'].map((size) => (
                    <button
                      key={size}
                      onClick={() => updateTempTheme({
                        componentPadding: size
                      })}
                      className={`p-2 border rounded-lg transition-colors ${
                        tempTheme?.componentPadding === size
                          ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20'
                          : 'border-gray-300 dark:border-gray-600 hover:border-primary-300'
                      }`}
                    >
                      <span className="text-sm uppercase">{size}</span>
                    </button>
                  ))}
                </div>
              </div>

              {/* Gap Elements */}
              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Spazio tra Elementi
                </label>
                <input
                  type="range"
                  min="4"
                  max="32"
                  step="4"
                  value={tempTheme?.gap || 16}
                  onChange={(e) => updateTempTheme({ gap: parseInt(e.target.value) })}
                  className="w-full"
                />
                <div className="flex justify-between text-xs text-gray-500">
                  <span>4px</span>
                  <span>{tempTheme?.gap || 16}px</span>
                  <span>32px</span>
                </div>
              </div>
            </div>
          )}
        </div>

        {/* Footer con azioni */}
        <div className="sticky bottom-0 bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700 px-6 py-4">
          <div className="flex gap-2">
            <button
              onClick={handleCancel}
              className="flex-1 px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors"
            >
              Annulla
            </button>
            <button
              onClick={handleSave}
              disabled={!hasChanges}
              className="flex-1 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              Applica Modifiche
            </button>
          </div>

          {hasChanges && (
            <p className="mt-2 text-xs text-center text-yellow-600 dark:text-yellow-400">
              Hai modifiche non salvate
            </p>
          )}
        </div>
      </div>

      {/* Area Preview */}
      <div className="flex-1 relative overflow-auto">
        <div className="absolute top-4 right-4 z-10 bg-white dark:bg-gray-900 rounded-lg shadow-lg px-3 py-2">
          <span className="text-sm font-medium text-gray-700 dark:text-gray-300">
            Preview Live
          </span>
        </div>

        {/* Contenuto in preview */}
        <div className="p-8">
          {children}
        </div>
      </div>
    </div>
  );
};

export default ThemeCustomizer;