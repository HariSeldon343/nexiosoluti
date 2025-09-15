import React, { useEffect, useState } from 'react';
import { BrowserRouter as Router, Routes, Route, Navigate, useNavigate } from 'react-router-dom';
import { Toaster } from 'react-hot-toast';
import { AuthProvider, useAuth } from './contexts/AuthContext';
import LoginPage from './pages/LoginPage';
import Dashboard from './pages/Dashboard';
import MainLayout from './components/layout/MainLayout';
import TaskManager from './pages/TaskManager';
import FileManager from './pages/FileManager';
import Calendar from './pages/Calendar';
import Chat from './pages/Chat';
import Users from './pages/Users';
import Companies from './pages/Companies';
import Settings from './pages/Settings';
import Profile from './pages/Profile';
import NotFound from './pages/NotFound';
import UserManagement from './pages/admin/UserManagement';

// Componente per gestire l'autenticazione - deve essere dentro AuthProvider
function ProtectedRoute({ children }) {
  const { isAuthenticated, loading } = useAuth();

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50">
        <div className="animate-pulse">
          <div className="w-12 h-12 bg-blue-500 rounded-full mx-auto mb-4"></div>
          <div className="text-xl text-gray-600">Caricamento...</div>
        </div>
      </div>
    );
  }

  if (!isAuthenticated) {
    return <Navigate to="/login" replace />;
  }

  return <MainLayout>{children}</MainLayout>;
}

// Landing page con navigazione funzionante
function LandingPage() {
  const navigate = useNavigate();

  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 flex items-center justify-center">
      <div className="max-w-4xl mx-auto p-8 text-center">
        <h1 className="text-5xl font-bold text-gray-800 mb-4">
          NexioSolution
        </h1>
        <p className="text-xl text-gray-600 mb-8">
          Piattaforma Collaborativa Multi-Tenant
        </p>

        <div className="space-x-4">
          <button
            onClick={() => navigate('/login')}
            className="bg-blue-500 text-white px-6 py-3 rounded-lg hover:bg-blue-600 transition-colors shadow-md"
          >
            Accedi alla Piattaforma
          </button>
          <button
            onClick={() => navigate('/dashboard')}
            className="bg-gray-500 text-white px-6 py-3 rounded-lg hover:bg-gray-600 transition-colors shadow-md"
          >
            Demo Dashboard
          </button>
        </div>

        <div className="mt-12 grid grid-cols-1 md:grid-cols-3 gap-6">
          <div className="bg-white p-6 rounded-lg shadow-md">
            <div className="text-blue-500 text-3xl mb-3">🏢</div>
            <h3 className="text-lg font-semibold mb-2">Multi-Tenant</h3>
            <p className="text-gray-600">Gestione completa multi-azienda con isolamento dati</p>
          </div>
          <div className="bg-white p-6 rounded-lg shadow-md">
            <div className="text-green-500 text-3xl mb-3">👥</div>
            <h3 className="text-lg font-semibold mb-2">Collaborativo</h3>
            <p className="text-gray-600">Chat in tempo reale, file sharing e task management</p>
          </div>
          <div className="bg-white p-6 rounded-lg shadow-md">
            <div className="text-purple-500 text-3xl mb-3">🔒</div>
            <h3 className="text-lg font-semibold mb-2">Sicuro</h3>
            <p className="text-gray-600">Autenticazione JWT, crittografia e audit log completo</p>
          </div>
        </div>

        <div className="mt-8 p-4 bg-white/50 rounded-lg">
          <p className="text-sm text-gray-600">
            Per accedere alla demo usa: <strong>admin@nexiosolution.com</strong> / <strong>password123</strong>
          </p>
        </div>
      </div>
    </div>
  );
}

// AppRoutes Component - deve essere dentro AuthProvider
function AppRoutes() {
  return (
    <Routes>
      {/* Landing page pubblica */}
      <Route path="/" element={<LandingPage />} />

      {/* Login page */}
      <Route path="/login" element={<LoginPage />} />

      {/* Routes protette */}
      <Route path="/dashboard" element={
        <ProtectedRoute>
          <Dashboard />
        </ProtectedRoute>
      } />

      <Route path="/tasks" element={
        <ProtectedRoute>
          <TaskManager />
        </ProtectedRoute>
      } />

      <Route path="/files" element={
        <ProtectedRoute>
          <FileManager />
        </ProtectedRoute>
      } />

      <Route path="/calendar" element={
        <ProtectedRoute>
          <Calendar />
        </ProtectedRoute>
      } />

      <Route path="/chat" element={
        <ProtectedRoute>
          <Chat />
        </ProtectedRoute>
      } />

      <Route path="/users" element={
        <ProtectedRoute>
          <Users />
        </ProtectedRoute>
      } />

      <Route path="/admin/users" element={
        <ProtectedRoute>
          <UserManagement />
        </ProtectedRoute>
      } />

      <Route path="/companies" element={
        <ProtectedRoute>
          <Companies />
        </ProtectedRoute>
      } />

      <Route path="/settings" element={
        <ProtectedRoute>
          <Settings />
        </ProtectedRoute>
      } />

      <Route path="/profile" element={
        <ProtectedRoute>
          <Profile />
        </ProtectedRoute>
      } />

      {/* 404 page */}
      <Route path="/404" element={<NotFound />} />

      {/* Redirect qualsiasi altra route */}
      <Route path="*" element={<Navigate to="/404" replace />} />
    </Routes>
  );
}

function App() {
  return (
    <Router>
      <AuthProvider>
        <AppRoutes />
        <Toaster
          position="top-right"
          reverseOrder={false}
          toastOptions={{
            duration: 4000,
            style: {
              background: '#363636',
              color: '#fff',
            },
            success: {
              style: {
                background: '#10b981',
              },
            },
            error: {
              style: {
                background: '#ef4444',
              },
            },
          }}
        />
      </AuthProvider>
    </Router>
  );
}

export default App