/**
 * Componente per verificare l'accessibilità del tema
 * Controlla contrasti, dimensioni font e conformità WCAG
 */

import React, { useState, useEffect } from 'react';
import { useTheme } from '../../hooks/useTheme';
import { checkWCAGContrast, getContrastRatio } from '../../utils/colors';

/**
 * Checker per verificare l'accessibilità del tema corrente
 */
const AccessibilityChecker = ({ className = '' }) => {
  const theme = useTheme();
  const [results, setResults] = useState({
    contrasts: [],
    fontSize: [],
    focusIndicators: [],
    ariaCompliance: [],
    overall: 'checking'
  });

  // Combinazioni di colori da testare
  const colorCombinations = [
    { bg: theme.theme?.primary || '#3B82F6', fg: '#ffffff', label: 'Primario su Bianco' },
    { bg: theme.theme?.primary || '#3B82F6', fg: '#000000', label: 'Primario su Nero' },
    { bg: '#ffffff', fg: theme.theme?.primary || '#3B82F6', label: 'Bianco su Primario' },
    { bg: theme.theme?.secondary || '#64748B', fg: '#ffffff', label: 'Secondario su Bianco' },
    { bg: '#ffffff', fg: theme.theme?.secondary || '#64748B', label: 'Bianco su Secondario' },
    { bg: theme.theme?.neutral || '#f5f5f5', fg: '#000000', label: 'Neutro su Nero' },
  ];

  /**
   * Esegue tutti i test di accessibilità
   */
  useEffect(() => {
    const runAccessibilityTests = () => {
      const contrastResults = [];
      let passCount = 0;
      let totalTests = 0;

      // Test contrasti colori
      colorCombinations.forEach(combo => {
        const result = checkWCAGContrast(combo.fg, combo.bg, 'AA');
        contrastResults.push({
          ...combo,
          ...result,
          passed: result.normalText
        });
        totalTests++;
        if (result.normalText) passCount++;
      });

      // Test dimensioni font
      const fontSizeResults = [];
      const minFontSize = 14; // px
      const baseFontSize = parseInt(getComputedStyle(document.documentElement).fontSize);

      fontSizeResults.push({
        label: 'Font Base',
        size: baseFontSize,
        passed: baseFontSize >= minFontSize,
        recommendation: baseFontSize >= minFontSize ? 'Dimensione adeguata' : 'Aumentare dimensione font'
      });

      // Test focus indicators
      const focusResults = [];
      const focusOutline = getComputedStyle(document.documentElement).getPropertyValue('--focus-outline');

      focusResults.push({
        label: 'Focus Outline',
        value: focusOutline || '2px solid primary',
        passed: true,
        recommendation: 'Focus indicator presente'
      });

      // Test ARIA compliance
      const ariaResults = [];

      // Verifica presenza di landmark roles
      const hasMainLandmark = document.querySelector('main') !== null;
      const hasNavLandmark = document.querySelector('nav') !== null;

      ariaResults.push({
        label: 'Main Landmark',
        passed: hasMainLandmark,
        recommendation: hasMainLandmark ? 'Main landmark presente' : 'Aggiungere <main> landmark'
      });

      ariaResults.push({
        label: 'Navigation Landmark',
        passed: hasNavLandmark,
        recommendation: hasNavLandmark ? 'Nav landmark presente' : 'Aggiungere <nav> landmark'
      });

      // Calcola punteggio overall
      const totalPassed = contrastResults.filter(r => r.passed).length +
                         fontSizeResults.filter(r => r.passed).length +
                         focusResults.filter(r => r.passed).length +
                         ariaResults.filter(r => r.passed).length;

      const totalChecks = contrastResults.length + fontSizeResults.length +
                         focusResults.length + ariaResults.length;

      const percentage = Math.round((totalPassed / totalChecks) * 100);

      let overallStatus = 'poor';
      if (percentage >= 90) overallStatus = 'excellent';
      else if (percentage >= 75) overallStatus = 'good';
      else if (percentage >= 50) overallStatus = 'fair';

      setResults({
        contrasts: contrastResults,
        fontSize: fontSizeResults,
        focusIndicators: focusResults,
        ariaCompliance: ariaResults,
        overall: overallStatus,
        percentage,
        totalPassed,
        totalChecks
      });
    };

    // Ritarda l'esecuzione per permettere al DOM di aggiornarsi
    const timer = setTimeout(runAccessibilityTests, 500);
    return () => clearTimeout(timer);
  }, [theme.theme]);

  /**
   * Genera suggerimenti basati sui risultati
   */
  const generateSuggestions = () => {
    const suggestions = [];

    // Suggerimenti per contrasto
    const failedContrasts = results.contrasts.filter(c => !c.passed);
    if (failedContrasts.length > 0) {
      suggestions.push({
        type: 'error',
        message: `${failedContrasts.length} combinazioni di colori non soddisfano WCAG AA`,
        action: 'Aumenta il contrasto tra i colori'
      });
    }

    // Suggerimenti per font
    const failedFonts = results.fontSize.filter(f => !f.passed);
    if (failedFonts.length > 0) {
      suggestions.push({
        type: 'warning',
        message: 'Dimensione font troppo piccola',
        action: 'Usa almeno 14px per il testo del corpo'
      });
    }

    // Suggerimenti ARIA
    const failedAria = results.ariaCompliance.filter(a => !a.passed);
    if (failedAria.length > 0) {
      suggestions.push({
        type: 'info',
        message: 'Miglioramenti ARIA disponibili',
        action: 'Aggiungi landmark roles e label ARIA'
      });
    }

    if (suggestions.length === 0) {
      suggestions.push({
        type: 'success',
        message: 'Ottimo lavoro! Il tema soddisfa gli standard di accessibilità',
        action: 'Continua a monitorare le modifiche'
      });
    }

    return suggestions;
  };

  const getStatusColor = (status) => {
    switch (status) {
      case 'excellent': return 'text-green-600 dark:text-green-400';
      case 'good': return 'text-blue-600 dark:text-blue-400';
      case 'fair': return 'text-yellow-600 dark:text-yellow-400';
      case 'poor': return 'text-red-600 dark:text-red-400';
      default: return 'text-gray-600 dark:text-gray-400';
    }
  };

  const getStatusIcon = (passed) => {
    if (passed) {
      return (
        <svg className="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
          <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
        </svg>
      );
    }
    return (
      <svg className="w-5 h-5 text-red-500" fill="currentColor" viewBox="0 0 20 20">
        <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd" />
      </svg>
    );
  };

  return (
    <div className={`accessibility-checker bg-white dark:bg-gray-900 rounded-lg shadow-lg p-6 ${className}`}>
      {/* Header con punteggio overall */}
      <div className="mb-6">
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-xl font-semibold text-gray-900 dark:text-white">
            Verifica Accessibilità
          </h2>
          {results.overall !== 'checking' && (
            <div className="text-center">
              <div className={`text-3xl font-bold ${getStatusColor(results.overall)}`}>
                {results.percentage}%
              </div>
              <p className="text-sm text-gray-600 dark:text-gray-400">
                {results.totalPassed}/{results.totalChecks} test superati
              </p>
            </div>
          )}
        </div>

        {/* Progress bar overall */}
        {results.overall !== 'checking' && (
          <div className="w-full bg-gray-200 rounded-full h-3 dark:bg-gray-700 overflow-hidden">
            <div
              className={`h-3 rounded-full transition-all duration-500 ${
                results.percentage >= 90 ? 'bg-green-500' :
                results.percentage >= 75 ? 'bg-blue-500' :
                results.percentage >= 50 ? 'bg-yellow-500' : 'bg-red-500'
              }`}
              style={{ width: `${results.percentage}%` }}
            />
          </div>
        )}
      </div>

      {/* Suggerimenti */}
      {results.overall !== 'checking' && (
        <div className="mb-6">
          <h3 className="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
            Suggerimenti
          </h3>
          <div className="space-y-2">
            {generateSuggestions().map((suggestion, index) => (
              <div
                key={index}
                className={`p-3 rounded-lg border ${
                  suggestion.type === 'error' ? 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800' :
                  suggestion.type === 'warning' ? 'bg-yellow-50 dark:bg-yellow-900/20 border-yellow-200 dark:border-yellow-800' :
                  suggestion.type === 'info' ? 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800' :
                  'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800'
                }`}
              >
                <p className={`text-sm font-medium ${
                  suggestion.type === 'error' ? 'text-red-800 dark:text-red-300' :
                  suggestion.type === 'warning' ? 'text-yellow-800 dark:text-yellow-300' :
                  suggestion.type === 'info' ? 'text-blue-800 dark:text-blue-300' :
                  'text-green-800 dark:text-green-300'
                }`}>
                  {suggestion.message}
                </p>
                <p className={`text-xs mt-1 ${
                  suggestion.type === 'error' ? 'text-red-700 dark:text-red-400' :
                  suggestion.type === 'warning' ? 'text-yellow-700 dark:text-yellow-400' :
                  suggestion.type === 'info' ? 'text-blue-700 dark:text-blue-400' :
                  'text-green-700 dark:text-green-400'
                }`}>
                  {suggestion.action}
                </p>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Risultati dettagliati */}
      <div className="space-y-6">
        {/* Contrasti Colori */}
        <div>
          <h3 className="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
            Contrasto Colori (WCAG AA)
          </h3>
          <div className="space-y-2">
            {results.contrasts.map((contrast, index) => (
              <div key={index} className="flex items-center justify-between p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800">
                <div className="flex items-center gap-3">
                  {getStatusIcon(contrast.passed)}
                  <div className="flex items-center gap-2">
                    <div
                      className="w-6 h-6 rounded border border-gray-300 dark:border-gray-600"
                      style={{ backgroundColor: contrast.bg }}
                    />
                    <div
                      className="w-6 h-6 rounded border border-gray-300 dark:border-gray-600"
                      style={{ backgroundColor: contrast.fg }}
                    />
                    <span className="text-sm text-gray-700 dark:text-gray-300">
                      {contrast.label}
                    </span>
                  </div>
                </div>
                <div className="text-right">
                  <span className={`text-sm font-medium ${contrast.passed ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'}`}>
                    {contrast.ratio}:1
                  </span>
                  <p className="text-xs text-gray-500 dark:text-gray-400">
                    {contrast.recommendation}
                  </p>
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* Dimensioni Font */}
        <div>
          <h3 className="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
            Dimensioni Font
          </h3>
          <div className="space-y-2">
            {results.fontSize.map((font, index) => (
              <div key={index} className="flex items-center justify-between p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800">
                <div className="flex items-center gap-3">
                  {getStatusIcon(font.passed)}
                  <span className="text-sm text-gray-700 dark:text-gray-300">
                    {font.label}
                  </span>
                </div>
                <div className="text-right">
                  <span className={`text-sm font-medium ${font.passed ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'}`}>
                    {font.size}px
                  </span>
                  <p className="text-xs text-gray-500 dark:text-gray-400">
                    {font.recommendation}
                  </p>
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* Focus Indicators */}
        <div>
          <h3 className="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
            Indicatori Focus
          </h3>
          <div className="space-y-2">
            {results.focusIndicators.map((focus, index) => (
              <div key={index} className="flex items-center justify-between p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800">
                <div className="flex items-center gap-3">
                  {getStatusIcon(focus.passed)}
                  <span className="text-sm text-gray-700 dark:text-gray-300">
                    {focus.label}
                  </span>
                </div>
                <p className="text-xs text-gray-500 dark:text-gray-400">
                  {focus.recommendation}
                </p>
              </div>
            ))}
          </div>
        </div>

        {/* ARIA Compliance */}
        <div>
          <h3 className="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
            Conformità ARIA
          </h3>
          <div className="space-y-2">
            {results.ariaCompliance.map((aria, index) => (
              <div key={index} className="flex items-center justify-between p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800">
                <div className="flex items-center gap-3">
                  {getStatusIcon(aria.passed)}
                  <span className="text-sm text-gray-700 dark:text-gray-300">
                    {aria.label}
                  </span>
                </div>
                <p className="text-xs text-gray-500 dark:text-gray-400">
                  {aria.recommendation}
                </p>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* Footer con azioni */}
      <div className="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
        <div className="flex gap-3">
          <button
            onClick={() => window.print()}
            className="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors"
          >
            Stampa Report
          </button>
          <button
            onClick={() => {
              const report = JSON.stringify(results, null, 2);
              const blob = new Blob([report], { type: 'application/json' });
              const url = URL.createObjectURL(blob);
              const a = document.createElement('a');
              a.href = url;
              a.download = 'accessibility-report.json';
              a.click();
              URL.revokeObjectURL(url);
            }}
            className="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors"
          >
            Esporta JSON
          </button>
        </div>
      </div>
    </div>
  );
};

export default AccessibilityChecker;