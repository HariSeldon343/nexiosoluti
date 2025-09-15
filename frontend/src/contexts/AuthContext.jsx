import React, { createContext, useState, useContext, useEffect } from 'react';
import axios from 'axios';
import toast from 'react-hot-toast';

const AuthContext = createContext(null);

export const useAuth = () => {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within AuthProvider');
  }
  return context;
};

export const AuthProvider = ({ children }) => {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);
  const [isAuthenticated, setIsAuthenticated] = useState(false);

  // Check if user is already logged in on mount
  useEffect(() => {
    checkAuth();
  }, []);

  const checkAuth = async () => {
    const token = localStorage.getItem('token');
    if (token) {
      try {
        // Set default auth header
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;

        // Verify token with backend
        const response = await axios.get('http://localhost:8000/api.php/user');
        setUser(response.data);
        setIsAuthenticated(true);
      } catch (error) {
        console.error('Auth check failed:', error);
        localStorage.removeItem('token');
        delete axios.defaults.headers.common['Authorization'];
        setIsAuthenticated(false);
      }
    }
    setLoading(false);
  };

  const login = async (email, password) => {
    try {
      const response = await axios.post('http://localhost:8000/api.php/login', {
        email,
        password
      });

      const { token, user } = response.data;

      // Store token
      localStorage.setItem('token', token);

      // Set default auth header
      axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;

      // Update state
      setUser(user);
      setIsAuthenticated(true);

      toast.success('Login effettuato con successo!');
      return { success: true };
    } catch (error) {
      console.error('Login failed:', error);
      const message = error.response?.data?.message || 'Credenziali non valide';
      toast.error(message);
      return { success: false, error: message };
    }
  };

  // Registrazione rimossa - solo admin può creare utenti
  // Mantenuta per compatibilità ma restituisce errore
  const register = async (userData) => {
    toast.error('La registrazione pubblica non è consentita. Contatta il tuo amministratore.');
    return { success: false, error: 'Registrazione non consentita' };
  };

  const logout = async () => {
    try {
      await axios.post('http://localhost:8000/api.php/logout');
    } catch (error) {
      console.error('Logout error:', error);
    } finally {
      // Clear local data
      localStorage.removeItem('token');
      delete axios.defaults.headers.common['Authorization'];

      // Reset state
      setUser(null);
      setIsAuthenticated(false);

      toast.success('Logout effettuato');
    }
  };

  const updateUser = (userData) => {
    setUser(userData);
  };

  const value = {
    user,
    loading,
    isAuthenticated,
    login,
    register,
    logout,
    updateUser,
    checkAuth
  };

  return (
    <AuthContext.Provider value={value}>
      {children}
    </AuthContext.Provider>
  );
};

export default AuthContext;