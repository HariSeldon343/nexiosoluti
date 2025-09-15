import React, { useState } from 'react';
import MainLayout from '../components/layout/MainLayout';
import {
  Settings as SettingsIcon,
  User,
  Bell,
  Shield,
  Palette,
  Globe,
  Database,
  Save,
  ChevronRight
} from 'lucide-react';

const Settings = () => {
  const [activeTab, setActiveTab] = useState('profile');

  const tabs = [
    { id: 'profile', label: 'Profilo', icon: User },
    { id: 'notifications', label: 'Notifiche', icon: Bell },
    { id: 'security', label: 'Sicurezza', icon: Shield },
    { id: 'appearance', label: 'Aspetto', icon: Palette },
    { id: 'language', label: 'Lingua', icon: Globe },
    { id: 'data', label: 'Dati', icon: Database },
  ];

  const [profileData, setProfileData] = useState({
    name: 'Mario Rossi',
    email: 'mario.rossi@example.com',
    phone: '+39 333 1234567',
    company: 'Tech Solutions Srl',
    role: 'Administrator'
  });

  const [notifications, setNotifications] = useState({
    email: true,
    push: true,
    desktop: false,
    taskReminders: true,
    eventReminders: true,
    systemUpdates: false
  });

  return (
    <MainLayout>
      <div className="p-6">
        {/* Header */}
        <div className="mb-6">
          <h1 className="text-2xl font-bold text-gray-900">Impostazioni</h1>
          <p className="text-gray-600 mt-1">Gestisci le tue preferenze e configurazioni</p>
        </div>

        <div className="flex gap-6">
          {/* Sidebar */}
          <div className="w-64 flex-shrink-0">
            <div className="bg-white rounded-lg shadow border border-gray-200">
              <nav className="p-2">
                {tabs.map((tab) => {
                  const Icon = tab.icon;
                  return (
                    <button
                      key={tab.id}
                      onClick={() => setActiveTab(tab.id)}
                      className={`w-full flex items-center justify-between px-3 py-2 rounded-lg transition-colors ${
                        activeTab === tab.id
                          ? 'bg-blue-50 text-blue-600'
                          : 'text-gray-700 hover:bg-gray-50'
                      }`}
                    >
                      <div className="flex items-center gap-3">
                        <Icon className="w-5 h-5" />
                        <span className="font-medium">{tab.label}</span>
                      </div>
                      {activeTab === tab.id && (
                        <ChevronRight className="w-4 h-4" />
                      )}
                    </button>
                  );
                })}
              </nav>
            </div>
          </div>

          {/* Content */}
          <div className="flex-1">
            <div className="bg-white rounded-lg shadow border border-gray-200 p-6">
              {activeTab === 'profile' && (
                <div>
                  <h2 className="text-lg font-semibold text-gray-900 mb-4">Informazioni Profilo</h2>
                  <div className="space-y-4">
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">
                        Nome completo
                      </label>
                      <input
                        type="text"
                        value={profileData.name}
                        onChange={(e) => setProfileData({...profileData, name: e.target.value})}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                      />
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">
                        Email
                      </label>
                      <input
                        type="email"
                        value={profileData.email}
                        onChange={(e) => setProfileData({...profileData, email: e.target.value})}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                      />
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">
                        Telefono
                      </label>
                      <input
                        type="tel"
                        value={profileData.phone}
                        onChange={(e) => setProfileData({...profileData, phone: e.target.value})}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                      />
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">
                        Azienda
                      </label>
                      <input
                        type="text"
                        value={profileData.company}
                        disabled
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50"
                      />
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">
                        Ruolo
                      </label>
                      <input
                        type="text"
                        value={profileData.role}
                        disabled
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50"
                      />
                    </div>
                  </div>
                </div>
              )}

              {activeTab === 'notifications' && (
                <div>
                  <h2 className="text-lg font-semibold text-gray-900 mb-4">Preferenze Notifiche</h2>
                  <div className="space-y-4">
                    <div className="flex items-center justify-between py-2">
                      <div>
                        <p className="font-medium text-gray-900">Notifiche Email</p>
                        <p className="text-sm text-gray-600">Ricevi notifiche via email</p>
                      </div>
                      <label className="relative inline-flex items-center cursor-pointer">
                        <input
                          type="checkbox"
                          checked={notifications.email}
                          onChange={(e) => setNotifications({...notifications, email: e.target.checked})}
                          className="sr-only peer"
                        />
                        <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                      </label>
                    </div>
                    <div className="flex items-center justify-between py-2">
                      <div>
                        <p className="font-medium text-gray-900">Notifiche Push</p>
                        <p className="text-sm text-gray-600">Ricevi notifiche push sul browser</p>
                      </div>
                      <label className="relative inline-flex items-center cursor-pointer">
                        <input
                          type="checkbox"
                          checked={notifications.push}
                          onChange={(e) => setNotifications({...notifications, push: e.target.checked})}
                          className="sr-only peer"
                        />
                        <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                      </label>
                    </div>
                    <div className="flex items-center justify-between py-2">
                      <div>
                        <p className="font-medium text-gray-900">Promemoria Attività</p>
                        <p className="text-sm text-gray-600">Ricevi promemoria per le attività in scadenza</p>
                      </div>
                      <label className="relative inline-flex items-center cursor-pointer">
                        <input
                          type="checkbox"
                          checked={notifications.taskReminders}
                          onChange={(e) => setNotifications({...notifications, taskReminders: e.target.checked})}
                          className="sr-only peer"
                        />
                        <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                      </label>
                    </div>
                    <div className="flex items-center justify-between py-2">
                      <div>
                        <p className="font-medium text-gray-900">Promemoria Eventi</p>
                        <p className="text-sm text-gray-600">Ricevi promemoria per gli eventi in calendario</p>
                      </div>
                      <label className="relative inline-flex items-center cursor-pointer">
                        <input
                          type="checkbox"
                          checked={notifications.eventReminders}
                          onChange={(e) => setNotifications({...notifications, eventReminders: e.target.checked})}
                          className="sr-only peer"
                        />
                        <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                      </label>
                    </div>
                  </div>
                </div>
              )}

              {activeTab === 'security' && (
                <div>
                  <h2 className="text-lg font-semibold text-gray-900 mb-4">Sicurezza Account</h2>
                  <div className="space-y-4">
                    <div className="p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                      <p className="text-sm text-yellow-800">
                        <strong>Autenticazione a due fattori:</strong> Non attiva
                      </p>
                      <button className="mt-2 text-sm text-yellow-600 hover:text-yellow-700 font-medium">
                        Attiva ora →
                      </button>
                    </div>
                    <div>
                      <h3 className="font-medium text-gray-900 mb-2">Cambia Password</h3>
                      <div className="space-y-3">
                        <input
                          type="password"
                          placeholder="Password attuale"
                          className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        />
                        <input
                          type="password"
                          placeholder="Nuova password"
                          className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        />
                        <input
                          type="password"
                          placeholder="Conferma nuova password"
                          className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        />
                      </div>
                    </div>
                    <div>
                      <h3 className="font-medium text-gray-900 mb-2">Sessioni Attive</h3>
                      <div className="space-y-2">
                        <div className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                          <div>
                            <p className="font-medium text-gray-900">Windows - Chrome</p>
                            <p className="text-sm text-gray-600">Milano, Italia • Sessione corrente</p>
                          </div>
                          <span className="text-xs text-green-600 font-medium">ATTIVA</span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              )}

              {activeTab === 'appearance' && (
                <div>
                  <h2 className="text-lg font-semibold text-gray-900 mb-4">Aspetto</h2>
                  <div className="space-y-4">
                    <div>
                      <p className="font-medium text-gray-900 mb-3">Tema</p>
                      <div className="grid grid-cols-3 gap-3">
                        <button className="p-4 border-2 border-blue-500 rounded-lg bg-white">
                          <div className="w-full h-20 bg-gray-100 rounded mb-2"></div>
                          <p className="text-sm font-medium">Chiaro</p>
                        </button>
                        <button className="p-4 border-2 border-gray-300 rounded-lg bg-white">
                          <div className="w-full h-20 bg-gray-800 rounded mb-2"></div>
                          <p className="text-sm font-medium">Scuro</p>
                        </button>
                        <button className="p-4 border-2 border-gray-300 rounded-lg bg-white">
                          <div className="w-full h-20 bg-gradient-to-b from-gray-100 to-gray-800 rounded mb-2"></div>
                          <p className="text-sm font-medium">Automatico</p>
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              )}

              {activeTab === 'language' && (
                <div>
                  <h2 className="text-lg font-semibold text-gray-900 mb-4">Lingua e Regione</h2>
                  <div className="space-y-4">
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">
                        Lingua
                      </label>
                      <select className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="it">Italiano</option>
                        <option value="en">English</option>
                        <option value="es">Español</option>
                        <option value="fr">Français</option>
                        <option value="de">Deutsch</option>
                      </select>
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">
                        Fuso orario
                      </label>
                      <select className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="Europe/Rome">Europa/Roma (GMT+1)</option>
                        <option value="Europe/London">Europa/Londra (GMT+0)</option>
                        <option value="America/New_York">America/New York (GMT-5)</option>
                      </select>
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">
                        Formato data
                      </label>
                      <select className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="DD/MM/YYYY">DD/MM/YYYY</option>
                        <option value="MM/DD/YYYY">MM/DD/YYYY</option>
                        <option value="YYYY-MM-DD">YYYY-MM-DD</option>
                      </select>
                    </div>
                  </div>
                </div>
              )}

              {activeTab === 'data' && (
                <div>
                  <h2 className="text-lg font-semibold text-gray-900 mb-4">Gestione Dati</h2>
                  <div className="space-y-4">
                    <div className="p-4 bg-gray-50 rounded-lg">
                      <h3 className="font-medium text-gray-900 mb-2">Esporta i tuoi dati</h3>
                      <p className="text-sm text-gray-600 mb-3">
                        Scarica una copia di tutti i tuoi dati in formato JSON o CSV
                      </p>
                      <button className="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                        Esporta Dati
                      </button>
                    </div>
                    <div className="p-4 bg-red-50 rounded-lg">
                      <h3 className="font-medium text-red-900 mb-2">Elimina Account</h3>
                      <p className="text-sm text-red-700 mb-3">
                        Questa azione è irreversibile. Tutti i tuoi dati verranno eliminati permanentemente.
                      </p>
                      <button className="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700">
                        Elimina Account
                      </button>
                    </div>
                  </div>
                </div>
              )}

              {/* Save button */}
              <div className="mt-6 pt-6 border-t border-gray-200">
                <div className="flex justify-end gap-3">
                  <button className="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                    Annulla
                  </button>
                  <button className="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center gap-2">
                    <Save className="w-4 h-4" />
                    Salva Modifiche
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </MainLayout>
  );
};

export default Settings;