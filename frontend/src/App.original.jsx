import { useEffect, lazy, Suspense } from 'react';
import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { Toaster } from 'react-hot-toast';
import { Loader2 } from 'lucide-react';

// Stores
import { useThemeStore } from './stores/themeStore';
import { useNotificationStore } from './stores/notificationStore';

// Layout
import DashboardLayout from './components/layout/DashboardLayout';
import ProtectedRoute from './components/auth/ProtectedRoute';

// Pages - Lazy loading
const LoginPage = lazy(() => import('./pages/auth/LoginPage'));
const Dashboard = lazy(() => import('./pages/Dashboard'));
const Calendar = lazy(() => import('./pages/Calendar'));
const FileManager = lazy(() => import('./pages/FileManager'));
const TaskManager = lazy(() => import('./pages/TaskManager'));
const Chat = lazy(() => import('./pages/Chat'));
const Companies = lazy(() => import('./pages/Companies'));
const Users = lazy(() => import('./pages/Users'));
const Settings = lazy(() => import('./pages/Settings'));
const Profile = lazy(() => import('./pages/Profile'));
const NotFound = lazy(() => import('./pages/NotFound'));

// Query client per React Query
const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 5 * 60 * 1000, // 5 minuti
      cacheTime: 10 * 60 * 1000, // 10 minuti
      refetchOnWindowFocus: false,
      retry: 1
    }
  }
});

// Loading component
const LoadingFallback = () => (
  <div className="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-dark-background">
    <div className="text-center">
      <Loader2 className="h-12 w-12 animate-spin text-primary mx-auto" />
      <p className="mt-4 text-gray-600 dark:text-gray-400">Caricamento...</p>
    </div>
  </div>
);

function App() {
  const { initTheme } = useThemeStore();
  const { requestNotificationPermission, subscribeToPush } = useNotificationStore();

  useEffect(() => {
    // Inizializza tema
    initTheme();

    // Richiedi permesso notifiche
    requestNotificationPermission();

    // Registra service worker per PWA
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.ready.then(registration => {
        console.log('Service Worker registrato:', registration);

        // Sottoscrivi push notifications
        subscribeToPush();
      });
    }

    // Gestione installazione PWA
    let deferredPrompt;
    window.addEventListener('beforeinstallprompt', (e) => {
      e.preventDefault();
      deferredPrompt = e;
      // Mostra pulsante di installazione nella UI
      console.log('App installabile');
    });

    // Gestione aggiornamenti PWA
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.addEventListener('controllerchange', () => {
        window.location.reload();
      });
    }

    // Gestione stato online/offline
    const handleOnline = () => {
      console.log('Connessione ripristinata');
    };

    const handleOffline = () => {
      console.log('Connessione persa - Modalità offline');
    };

    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);

    return () => {
      window.removeEventListener('online', handleOnline);
      window.removeEventListener('offline', handleOffline);
    };
  }, [initTheme, requestNotificationPermission, subscribeToPush]);

  return (
    <QueryClientProvider client={queryClient}>
      <Router>
        <Suspense fallback={<LoadingFallback />}>
          <Routes>
            {/* Route pubbliche */}
            <Route path="/login" element={<LoginPage />} />
            <Route path="/forgot-password" element={<div>Password Reset Page</div>} />
            <Route path="/register" element={<div>Register Page</div>} />

            {/* Route protette */}
            <Route
              path="/"
              element={
                <ProtectedRoute>
                  <DashboardLayout />
                </ProtectedRoute>
              }
            >
              <Route index element={<Navigate to="/dashboard" replace />} />
              <Route path="dashboard" element={<Dashboard />} />
              <Route path="calendar" element={<Calendar />} />

              {/* File Manager routes */}
              <Route path="files" element={<FileManager />} />
              <Route path="files/my-files" element={<FileManager />} />
              <Route path="files/shared" element={<FileManager />} />
              <Route path="files/workflows" element={<FileManager />} />

              {/* Task routes */}
              <Route path="tasks" element={<TaskManager />} />
              <Route path="tasks/kanban" element={<TaskManager />} />
              <Route path="tasks/list" element={<TaskManager />} />
              <Route path="tasks/timeline" element={<TaskManager />} />

              {/* Chat */}
              <Route path="chat" element={<Chat />} />
              <Route path="chat/:conversationId" element={<Chat />} />

              {/* Companies - Solo admin e manager */}
              <Route
                path="companies"
                element={
                  <ProtectedRoute requiredRole={['admin', 'manager']}>
                    <Companies />
                  </ProtectedRoute>
                }
              />

              {/* Users - Solo admin */}
              <Route
                path="users"
                element={
                  <ProtectedRoute requiredRole="admin">
                    <Users />
                  </ProtectedRoute>
                }
              />

              {/* Reports */}
              <Route path="reports" element={<div>Reports Page</div>} />

              {/* Settings */}
              <Route path="settings" element={<Settings />} />
              <Route path="settings/general" element={<Settings />} />
              <Route path="settings/tenant" element={<Settings />} />
              <Route path="settings/security" element={<Settings />} />
              <Route path="settings/audit" element={<Settings />} />

              {/* Profile */}
              <Route path="profile" element={<Profile />} />

              {/* Notifications */}
              <Route path="notifications" element={<div>Notifications Page</div>} />

              {/* Search */}
              <Route path="search" element={<div>Search Results Page</div>} />
            </Route>

            {/* Error pages */}
            <Route path="/unauthorized" element={<div>Unauthorized - Non hai i permessi</div>} />
            <Route path="*" element={<NotFound />} />
          </Routes>
        </Suspense>

        {/* Toast notifications */}
        <Toaster
          position="top-right"
          reverseOrder={false}
          gutter={8}
          toastOptions={{
            duration: 4000,
            style: {
              background: '#1F2937',
              color: '#F3F4F6',
              borderRadius: '0.5rem',
              padding: '0.75rem'
            },
            success: {
              iconTheme: {
                primary: '#10B981',
                secondary: '#F3F4F6'
              }
            },
            error: {
              iconTheme: {
                primary: '#EF4444',
                secondary: '#F3F4F6'
              }
            }
          }}
        />
      </Router>
    </QueryClientProvider>
  );
}

export default App
