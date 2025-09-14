/**
 * Hook personalizzato per la gestione del branding del tenant
 * Gestisce logo, favicon e altre risorse brandizzate
 */

import { useState, useEffect, useCallback } from 'react';

/**
 * Hook per la gestione completa del branding
 * @param {string} tenantId - ID del tenant
 * @returns {object} Stato e funzioni per gestione branding
 */
export const useBranding = (tenantId) => {
  // Stato per il logo
  const [logo, setLogo] = useState(() => {
    const stored = localStorage.getItem(`logo_${tenantId}`);
    return stored || '/logo-placeholder.svg';
  });

  // Stato per il logo dark mode
  const [logoDark, setLogoDark] = useState(() => {
    const stored = localStorage.getItem(`logo_dark_${tenantId}`);
    return stored || null;
  });

  // Stato per il favicon
  const [favicon, setFavicon] = useState(() => {
    const stored = localStorage.getItem(`favicon_${tenantId}`);
    return stored || '/favicon.ico';
  });

  // Stato per il nome dell'azienda
  const [companyName, setCompanyName] = useState(() => {
    const stored = localStorage.getItem(`company_name_${tenantId}`);
    return stored || 'NexioSolution';
  });

  // Stato per lo slogan
  const [tagline, setTagline] = useState(() => {
    const stored = localStorage.getItem(`tagline_${tenantId}`);
    return stored || '';
  });

  // Stato per i metadati SEO
  const [seoMetadata, setSeoMetadata] = useState(() => {
    const stored = localStorage.getItem(`seo_${tenantId}`);
    if (stored) {
      try {
        return JSON.parse(stored);
      } catch {
        return {};
      }
    }
    return {
      title: 'NexioSolution',
      description: 'White-label solution platform',
      keywords: []
    };
  });

  // Stati di caricamento
  const [isUploading, setIsUploading] = useState(false);
  const [uploadProgress, setUploadProgress] = useState(0);
  const [uploadError, setUploadError] = useState(null);

  /**
   * Applica il favicon al documento
   */
  useEffect(() => {
    if (favicon) {
      // Rimuovi favicon esistenti
      const existingLinks = document.querySelectorAll("link[rel*='icon']");
      existingLinks.forEach(link => link.remove());

      // Aggiungi nuovo favicon
      const link = document.createElement('link');
      link.rel = 'icon';
      link.href = favicon;
      document.head.appendChild(link);
    }
  }, [favicon]);

  /**
   * Applica i metadati SEO
   */
  useEffect(() => {
    if (seoMetadata.title) {
      document.title = seoMetadata.title;
    }

    if (seoMetadata.description) {
      let metaDescription = document.querySelector("meta[name='description']");
      if (!metaDescription) {
        metaDescription = document.createElement('meta');
        metaDescription.name = 'description';
        document.head.appendChild(metaDescription);
      }
      metaDescription.content = seoMetadata.description;
    }

    if (seoMetadata.keywords && seoMetadata.keywords.length > 0) {
      let metaKeywords = document.querySelector("meta[name='keywords']");
      if (!metaKeywords) {
        metaKeywords = document.createElement('meta');
        metaKeywords.name = 'keywords';
        document.head.appendChild(metaKeywords);
      }
      metaKeywords.content = seoMetadata.keywords.join(', ');
    }
  }, [seoMetadata]);

  /**
   * Upload di un file immagine
   */
  const uploadImage = useCallback(async (file, type = 'logo') => {
    setIsUploading(true);
    setUploadProgress(0);
    setUploadError(null);

    try {
      // Validazione file
      const validTypes = ['image/jpeg', 'image/png', 'image/svg+xml', 'image/webp'];
      if (!validTypes.includes(file.type)) {
        throw new Error('Formato file non supportato. Usa JPG, PNG, SVG o WebP.');
      }

      // Validazione dimensione (max 5MB)
      const maxSize = 5 * 1024 * 1024;
      if (file.size > maxSize) {
        throw new Error('Il file è troppo grande. Massimo 5MB.');
      }

      // Simula upload con progress
      // In produzione, questo dovrebbe essere sostituito con un vero upload
      const reader = new FileReader();

      reader.onprogress = (event) => {
        if (event.lengthComputable) {
          const progress = (event.loaded / event.total) * 100;
          setUploadProgress(progress);
        }
      };

      reader.onload = (event) => {
        const dataUrl = event.target.result;

        // Salva in base al tipo
        switch (type) {
          case 'logo':
            setLogo(dataUrl);
            localStorage.setItem(`logo_${tenantId}`, dataUrl);
            break;
          case 'logo-dark':
            setLogoDark(dataUrl);
            localStorage.setItem(`logo_dark_${tenantId}`, dataUrl);
            break;
          case 'favicon':
            setFavicon(dataUrl);
            localStorage.setItem(`favicon_${tenantId}`, dataUrl);
            break;
        }

        setUploadProgress(100);
      };

      reader.readAsDataURL(file);

      return true;
    } catch (error) {
      setUploadError(error.message);
      return false;
    } finally {
      setTimeout(() => {
        setIsUploading(false);
        setUploadProgress(0);
      }, 500);
    }
  }, [tenantId]);

  /**
   * Rimuovi un'immagine
   */
  const removeImage = useCallback((type) => {
    switch (type) {
      case 'logo':
        setLogo('/logo-placeholder.svg');
        localStorage.removeItem(`logo_${tenantId}`);
        break;
      case 'logo-dark':
        setLogoDark(null);
        localStorage.removeItem(`logo_dark_${tenantId}`);
        break;
      case 'favicon':
        setFavicon('/favicon.ico');
        localStorage.removeItem(`favicon_${tenantId}`);
        break;
    }
  }, [tenantId]);

  /**
   * Aggiorna il nome dell'azienda
   */
  const updateCompanyName = useCallback((name) => {
    setCompanyName(name);
    localStorage.setItem(`company_name_${tenantId}`, name);

    // Aggiorna anche il titolo SEO se correlato
    setSeoMetadata(prev => ({
      ...prev,
      title: `${name} - Dashboard`
    }));
  }, [tenantId]);

  /**
   * Aggiorna lo slogan
   */
  const updateTagline = useCallback((text) => {
    setTagline(text);
    localStorage.setItem(`tagline_${tenantId}`, text);
  }, [tenantId]);

  /**
   * Aggiorna i metadati SEO
   */
  const updateSeoMetadata = useCallback((updates) => {
    setSeoMetadata(prev => {
      const newMetadata = { ...prev, ...updates };
      localStorage.setItem(`seo_${tenantId}`, JSON.stringify(newMetadata));
      return newMetadata;
    });
  }, [tenantId]);

  /**
   * Reset completo del branding
   */
  const resetBranding = useCallback(() => {
    // Rimuovi tutto dal localStorage
    localStorage.removeItem(`logo_${tenantId}`);
    localStorage.removeItem(`logo_dark_${tenantId}`);
    localStorage.removeItem(`favicon_${tenantId}`);
    localStorage.removeItem(`company_name_${tenantId}`);
    localStorage.removeItem(`tagline_${tenantId}`);
    localStorage.removeItem(`seo_${tenantId}`);

    // Reset stati
    setLogo('/logo-placeholder.svg');
    setLogoDark(null);
    setFavicon('/favicon.ico');
    setCompanyName('NexioSolution');
    setTagline('');
    setSeoMetadata({
      title: 'NexioSolution',
      description: 'White-label solution platform',
      keywords: []
    });
  }, [tenantId]);

  /**
   * Esporta configurazione branding
   */
  const exportBranding = useCallback(() => {
    const brandingData = {
      version: '1.0.0',
      tenantId,
      timestamp: new Date().toISOString(),
      branding: {
        logo,
        logoDark,
        favicon,
        companyName,
        tagline,
        seoMetadata
      }
    };

    return JSON.stringify(brandingData, null, 2);
  }, [logo, logoDark, favicon, companyName, tagline, seoMetadata, tenantId]);

  /**
   * Importa configurazione branding
   */
  const importBranding = useCallback(async (jsonString) => {
    try {
      const data = JSON.parse(jsonString);

      if (!data.branding) {
        throw new Error('Formato branding non valido');
      }

      const { branding } = data;

      // Applica tutti i valori
      if (branding.logo) {
        setLogo(branding.logo);
        localStorage.setItem(`logo_${tenantId}`, branding.logo);
      }

      if (branding.logoDark) {
        setLogoDark(branding.logoDark);
        localStorage.setItem(`logo_dark_${tenantId}`, branding.logoDark);
      }

      if (branding.favicon) {
        setFavicon(branding.favicon);
        localStorage.setItem(`favicon_${tenantId}`, branding.favicon);
      }

      if (branding.companyName) {
        updateCompanyName(branding.companyName);
      }

      if (branding.tagline) {
        updateTagline(branding.tagline);
      }

      if (branding.seoMetadata) {
        updateSeoMetadata(branding.seoMetadata);
      }

      return true;
    } catch (error) {
      console.error('Errore nell\'importazione del branding:', error);
      return false;
    }
  }, [tenantId, updateCompanyName, updateTagline, updateSeoMetadata]);

  /**
   * Ottieni URL completo per risorsa
   */
  const getAssetUrl = useCallback((assetType) => {
    switch (assetType) {
      case 'logo':
        return logo;
      case 'logo-dark':
        return logoDark || logo;
      case 'favicon':
        return favicon;
      default:
        return null;
    }
  }, [logo, logoDark, favicon]);

  return {
    // Stati
    logo,
    logoDark,
    favicon,
    companyName,
    tagline,
    seoMetadata,

    // Stati upload
    isUploading,
    uploadProgress,
    uploadError,

    // Funzioni
    uploadImage,
    removeImage,
    updateCompanyName,
    updateTagline,
    updateSeoMetadata,
    resetBranding,
    exportBranding,
    importBranding,
    getAssetUrl
  };
};

export default useBranding;