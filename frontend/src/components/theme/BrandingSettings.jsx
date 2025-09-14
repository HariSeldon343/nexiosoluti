/**
 * Pannello di amministrazione per la personalizzazione del branding
 * Gestisce logo, colori, font e altre impostazioni del tenant
 */

import React, { useState, useRef, useCallback } from 'react';
import { useTheme } from '../../hooks/useTheme';
import { useBranding } from '../../hooks/useBranding';
import ColorPicker from './ColorPicker';
import { generateThemeFromLogo, downloadThemeAsCSS, downloadThemeAsJSON } from '../../utils/theme';

/**
 * Pannello completo per le impostazioni di branding
 * @param {object} props - Props del componente
 * @param {string} props.tenantId - ID del tenant
 * @param {Function} props.onSave - Callback per salvataggio
 */
const BrandingSettings = ({
  tenantId = 'default',
  onSave,
  className = ''
}) => {
  // Hooks per tema e branding
  const theme = useTheme();
  const branding = useBranding(tenantId);

  // Stati locali
  const [activeTab, setActiveTab] = useState('general');
  const [previewMode, setPreviewMode] = useState(false);
  const [showResetConfirm, setShowResetConfirm] = useState(false);

  // Refs per file input
  const logoInputRef = useRef(null);
  const logoDarkInputRef = useRef(null);
  const faviconInputRef = useRef(null);
  const themeImportRef = useRef(null);
  const brandingImportRef = useRef(null);

  // Font disponibili
  const availableFonts = [
    { value: 'Inter', label: 'Inter' },
    { value: 'Roboto', label: 'Roboto' },
    { value: 'Open Sans', label: 'Open Sans' },
    { value: 'Montserrat', label: 'Montserrat' },
    { value: 'Poppins', label: 'Poppins' },
    { value: 'Playfair Display', label: 'Playfair Display' },
    { value: 'Source Sans Pro', label: 'Source Sans Pro' },
    { value: 'Helvetica Neue', label: 'Helvetica Neue' },
    { value: 'Arial', label: 'Arial' },
    { value: 'Georgia', label: 'Georgia' }
  ];

  /**
   * Gestisce upload del logo
   */
  const handleLogoUpload = useCallback(async (e, type = 'logo') => {
    const file = e.target.files[0];
    if (file) {
      const success = await branding.uploadImage(file, type);

      if (success && type === 'logo' && theme.theme) {
        // Genera tema dal logo se richiesto
        const shouldGenerate = window.confirm(
          'Vuoi generare automaticamente un tema basato sul logo caricato?'
        );

        if (shouldGenerate) {
          try {
            const generatedTheme = await generateThemeFromLogo(file);
            theme.updateTheme(generatedTheme);
          } catch (error) {
            console.error('Errore nella generazione del tema:', error);
          }
        }
      }
    }
  }, [branding, theme]);

  /**
   * Gestisce il salvataggio di tutte le impostazioni
   */
  const handleSave = useCallback(async () => {
    try {
      // Salva tema
      await theme.saveTheme();

      // Callback esterno se fornito
      if (onSave) {
        await onSave({
          theme: theme.theme,
          branding: {
            logo: branding.logo,
            logoDark: branding.logoDark,
            favicon: branding.favicon,
            companyName: branding.companyName,
            tagline: branding.tagline,
            seoMetadata: branding.seoMetadata
          }
        });
      }

      // Mostra notifica di successo (potresti usare un toast)
      alert('Impostazioni salvate con successo!');
    } catch (error) {
      console.error('Errore nel salvataggio:', error);
      alert('Errore nel salvataggio delle impostazioni');
    }
  }, [theme, branding, onSave]);

  /**
   * Gestisce il reset completo
   */
  const handleReset = useCallback(() => {
    if (showResetConfirm) {
      theme.resetTheme();
      branding.resetBranding();
      setShowResetConfirm(false);
    } else {
      setShowResetConfirm(true);
      setTimeout(() => setShowResetConfirm(false), 5000);
    }
  }, [showResetConfirm, theme, branding]);

  /**
   * Toggle preview mode
   */
  const togglePreview = useCallback(() => {
    if (previewMode) {
      theme.stopPreview();
    } else {
      theme.startPreview(theme.theme);
    }
    setPreviewMode(!previewMode);
  }, [previewMode, theme]);

  /**
   * Importa tema da file
   */
  const handleThemeImport = useCallback(async (e) => {
    const file = e.target.files[0];
    if (file) {
      const success = await theme.loadThemeFromFile(file);
      if (success) {
        alert('Tema importato con successo!');
      } else {
        alert('Errore nell\'importazione del tema');
      }
    }
  }, [theme]);

  /**
   * Importa branding da file
   */
  const handleBrandingImport = useCallback(async (e) => {
    const file = e.target.files[0];
    if (file) {
      const text = await file.text();
      const success = await branding.importBranding(text);
      if (success) {
        alert('Branding importato con successo!');
      } else {
        alert('Errore nell\'importazione del branding');
      }
    }
  }, [branding]);

  return (
    <div className={`branding-settings bg-white dark:bg-gray-900 rounded-lg shadow-lg ${className}`}>
      {/* Header */}
      <div className="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
        <div className="flex items-center justify-between">
          <h2 className="text-xl font-semibold text-gray-900 dark:text-white">
            Impostazioni Branding
          </h2>

          <div className="flex items-center gap-2">
            {/* Bottone Preview */}
            <button
              onClick={togglePreview}
              className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors ${
                previewMode
                  ? 'bg-yellow-500 text-white hover:bg-yellow-600'
                  : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600'
              }`}
            >
              {previewMode ? 'Ferma Preview' : 'Preview'}
            </button>

            {/* Bottone Salva */}
            <button
              onClick={handleSave}
              disabled={!theme.hasUnsavedChanges}
              className="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              Salva modifiche
            </button>
          </div>
        </div>

        {/* Indicatore modifiche non salvate */}
        {theme.hasUnsavedChanges && (
          <div className="mt-2 text-sm text-yellow-600 dark:text-yellow-400">
            ⚠ Hai modifiche non salvate
          </div>
        )}
      </div>

      {/* Tabs */}
      <div className="border-b border-gray-200 dark:border-gray-700">
        <nav className="flex space-x-8 px-6" aria-label="Tabs">
          {[
            { id: 'general', label: 'Generale' },
            { id: 'colors', label: 'Colori' },
            { id: 'typography', label: 'Tipografia' },
            { id: 'advanced', label: 'Avanzate' }
          ].map((tab) => (
            <button
              key={tab.id}
              onClick={() => setActiveTab(tab.id)}
              className={`py-3 px-1 border-b-2 font-medium text-sm transition-colors ${
                activeTab === tab.id
                  ? 'border-primary-500 text-primary-600 dark:text-primary-400'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'
              }`}
            >
              {tab.label}
            </button>
          ))}
        </nav>
      </div>

      {/* Content */}
      <div className="p-6">
        {/* Tab Generale */}
        {activeTab === 'general' && (
          <div className="space-y-6">
            {/* Nome azienda */}
            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Nome Azienda
              </label>
              <input
                type="text"
                value={branding.companyName}
                onChange={(e) => branding.updateCompanyName(e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                placeholder="Nome della tua azienda"
              />
            </div>

            {/* Slogan */}
            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Slogan
              </label>
              <input
                type="text"
                value={branding.tagline}
                onChange={(e) => branding.updateTagline(e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                placeholder="Il tuo slogan aziendale"
              />
            </div>

            {/* Logo */}
            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Logo
              </label>
              <div className="flex items-center gap-4">
                {branding.logo && (
                  <div className="w-32 h-32 border-2 border-gray-300 dark:border-gray-600 rounded-lg p-2 bg-white dark:bg-gray-800">
                    <img
                      src={branding.logo}
                      alt="Logo"
                      className="w-full h-full object-contain"
                    />
                  </div>
                )}
                <div className="flex-1">
                  <input
                    ref={logoInputRef}
                    type="file"
                    accept="image/*"
                    onChange={(e) => handleLogoUpload(e, 'logo')}
                    className="hidden"
                  />
                  <button
                    onClick={() => logoInputRef.current?.click()}
                    className="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors"
                  >
                    Carica Logo
                  </button>
                  {branding.logo && branding.logo !== '/logo-placeholder.svg' && (
                    <button
                      onClick={() => branding.removeImage('logo')}
                      className="ml-2 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors"
                    >
                      Rimuovi
                    </button>
                  )}
                </div>
              </div>
            </div>

            {/* Logo Dark Mode */}
            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Logo Dark Mode (opzionale)
              </label>
              <div className="flex items-center gap-4">
                {branding.logoDark && (
                  <div className="w-32 h-32 border-2 border-gray-300 dark:border-gray-600 rounded-lg p-2 bg-gray-900">
                    <img
                      src={branding.logoDark}
                      alt="Logo Dark"
                      className="w-full h-full object-contain"
                    />
                  </div>
                )}
                <div className="flex-1">
                  <input
                    ref={logoDarkInputRef}
                    type="file"
                    accept="image/*"
                    onChange={(e) => handleLogoUpload(e, 'logo-dark')}
                    className="hidden"
                  />
                  <button
                    onClick={() => logoDarkInputRef.current?.click()}
                    className="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors"
                  >
                    Carica Logo Dark
                  </button>
                  {branding.logoDark && (
                    <button
                      onClick={() => branding.removeImage('logo-dark')}
                      className="ml-2 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors"
                    >
                      Rimuovi
                    </button>
                  )}
                </div>
              </div>
            </div>

            {/* Favicon */}
            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Favicon
              </label>
              <div className="flex items-center gap-4">
                {branding.favicon && (
                  <div className="w-16 h-16 border-2 border-gray-300 dark:border-gray-600 rounded-lg p-2 bg-white dark:bg-gray-800">
                    <img
                      src={branding.favicon}
                      alt="Favicon"
                      className="w-full h-full object-contain"
                    />
                  </div>
                )}
                <div className="flex-1">
                  <input
                    ref={faviconInputRef}
                    type="file"
                    accept="image/*"
                    onChange={(e) => handleLogoUpload(e, 'favicon')}
                    className="hidden"
                  />
                  <button
                    onClick={() => faviconInputRef.current?.click()}
                    className="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors"
                  >
                    Carica Favicon
                  </button>
                  {branding.favicon && branding.favicon !== '/favicon.ico' && (
                    <button
                      onClick={() => branding.removeImage('favicon')}
                      className="ml-2 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors"
                    >
                      Rimuovi
                    </button>
                  )}
                </div>
              </div>
            </div>
          </div>
        )}

        {/* Tab Colori */}
        {activeTab === 'colors' && (
          <div className="space-y-6">
            {/* Temi predefiniti */}
            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Temi Predefiniti
              </label>
              <div className="grid grid-cols-5 gap-2">
                {Object.entries(theme.availableThemes).map(([key, preset]) => (
                  <button
                    key={key}
                    onClick={() => theme.resetTheme(key)}
                    className="p-3 border-2 border-gray-200 dark:border-gray-700 rounded-lg hover:border-primary-500 transition-colors"
                  >
                    <div className="flex gap-1 mb-2">
                      <div
                        className="w-6 h-6 rounded"
                        style={{ backgroundColor: preset.primary }}
                      />
                      <div
                        className="w-6 h-6 rounded"
                        style={{ backgroundColor: preset.secondary }}
                      />
                      <div
                        className="w-6 h-6 rounded"
                        style={{ backgroundColor: preset.accent }}
                      />
                    </div>
                    <p className="text-xs text-gray-600 dark:text-gray-400">
                      {preset.name}
                    </p>
                  </button>
                ))}
              </div>
            </div>

            {/* Colore Primario */}
            <ColorPicker
              label="Colore Primario"
              value={theme.theme?.primary || '#3B82F6'}
              onChange={(color) => theme.setPrimaryColor(color)}
              showPalette={true}
              showHarmony={true}
              showContrast={true}
            />

            {/* Colore Secondario */}
            <ColorPicker
              label="Colore Secondario"
              value={theme.theme?.secondary || '#64748B'}
              onChange={(color) => theme.setSecondaryColor(color)}
              showPalette={true}
              showHarmony={false}
              showContrast={true}
            />

            {/* Dark Mode Toggle */}
            <div className="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
              <div>
                <p className="font-medium text-gray-900 dark:text-white">
                  Modalità Scura
                </p>
                <p className="text-sm text-gray-500 dark:text-gray-400">
                  Abilita la modalità scura per l'interfaccia
                </p>
              </div>
              <button
                onClick={theme.toggleDarkMode}
                className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors ${
                  theme.isDarkMode ? 'bg-primary-600' : 'bg-gray-200'
                }`}
              >
                <span
                  className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${
                    theme.isDarkMode ? 'translate-x-6' : 'translate-x-1'
                  }`}
                />
              </button>
            </div>
          </div>
        )}

        {/* Tab Tipografia */}
        {activeTab === 'typography' && (
          <div className="space-y-6">
            {/* Font Heading */}
            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Font Titoli
              </label>
              <select
                value={theme.theme?.font?.heading || 'Inter'}
                onChange={(e) => theme.setFont('heading', e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500"
              >
                {availableFonts.map((font) => (
                  <option key={font.value} value={font.value}>
                    {font.label}
                  </option>
                ))}
              </select>
              <div
                className="mt-2 p-3 bg-gray-50 dark:bg-gray-800 rounded"
                style={{ fontFamily: theme.theme?.font?.heading || 'Inter' }}
              >
                <h3 className="text-2xl">Esempio di Titolo</h3>
              </div>
            </div>

            {/* Font Body */}
            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Font Corpo
              </label>
              <select
                value={theme.theme?.font?.body || 'Inter'}
                onChange={(e) => theme.setFont('body', e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500"
              >
                {availableFonts.map((font) => (
                  <option key={font.value} value={font.value}>
                    {font.label}
                  </option>
                ))}
              </select>
              <div
                className="mt-2 p-3 bg-gray-50 dark:bg-gray-800 rounded"
                style={{ fontFamily: theme.theme?.font?.body || 'Inter' }}
              >
                <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</p>
              </div>
            </div>
          </div>
        )}

        {/* Tab Avanzate */}
        {activeTab === 'advanced' && (
          <div className="space-y-6">
            {/* Import/Export Tema */}
            <div className="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
              <h3 className="font-medium text-gray-900 dark:text-white mb-4">
                Import/Export Tema
              </h3>
              <div className="flex gap-2">
                <input
                  ref={themeImportRef}
                  type="file"
                  accept=".json"
                  onChange={handleThemeImport}
                  className="hidden"
                />
                <button
                  onClick={() => themeImportRef.current?.click()}
                  className="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors"
                >
                  Importa Tema
                </button>
                <button
                  onClick={() => downloadThemeAsJSON(theme.theme, `tema-${tenantId}.json`)}
                  className="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors"
                >
                  Esporta JSON
                </button>
                <button
                  onClick={() => downloadThemeAsCSS(theme.theme, `tema-${tenantId}.css`)}
                  className="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors"
                >
                  Esporta CSS
                </button>
              </div>
            </div>

            {/* Import/Export Branding */}
            <div className="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
              <h3 className="font-medium text-gray-900 dark:text-white mb-4">
                Import/Export Branding
              </h3>
              <div className="flex gap-2">
                <input
                  ref={brandingImportRef}
                  type="file"
                  accept=".json"
                  onChange={handleBrandingImport}
                  className="hidden"
                />
                <button
                  onClick={() => brandingImportRef.current?.click()}
                  className="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors"
                >
                  Importa Branding
                </button>
                <button
                  onClick={() => {
                    const data = branding.exportBranding();
                    const blob = new Blob([data], { type: 'application/json' });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `branding-${tenantId}.json`;
                    a.click();
                    URL.revokeObjectURL(url);
                  }}
                  className="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors"
                >
                  Esporta Branding
                </button>
              </div>
            </div>

            {/* Undo/Redo */}
            <div className="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
              <h3 className="font-medium text-gray-900 dark:text-white mb-4">
                Cronologia Modifiche
              </h3>
              <div className="flex gap-2">
                <button
                  onClick={theme.undo}
                  disabled={!theme.canUndo}
                  className="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                >
                  ← Annulla
                </button>
                <button
                  onClick={theme.redo}
                  disabled={!theme.canRedo}
                  className="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                >
                  Ripeti →
                </button>
              </div>
            </div>

            {/* Reset */}
            <div className="p-4 bg-red-50 dark:bg-red-900/20 rounded-lg">
              <h3 className="font-medium text-red-900 dark:text-red-300 mb-4">
                Zona Pericolosa
              </h3>
              <button
                onClick={handleReset}
                className={`px-4 py-2 rounded-lg transition-colors ${
                  showResetConfirm
                    ? 'bg-red-600 text-white hover:bg-red-700'
                    : 'bg-gray-600 text-white hover:bg-gray-700'
                }`}
              >
                {showResetConfirm ? 'Conferma Reset' : 'Reset Tutto'}
              </button>
              {showResetConfirm && (
                <p className="mt-2 text-sm text-red-600 dark:text-red-400">
                  Clicca di nuovo per confermare il reset completo
                </p>
              )}
            </div>
          </div>
        )}
      </div>
    </div>
  );
};

export default BrandingSettings;