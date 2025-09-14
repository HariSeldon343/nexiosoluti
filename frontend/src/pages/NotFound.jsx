import { useNavigate } from 'react-router-dom';
import { Home, ArrowLeft } from 'lucide-react';

const NotFound = () => {
  const navigate = useNavigate();

  return (
    <div className="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-dark-background">
      <div className="text-center">
        <h1 className="text-9xl font-bold text-gray-200 dark:text-gray-700">404</h1>
        <h2 className="text-2xl font-semibold text-gray-900 dark:text-white mt-4">
          Pagina non trovata
        </h2>
        <p className="text-gray-600 dark:text-gray-400 mt-2">
          La pagina che stai cercando non esiste o è stata spostata.
        </p>
        <div className="flex items-center justify-center gap-4 mt-8">
          <button
            onClick={() => navigate(-1)}
            className="btn-ghost flex items-center gap-2"
          >
            <ArrowLeft className="h-4 w-4" />
            Torna indietro
          </button>
          <button
            onClick={() => navigate('/dashboard')}
            className="btn-primary flex items-center gap-2"
          >
            <Home className="h-4 w-4" />
            Vai alla Dashboard
          </button>
        </div>
      </div>
    </div>
  );
};

export default NotFound;