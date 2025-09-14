import React, { useState, useEffect } from 'react';
import { ChevronDown, Building2, Check } from 'lucide-react';
import { api } from '../services/api';

/**
 * Componente per selezione tenant in ambiente localhost
 * Permette di switchare tra tenant senza subdomain
 */
const TenantSelector = () => {
  const [tenants, setTenants] = useState([]);
  const [currentTenant, setCurrentTenant] = useState(null);
  const [isOpen, setIsOpen] = useState(false);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadTenants();
    loadCurrentTenant();
  }, []);

  // Carica lista tenant disponibili
  const loadTenants = async () => {
    try {
      const response = await api.get('/tenants/available');
      setTenants(response.data);
    } catch (error) {
      console.error('Errore caricamento tenant:', error);
    }
  };

  // Carica tenant corrente
  const loadCurrentTenant = () => {
    // Recupera da localStorage o sessione
    const savedTenant = localStorage.getItem('current_tenant');
    if (savedTenant) {
      setCurrentTenant(JSON.parse(savedTenant));
    } else {
      // Usa tenant di default
      setCurrentTenant({
        id: 'demo',
        name: 'Demo Company',
        code: 'demo'
      });
    }
    setLoading(false);
  };

  // Cambia tenant
  const switchTenant = async (tenant) => {
    try {
      // Salva in localStorage
      localStorage.setItem('current_tenant', JSON.stringify(tenant));
      localStorage.setItem('X-Tenant-ID', tenant.code);

      // Aggiorna header API per richieste future
      api.defaults.headers.common['X-Tenant-ID'] = tenant.code;

      // Notifica cambio tenant
      window.dispatchEvent(new CustomEvent('tenant-changed', {
        detail: tenant
      }));

      setCurrentTenant(tenant);
      setIsOpen(false);

      // Ricarica la pagina per aggiornare i dati
      window.location.reload();
    } catch (error) {
      console.error('Errore cambio tenant:', error);
    }
  };

  if (loading) {
    return (
      <div className="animate-pulse">
        <div className="h-10 w-48 bg-gray-700 rounded"></div>
      </div>
    );
  }

  return (
    <div className="relative">
      {/* Trigger Button */}
      <button
        onClick={() => setIsOpen(!isOpen)}
        className="flex items-center gap-2 px-4 py-2 bg-gray-800 hover:bg-gray-700
                   text-white rounded-lg transition-colors duration-200
                   border border-gray-600 hover:border-gray-500"
      >
        <Building2 className="w-5 h-5 text-blue-400" />
        <span className="font-medium">{currentTenant?.name || 'Seleziona Tenant'}</span>
        <ChevronDown className={`w-4 h-4 transition-transform ${isOpen ? 'rotate-180' : ''}`} />
      </button>

      {/* Dropdown Menu */}
      {isOpen && (
        <>
          {/* Backdrop */}
          <div
            className="fixed inset-0 z-10"
            onClick={() => setIsOpen(false)}
          />

          {/* Menu */}
          <div className="absolute top-full mt-2 w-64 bg-gray-800 border border-gray-600
                          rounded-lg shadow-xl z-20 overflow-hidden">
            <div className="p-2">
              <div className="text-xs text-gray-400 px-3 py-1 uppercase tracking-wider">
                Tenant Disponibili
              </div>

              {tenants.length > 0 ? (
                tenants.map((tenant) => (
                  <button
                    key={tenant.id}
                    onClick={() => switchTenant(tenant)}
                    className={`w-full flex items-center justify-between px-3 py-2
                               rounded-md transition-colors duration-150
                               ${currentTenant?.id === tenant.id
                                 ? 'bg-blue-600 text-white'
                                 : 'hover:bg-gray-700 text-gray-200'}`}
                  >
                    <div className="flex items-center gap-2">
                      <Building2 className="w-4 h-4" />
                      <div className="text-left">
                        <div className="font-medium">{tenant.name}</div>
                        <div className="text-xs opacity-75">
                          Codice: {tenant.code}
                        </div>
                      </div>
                    </div>
                    {currentTenant?.id === tenant.id && (
                      <Check className="w-4 h-4" />
                    )}
                  </button>
                ))
              ) : (
                <div className="px-3 py-4 text-center text-gray-400">
                  Nessun tenant disponibile
                </div>
              )}
            </div>

            {/* Info Footer */}
            <div className="border-t border-gray-700 p-3 bg-gray-900">
              <div className="text-xs text-gray-400">
                <div className="flex items-center gap-1 mb-1">
                  <span className="font-semibold">Modalità:</span>
                  <span>Localhost Multi-tenant</span>
                </div>
                <div className="flex items-center gap-1">
                  <span className="font-semibold">Header:</span>
                  <code className="bg-gray-800 px-1 rounded">X-Tenant-ID</code>
                </div>
              </div>
            </div>
          </div>
        </>
      )}
    </div>
  );
};

export default TenantSelector;