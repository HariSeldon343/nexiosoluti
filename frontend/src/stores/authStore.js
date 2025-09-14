import { create } from 'zustand';
import { persist } from 'zustand/middleware';
import axios from 'axios';

/**
 * Store per la gestione dell'autenticazione e dell'utente
 */
const useAuthStore = create(
  persist(
    (set, get) => ({
      user: null,
      token: null,
      refreshToken: null,
      tenant: null,
      isAuthenticated: false,
      isLoading: false,
      error: null,

      // Login
      login: async (email, password) => {
        set({ isLoading: true, error: null });
        try {
          const response = await axios.post('/api/auth/login', { email, password });
          const { user, token, refreshToken, tenant } = response.data;

          set({
            user,
            token,
            refreshToken,
            tenant,
            isAuthenticated: true,
            isLoading: false
          });

          // Imposta token per richieste future
          axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;

          return { success: true };
        } catch (error) {
          set({
            error: error.response?.data?.message || 'Errore durante il login',
            isLoading: false
          });
          return { success: false, error: error.response?.data?.message };
        }
      },

      // Login con 2FA
      verify2FA: async (code) => {
        set({ isLoading: true, error: null });
        try {
          const response = await axios.post('/api/auth/verify-2fa', {
            code,
            email: get().user?.email
          });

          const { token, refreshToken } = response.data;

          set({
            token,
            refreshToken,
            isAuthenticated: true,
            isLoading: false
          });

          axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;

          return { success: true };
        } catch (error) {
          set({
            error: error.response?.data?.message || 'Codice 2FA non valido',
            isLoading: false
          });
          return { success: false, error: error.response?.data?.message };
        }
      },

      // Logout
      logout: () => {
        set({
          user: null,
          token: null,
          refreshToken: null,
          tenant: null,
          isAuthenticated: false,
          error: null
        });

        delete axios.defaults.headers.common['Authorization'];
      },

      // Refresh token
      refreshAccessToken: async () => {
        const refreshToken = get().refreshToken;
        if (!refreshToken) {
          get().logout();
          return null;
        }

        try {
          const response = await axios.post('/api/auth/refresh', { refreshToken });
          const { token } = response.data;

          set({ token });
          axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;

          return token;
        } catch (error) {
          get().logout();
          return null;
        }
      },

      // Update user profile
      updateProfile: (userData) => {
        set(state => ({
          user: { ...state.user, ...userData }
        }));
      },

      // Check auth status
      checkAuth: async () => {
        const token = get().token;
        if (!token) {
          set({ isAuthenticated: false });
          return false;
        }

        try {
          const response = await axios.get('/api/auth/me');
          set({
            user: response.data.user,
            isAuthenticated: true
          });
          return true;
        } catch (error) {
          if (error.response?.status === 401) {
            const newToken = await get().refreshAccessToken();
            if (newToken) {
              return get().checkAuth();
            }
          }
          get().logout();
          return false;
        }
      },

      // Reset password
      resetPassword: async (email) => {
        set({ isLoading: true, error: null });
        try {
          await axios.post('/api/auth/reset-password', { email });
          set({ isLoading: false });
          return { success: true };
        } catch (error) {
          set({
            error: error.response?.data?.message || 'Errore durante il reset password',
            isLoading: false
          });
          return { success: false, error: error.response?.data?.message };
        }
      },

      // Clear error
      clearError: () => set({ error: null })
    }),
    {
      name: 'auth-storage',
      partialize: (state) => ({
        user: state.user,
        token: state.token,
        refreshToken: state.refreshToken,
        tenant: state.tenant,
        isAuthenticated: state.isAuthenticated
      })
    }
  )
);

export { useAuthStore };