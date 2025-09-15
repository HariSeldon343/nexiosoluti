import React, { useState, useEffect } from 'react';
import MainLayout from '../components/layout/MainLayout';
import { Plus, Search, Filter, Calendar, Clock, CheckCircle, Circle, AlertCircle } from 'lucide-react';
import { tasksAPI } from '../services/api';
import toast from 'react-hot-toast';

const Tasks = () => {
  const [tasks, setTasks] = useState([]);
  const [loading, setLoading] = useState(true);
  const [searchTerm, setSearchTerm] = useState('');
  const [filterStatus, setFilterStatus] = useState('all');
  const [showNewTaskModal, setShowNewTaskModal] = useState(false);

  // Demo tasks data
  const demoTasks = [
    {
      id: 1,
      title: 'Completare il report trimestrale',
      description: 'Preparare e inviare il report Q4 2024',
      status: 'in_progress',
      priority: 'high',
      assignee: 'Mario Rossi',
      dueDate: '2024-01-15',
      tags: ['report', 'urgente']
    },
    {
      id: 2,
      title: 'Revisione codice modulo pagamenti',
      description: 'Controllare e ottimizzare il codice del nuovo sistema di pagamenti',
      status: 'pending',
      priority: 'medium',
      assignee: 'Laura Bianchi',
      dueDate: '2024-01-20',
      tags: ['sviluppo', 'review']
    },
    {
      id: 3,
      title: 'Meeting con il cliente ABC',
      description: 'Discutere i requisiti del nuovo progetto',
      status: 'completed',
      priority: 'high',
      assignee: 'Giuseppe Verdi',
      dueDate: '2024-01-10',
      tags: ['meeting', 'cliente']
    },
    {
      id: 4,
      title: 'Aggiornamento documentazione API',
      description: 'Aggiornare la documentazione delle API REST',
      status: 'in_progress',
      priority: 'low',
      assignee: 'Anna Neri',
      dueDate: '2024-01-25',
      tags: ['documentazione']
    },
    {
      id: 5,
      title: 'Test di sicurezza applicazione',
      description: 'Eseguire penetration test e vulnerability assessment',
      status: 'pending',
      priority: 'high',
      assignee: 'Marco Blu',
      dueDate: '2024-01-18',
      tags: ['sicurezza', 'test']
    }
  ];

  useEffect(() => {
    loadTasks();
  }, []);

  const loadTasks = async () => {
    try {
      // Simula caricamento
      setTimeout(() => {
        setTasks(demoTasks);
        setLoading(false);
      }, 500);
    } catch (error) {
      console.error('Error loading tasks:', error);
      setTasks(demoTasks);
      setLoading(false);
    }
  };

  const getStatusIcon = (status) => {
    switch (status) {
      case 'completed':
        return <CheckCircle className="w-5 h-5 text-green-500" />;
      case 'in_progress':
        return <Clock className="w-5 h-5 text-blue-500" />;
      case 'pending':
        return <Circle className="w-5 h-5 text-gray-400" />;
      default:
        return <AlertCircle className="w-5 h-5 text-red-500" />;
    }
  };

  const getStatusLabel = (status) => {
    switch (status) {
      case 'completed':
        return 'Completato';
      case 'in_progress':
        return 'In corso';
      case 'pending':
        return 'In attesa';
      default:
        return 'Sconosciuto';
    }
  };

  const getPriorityColor = (priority) => {
    switch (priority) {
      case 'high':
        return 'text-red-600 bg-red-50';
      case 'medium':
        return 'text-yellow-600 bg-yellow-50';
      case 'low':
        return 'text-green-600 bg-green-50';
      default:
        return 'text-gray-600 bg-gray-50';
    }
  };

  const filteredTasks = tasks.filter(task => {
    const matchesSearch = task.title.toLowerCase().includes(searchTerm.toLowerCase()) ||
                          task.description.toLowerCase().includes(searchTerm.toLowerCase());
    const matchesFilter = filterStatus === 'all' || task.status === filterStatus;
    return matchesSearch && matchesFilter;
  });

  const taskStats = {
    total: tasks.length,
    completed: tasks.filter(t => t.status === 'completed').length,
    inProgress: tasks.filter(t => t.status === 'in_progress').length,
    pending: tasks.filter(t => t.status === 'pending').length
  };

  return (
    <MainLayout>
      <div className="p-6">
        {/* Header */}
        <div className="mb-6">
          <h1 className="text-2xl font-bold text-gray-900">Gestione Attività</h1>
          <p className="text-gray-600 mt-1">Organizza e monitora le tue attività</p>
        </div>

        {/* Stats Cards */}
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
          <div className="bg-white rounded-lg shadow p-4 border border-gray-200">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600">Totale</p>
                <p className="text-2xl font-bold text-gray-900">{taskStats.total}</p>
              </div>
              <div className="p-3 bg-gray-100 rounded-lg">
                <AlertCircle className="w-6 h-6 text-gray-600" />
              </div>
            </div>
          </div>
          <div className="bg-white rounded-lg shadow p-4 border border-gray-200">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600">Completate</p>
                <p className="text-2xl font-bold text-green-600">{taskStats.completed}</p>
              </div>
              <div className="p-3 bg-green-100 rounded-lg">
                <CheckCircle className="w-6 h-6 text-green-600" />
              </div>
            </div>
          </div>
          <div className="bg-white rounded-lg shadow p-4 border border-gray-200">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600">In corso</p>
                <p className="text-2xl font-bold text-blue-600">{taskStats.inProgress}</p>
              </div>
              <div className="p-3 bg-blue-100 rounded-lg">
                <Clock className="w-6 h-6 text-blue-600" />
              </div>
            </div>
          </div>
          <div className="bg-white rounded-lg shadow p-4 border border-gray-200">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600">In attesa</p>
                <p className="text-2xl font-bold text-gray-600">{taskStats.pending}</p>
              </div>
              <div className="p-3 bg-gray-100 rounded-lg">
                <Circle className="w-6 h-6 text-gray-600" />
              </div>
            </div>
          </div>
        </div>

        {/* Filters and Actions */}
        <div className="bg-white rounded-lg shadow border border-gray-200 p-4 mb-6">
          <div className="flex flex-col md:flex-row gap-4">
            <div className="flex-1">
              <div className="relative">
                <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-5 h-5" />
                <input
                  type="text"
                  placeholder="Cerca attività..."
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                />
              </div>
            </div>
            <div className="flex gap-2">
              <select
                value={filterStatus}
                onChange={(e) => setFilterStatus(e.target.value)}
                className="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              >
                <option value="all">Tutte</option>
                <option value="pending">In attesa</option>
                <option value="in_progress">In corso</option>
                <option value="completed">Completate</option>
              </select>
              <button
                onClick={() => setShowNewTaskModal(true)}
                className="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center gap-2"
              >
                <Plus className="w-5 h-5" />
                Nuova Attività
              </button>
            </div>
          </div>
        </div>

        {/* Tasks List */}
        <div className="bg-white rounded-lg shadow border border-gray-200">
          {loading ? (
            <div className="p-8 text-center">
              <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
              <p className="mt-4 text-gray-600">Caricamento attività...</p>
            </div>
          ) : filteredTasks.length === 0 ? (
            <div className="p-8 text-center">
              <AlertCircle className="w-12 h-12 text-gray-400 mx-auto mb-4" />
              <p className="text-gray-600">Nessuna attività trovata</p>
            </div>
          ) : (
            <div className="divide-y divide-gray-200">
              {filteredTasks.map((task) => (
                <div key={task.id} className="p-4 hover:bg-gray-50 transition-colors">
                  <div className="flex items-start gap-4">
                    <div className="mt-1">
                      {getStatusIcon(task.status)}
                    </div>
                    <div className="flex-1">
                      <div className="flex items-start justify-between">
                        <div>
                          <h3 className="text-lg font-semibold text-gray-900">{task.title}</h3>
                          <p className="text-gray-600 mt-1">{task.description}</p>
                          <div className="flex items-center gap-4 mt-3">
                            <div className="flex items-center gap-1 text-sm text-gray-500">
                              <Calendar className="w-4 h-4" />
                              <span>Scadenza: {new Date(task.dueDate).toLocaleDateString('it-IT')}</span>
                            </div>
                            <div className="text-sm text-gray-500">
                              Assegnato a: <span className="font-medium">{task.assignee}</span>
                            </div>
                          </div>
                          <div className="flex items-center gap-2 mt-2">
                            <span className={`px-2 py-1 text-xs font-medium rounded-full ${getPriorityColor(task.priority)}`}>
                              {task.priority === 'high' ? 'Alta' : task.priority === 'medium' ? 'Media' : 'Bassa'} priorità
                            </span>
                            <span className="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-700">
                              {getStatusLabel(task.status)}
                            </span>
                            {task.tags.map((tag, index) => (
                              <span key={index} className="px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-700">
                                #{tag}
                              </span>
                            ))}
                          </div>
                        </div>
                        <div className="flex gap-2">
                          <button className="text-gray-400 hover:text-gray-600">
                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                          </button>
                          <button className="text-gray-400 hover:text-red-600">
                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>
    </MainLayout>
  );
};

export default Tasks;