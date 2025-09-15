import { useState } from 'react';
import { Send, Paperclip, Smile, Phone, Video, MoreVertical, Search } from 'lucide-react';
import MainLayout from '../components/layout/MainLayout';

/**
 * Componente Chat placeholder
 */
const ChatComponent = () => {
  const [message, setMessage] = useState('');

  return (
    <div className="flex h-full">
      {/* Lista conversazioni */}
      <div className="w-80 border-r border-gray-200 dark:border-gray-700 bg-white dark:bg-dark-card">
        <div className="p-4 border-b border-gray-200 dark:border-gray-700">
          <div className="relative">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
            <input
              type="search"
              placeholder="Cerca conversazioni..."
              className="input pl-9"
            />
          </div>
        </div>
        <div className="overflow-y-auto">
          {[1, 2, 3, 4, 5].map(i => (
            <div key={i} className="p-4 hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer border-b border-gray-100 dark:border-gray-800">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 bg-primary rounded-full flex items-center justify-center">
                  <span className="text-white font-medium">U{i}</span>
                </div>
                <div className="flex-1">
                  <p className="font-medium text-gray-900 dark:text-white">Utente {i}</p>
                  <p className="text-sm text-gray-500 dark:text-gray-400 truncate">
                    Ultimo messaggio...
                  </p>
                </div>
                <span className="text-xs text-gray-500">10:30</span>
              </div>
            </div>
          ))}
        </div>
      </div>

      {/* Area chat */}
      <div className="flex-1 flex flex-col">
        {/* Header chat */}
        <div className="p-4 border-b border-gray-200 dark:border-gray-700 bg-white dark:bg-dark-card">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 bg-primary rounded-full flex items-center justify-center">
                <span className="text-white font-medium">U1</span>
              </div>
              <div>
                <p className="font-medium text-gray-900 dark:text-white">Utente 1</p>
                <p className="text-xs text-success">Online</p>
              </div>
            </div>
            <div className="flex items-center gap-2">
              <button className="p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg">
                <Phone className="h-5 w-5 text-gray-500" />
              </button>
              <button className="p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg">
                <Video className="h-5 w-5 text-gray-500" />
              </button>
              <button className="p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg">
                <MoreVertical className="h-5 w-5 text-gray-500" />
              </button>
            </div>
          </div>
        </div>

        {/* Messaggi */}
        <div className="flex-1 overflow-y-auto p-4 space-y-4">
          <div className="text-center text-sm text-gray-500 dark:text-gray-400">
            Oggi
          </div>

          {/* Messaggio ricevuto */}
          <div className="flex gap-3">
            <div className="w-8 h-8 bg-gray-300 rounded-full flex-shrink-0" />
            <div className="max-w-md">
              <div className="bg-gray-100 dark:bg-gray-800 rounded-lg p-3">
                <p className="text-sm text-gray-900 dark:text-white">
                  Ciao! Come stai?
                </p>
              </div>
              <p className="text-xs text-gray-500 mt-1">10:30</p>
            </div>
          </div>

          {/* Messaggio inviato */}
          <div className="flex gap-3 justify-end">
            <div className="max-w-md">
              <div className="bg-primary text-white rounded-lg p-3">
                <p className="text-sm">
                  Tutto bene, grazie! Tu?
                </p>
              </div>
              <p className="text-xs text-gray-500 mt-1 text-right">10:32</p>
            </div>
          </div>
        </div>

        {/* Input messaggio */}
        <div className="p-4 border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-dark-card">
          <div className="flex items-center gap-2">
            <button className="p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg">
              <Paperclip className="h-5 w-5 text-gray-500" />
            </button>
            <input
              type="text"
              value={message}
              onChange={(e) => setMessage(e.target.value)}
              placeholder="Scrivi un messaggio..."
              className="input flex-1"
            />
            <button className="p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg">
              <Smile className="h-5 w-5 text-gray-500" />
            </button>
            <button className="btn-primary p-2">
              <Send className="h-5 w-5" />
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};

const Chat = () => {
  return (
    <MainLayout>
      <ChatComponent />
    </MainLayout>
  );
};

export default Chat;