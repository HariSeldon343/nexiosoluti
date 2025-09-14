import { User } from 'lucide-react';

const Profile = () => {
  return (
    <div className="card p-8 text-center">
      <User className="h-12 w-12 text-gray-400 mx-auto mb-4" />
      <h2 className="text-xl font-semibold text-gray-900 dark:text-white mb-2">
        Profilo Utente
      </h2>
      <p className="text-gray-500 dark:text-gray-400">
        Modulo in costruzione
      </p>
    </div>
  );
};

export default Profile;