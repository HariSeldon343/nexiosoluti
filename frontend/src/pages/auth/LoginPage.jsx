import { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { motion } from 'framer-motion';
import { Mail, Lock, Eye, EyeOff, AlertCircle, Loader2 } from 'lucide-react';
import { useAuthStore } from '../../stores/authStore';
import toast from 'react-hot-toast';

/**
 * Pagina di login con supporto 2FA
 */
const LoginPage = () => {
  const navigate = useNavigate();
  const { login, verify2FA, isLoading, error, clearError } = useAuthStore();
  const [showPassword, setShowPassword] = useState(false);
  const [requires2FA, setRequires2FA] = useState(false);
  const [email, setEmail] = useState('');

  const {
    register,
    handleSubmit,
    formState: { errors }
  } = useForm();

  const {
    register: register2FA,
    handleSubmit: handleSubmit2FA,
    formState: { errors: errors2FA }
  } = useForm();

  // Gestione login
  const onSubmitLogin = async (data) => {
    clearError();
    setEmail(data.email);

    const result = await login(data.email, data.password);

    if (result.success) {
      if (result.requires2FA) {
        setRequires2FA(true);
        toast.success('Inserisci il codice 2FA');
      } else {
        toast.success('Login effettuato con successo');
        navigate('/dashboard');
      }
    } else {
      toast.error(result.error || 'Errore durante il login');
    }
  };

  // Gestione 2FA
  const onSubmit2FA = async (data) => {
    clearError();

    const result = await verify2FA(data.code);

    if (result.success) {
      toast.success('Autenticazione completata');
      navigate('/dashboard');
    } else {
      toast.error(result.error || 'Codice 2FA non valido');
    }
  };

  return (
    <div className="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-dark-background py-12 px-4 sm:px-6 lg:px-8">
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.5 }}
        className="max-w-md w-full space-y-8"
      >
        {/* Logo e titolo */}
        <div className="text-center">
          <div className="flex justify-center mb-4">
            <div className="w-16 h-16 bg-primary rounded-2xl flex items-center justify-center">
              <span className="text-white font-bold text-3xl">N</span>
            </div>
          </div>
          <h2 className="text-3xl font-bold text-gray-900 dark:text-white">
            NexioSolution
          </h2>
          <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">
            {requires2FA ? 'Verifica a due fattori' : 'Accedi al tuo account'}
          </p>
        </div>

        {/* Form container */}
        <div className="card p-8">
          {!requires2FA ? (
            // Form di login
            <form onSubmit={handleSubmit(onSubmitLogin)} className="space-y-6">
              {/* Email */}
              <div>
                <label htmlFor="email" className="label">
                  Email
                </label>
                <div className="relative">
                  <Mail className="absolute left-3 top-1/2 -translate-y-1/2 h-5 w-5 text-gray-400" />
                  <input
                    {...register('email', {
                      required: 'Email richiesta',
                      pattern: {
                        value: /^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}$/i,
                        message: 'Email non valida'
                      }
                    })}
                    type="email"
                    autoComplete="email"
                    className={`input pl-10 ${errors.email ? 'input-error' : ''}`}
                    placeholder="nome@azienda.com"
                  />
                </div>
                {errors.email && (
                  <p className="mt-1 text-sm text-error flex items-center gap-1">
                    <AlertCircle className="h-4 w-4" />
                    {errors.email.message}
                  </p>
                )}
              </div>

              {/* Password */}
              <div>
                <label htmlFor="password" className="label">
                  Password
                </label>
                <div className="relative">
                  <Lock className="absolute left-3 top-1/2 -translate-y-1/2 h-5 w-5 text-gray-400" />
                  <input
                    {...register('password', {
                      required: 'Password richiesta',
                      minLength: {
                        value: 6,
                        message: 'La password deve essere di almeno 6 caratteri'
                      }
                    })}
                    type={showPassword ? 'text' : 'password'}
                    autoComplete="current-password"
                    className={`input pl-10 pr-10 ${errors.password ? 'input-error' : ''}`}
                    placeholder="••••••••"
                  />
                  <button
                    type="button"
                    onClick={() => setShowPassword(!showPassword)}
                    className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                  >
                    {showPassword ? (
                      <EyeOff className="h-5 w-5" />
                    ) : (
                      <Eye className="h-5 w-5" />
                    )}
                  </button>
                </div>
                {errors.password && (
                  <p className="mt-1 text-sm text-error flex items-center gap-1">
                    <AlertCircle className="h-4 w-4" />
                    {errors.password.message}
                  </p>
                )}
              </div>

              {/* Remember me e password dimenticata */}
              <div className="flex items-center justify-between">
                <div className="flex items-center">
                  <input
                    id="remember-me"
                    name="remember-me"
                    type="checkbox"
                    className="h-4 w-4 text-primary focus:ring-primary border-gray-300 rounded"
                  />
                  <label htmlFor="remember-me" className="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                    Ricordami
                  </label>
                </div>

                <Link
                  to="/forgot-password"
                  className="text-sm text-primary hover:text-primary-600"
                >
                  Password dimenticata?
                </Link>
              </div>

              {/* Errore generale */}
              {error && (
                <div className="p-3 bg-error-50 dark:bg-error-900/20 border border-error-200 dark:border-error-800 rounded-lg">
                  <p className="text-sm text-error flex items-center gap-2">
                    <AlertCircle className="h-4 w-4" />
                    {error}
                  </p>
                </div>
              )}

              {/* Pulsante submit */}
              <button
                type="submit"
                disabled={isLoading}
                className="btn-primary w-full"
              >
                {isLoading ? (
                  <>
                    <Loader2 className="h-5 w-5 animate-spin mr-2" />
                    Accesso in corso...
                  </>
                ) : (
                  'Accedi'
                )}
              </button>
            </form>
          ) : (
            // Form 2FA
            <form onSubmit={handleSubmit2FA(onSubmit2FA)} className="space-y-6">
              <div>
                <label htmlFor="code" className="label">
                  Codice di verifica
                </label>
                <input
                  {...register2FA('code', {
                    required: 'Codice richiesto',
                    pattern: {
                      value: /^[0-9]{6}$/,
                      message: 'Il codice deve essere di 6 cifre'
                    }
                  })}
                  type="text"
                  autoComplete="one-time-code"
                  className={`input text-center text-2xl tracking-widest ${errors2FA.code ? 'input-error' : ''}`}
                  placeholder="000000"
                  maxLength={6}
                />
                {errors2FA.code && (
                  <p className="mt-1 text-sm text-error flex items-center gap-1">
                    <AlertCircle className="h-4 w-4" />
                    {errors2FA.code.message}
                  </p>
                )}
                <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
                  Inserisci il codice dalla tua app di autenticazione
                </p>
              </div>

              {/* Errore generale */}
              {error && (
                <div className="p-3 bg-error-50 dark:bg-error-900/20 border border-error-200 dark:border-error-800 rounded-lg">
                  <p className="text-sm text-error flex items-center gap-2">
                    <AlertCircle className="h-4 w-4" />
                    {error}
                  </p>
                </div>
              )}

              <div className="space-y-3">
                <button
                  type="submit"
                  disabled={isLoading}
                  className="btn-primary w-full"
                >
                  {isLoading ? (
                    <>
                      <Loader2 className="h-5 w-5 animate-spin mr-2" />
                      Verifica in corso...
                    </>
                  ) : (
                    'Verifica'
                  )}
                </button>

                <button
                  type="button"
                  onClick={() => setRequires2FA(false)}
                  className="btn-ghost w-full"
                >
                  Torna al login
                </button>
              </div>
            </form>
          )}

          {/* Link registrazione */}
          {!requires2FA && (
            <div className="mt-6 text-center">
              <p className="text-sm text-gray-600 dark:text-gray-400">
                Non hai un account?{' '}
                <Link to="/register" className="font-medium text-primary hover:text-primary-600">
                  Registrati
                </Link>
              </p>
            </div>
          )}
        </div>
      </motion.div>
    </div>
  );
};

export default LoginPage;