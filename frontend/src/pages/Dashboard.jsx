import { useState, useEffect } from 'react';
import { motion } from 'framer-motion';
import {
  Users,
  FileText,
  CheckSquare,
  Calendar,
  TrendingUp,
  TrendingDown,
  Clock,
  AlertCircle,
  Download,
  Eye,
  MessageSquare,
  Activity
} from 'lucide-react';
import {
  LineChart,
  Line,
  BarChart,
  Bar,
  PieChart,
  Pie,
  Cell,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  Legend,
  ResponsiveContainer
} from 'recharts';
import { useAuthStore } from '../stores/authStore';
import { format } from 'date-fns';
import { it } from 'date-fns/locale';

/**
 * Dashboard principale con widget statistiche e grafici
 */
const Dashboard = () => {
  const { user } = useAuthStore();
  const [stats, setStats] = useState({
    users: 156,
    documents: 1234,
    tasks: 89,
    events: 24
  });

  // Dati per grafici
  const activityData = [
    { name: 'Lun', tasks: 12, documents: 24, events: 3 },
    { name: 'Mar', tasks: 19, documents: 18, events: 5 },
    { name: 'Mer', tasks: 15, documents: 32, events: 2 },
    { name: 'Gio', tasks: 25, documents: 28, events: 8 },
    { name: 'Ven', tasks: 22, documents: 35, events: 4 },
    { name: 'Sab', tasks: 8, documents: 12, events: 1 },
    { name: 'Dom', tasks: 5, documents: 8, events: 2 }
  ];

  const taskStatusData = [
    { name: 'Completati', value: 45, color: '#10B981' },
    { name: 'In corso', value: 30, color: '#F59E0B' },
    { name: 'In attesa', value: 15, color: '#6B7280' },
    { name: 'Scaduti', value: 10, color: '#EF4444' }
  ];

  const recentFiles = [
    { id: 1, name: 'Report Q4 2024.pdf', size: '2.4 MB', date: '2 ore fa', type: 'pdf' },
    { id: 2, name: 'Presentazione.pptx', size: '5.1 MB', date: '5 ore fa', type: 'ppt' },
    { id: 3, name: 'Contratto_cliente.docx', size: '156 KB', date: 'Ieri', type: 'doc' },
    { id: 4, name: 'Budget_2025.xlsx', size: '890 KB', date: '2 giorni fa', type: 'xls' },
    { id: 5, name: 'Logo_nuovo.png', size: '245 KB', date: '3 giorni fa', type: 'img' }
  ];

  const upcomingEvents = [
    { id: 1, title: 'Meeting Team', time: '09:00', date: 'Oggi', type: 'meeting' },
    { id: 2, title: 'Presentazione Cliente', time: '14:30', date: 'Oggi', type: 'presentation' },
    { id: 3, title: 'Scadenza Progetto X', time: '18:00', date: 'Domani', type: 'deadline' },
    { id: 4, title: 'Call Conference', time: '10:00', date: 'Domani', type: 'call' }
  ];

  const recentActivities = [
    { id: 1, user: 'Mario Rossi', action: 'ha caricato', target: 'Report_vendite.pdf', time: '5 min fa' },
    { id: 2, user: 'Laura Bianchi', action: 'ha completato', target: 'Task #45', time: '15 min fa' },
    { id: 3, user: 'Giuseppe Verdi', action: 'ha commentato', target: 'Progetto Alpha', time: '1 ora fa' },
    { id: 4, user: 'Anna Neri', action: 'ha creato', target: 'Evento riunione', time: '2 ore fa' }
  ];

  // Widget statistiche
  const statsWidgets = [
    {
      title: 'Utenti Attivi',
      value: stats.users,
      change: '+12%',
      trend: 'up',
      icon: Users,
      color: 'primary'
    },
    {
      title: 'Documenti',
      value: stats.documents,
      change: '+8%',
      trend: 'up',
      icon: FileText,
      color: 'success'
    },
    {
      title: 'Task Attivi',
      value: stats.tasks,
      change: '-3%',
      trend: 'down',
      icon: CheckSquare,
      color: 'warning'
    },
    {
      title: 'Eventi',
      value: stats.events,
      change: '+24%',
      trend: 'up',
      icon: Calendar,
      color: 'error'
    }
  ];

  const containerVariants = {
    hidden: { opacity: 0 },
    visible: {
      opacity: 1,
      transition: {
        staggerChildren: 0.1
      }
    }
  };

  const itemVariants = {
    hidden: { y: 20, opacity: 0 },
    visible: {
      y: 0,
      opacity: 1,
      transition: {
        duration: 0.5
      }
    }
  };

  return (
    <motion.div
      variants={containerVariants}
      initial="hidden"
      animate="visible"
      className="space-y-6"
    >
      {/* Header */}
      <motion.div variants={itemVariants}>
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
          Benvenuto, {user?.name || 'Utente'}
        </h1>
        <p className="text-gray-600 dark:text-gray-400">
          {format(new Date(), 'EEEE d MMMM yyyy', { locale: it })}
        </p>
      </motion.div>

      {/* Stats Widgets */}
      <motion.div
        variants={itemVariants}
        className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4"
      >
        {statsWidgets.map((widget, index) => (
          <div key={index} className="card p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600 dark:text-gray-400">{widget.title}</p>
                <p className="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                  {widget.value.toLocaleString()}
                </p>
                <div className="flex items-center gap-1 mt-2">
                  {widget.trend === 'up' ? (
                    <TrendingUp className="h-4 w-4 text-success" />
                  ) : (
                    <TrendingDown className="h-4 w-4 text-error" />
                  )}
                  <span
                    className={`text-sm font-medium ${
                      widget.trend === 'up' ? 'text-success' : 'text-error'
                    }`}
                  >
                    {widget.change}
                  </span>
                  <span className="text-xs text-gray-500 dark:text-gray-400">vs mese scorso</span>
                </div>
              </div>
              <div
                className={`p-3 rounded-lg bg-${widget.color}-100 dark:bg-${widget.color}-900/20`}
              >
                <widget.icon className={`h-6 w-6 text-${widget.color}`} />
              </div>
            </div>
          </div>
        ))}
      </motion.div>

      {/* Grafici e attività */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Grafico attività */}
        <motion.div variants={itemVariants} className="lg:col-span-2">
          <div className="card p-6">
            <div className="flex items-center justify-between mb-4">
              <h2 className="text-lg font-semibold text-gray-900 dark:text-white">
                Attività Settimanale
              </h2>
              <Activity className="h-5 w-5 text-gray-400" />
            </div>
            <ResponsiveContainer width="100%" height={300}>
              <LineChart data={activityData}>
                <CartesianGrid strokeDasharray="3 3" stroke="#374151" />
                <XAxis dataKey="name" stroke="#9CA3AF" />
                <YAxis stroke="#9CA3AF" />
                <Tooltip
                  contentStyle={{
                    backgroundColor: '#1F2937',
                    border: 'none',
                    borderRadius: '8px'
                  }}
                />
                <Legend />
                <Line
                  type="monotone"
                  dataKey="tasks"
                  stroke="#2563EB"
                  strokeWidth={2}
                  name="Task"
                />
                <Line
                  type="monotone"
                  dataKey="documents"
                  stroke="#10B981"
                  strokeWidth={2}
                  name="Documenti"
                />
                <Line
                  type="monotone"
                  dataKey="events"
                  stroke="#F59E0B"
                  strokeWidth={2}
                  name="Eventi"
                />
              </LineChart>
            </ResponsiveContainer>
          </div>
        </motion.div>

        {/* Stato Task */}
        <motion.div variants={itemVariants}>
          <div className="card p-6">
            <div className="flex items-center justify-between mb-4">
              <h2 className="text-lg font-semibold text-gray-900 dark:text-white">
                Stato Task
              </h2>
              <CheckSquare className="h-5 w-5 text-gray-400" />
            </div>
            <ResponsiveContainer width="100%" height={300}>
              <PieChart>
                <Pie
                  data={taskStatusData}
                  cx="50%"
                  cy="50%"
                  innerRadius={60}
                  outerRadius={100}
                  paddingAngle={5}
                  dataKey="value"
                >
                  {taskStatusData.map((entry, index) => (
                    <Cell key={`cell-${index}`} fill={entry.color} />
                  ))}
                </Pie>
                <Tooltip
                  contentStyle={{
                    backgroundColor: '#1F2937',
                    border: 'none',
                    borderRadius: '8px'
                  }}
                />
              </PieChart>
            </ResponsiveContainer>
            <div className="mt-4 space-y-2">
              {taskStatusData.map((item, index) => (
                <div key={index} className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <div
                      className="w-3 h-3 rounded-full"
                      style={{ backgroundColor: item.color }}
                    />
                    <span className="text-sm text-gray-600 dark:text-gray-400">{item.name}</span>
                  </div>
                  <span className="text-sm font-medium text-gray-900 dark:text-white">
                    {item.value}
                  </span>
                </div>
              ))}
            </div>
          </div>
        </motion.div>
      </div>

      {/* File recenti, eventi e attività */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* File recenti */}
        <motion.div variants={itemVariants}>
          <div className="card p-6">
            <div className="flex items-center justify-between mb-4">
              <h2 className="text-lg font-semibold text-gray-900 dark:text-white">
                File Recenti
              </h2>
              <FileText className="h-5 w-5 text-gray-400" />
            </div>
            <div className="space-y-3">
              {recentFiles.map((file) => (
                <div
                  key={file.id}
                  className="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer transition-colors"
                >
                  <div className="flex items-center gap-3">
                    <FileText className="h-5 w-5 text-primary" />
                    <div>
                      <p className="text-sm font-medium text-gray-900 dark:text-white">
                        {file.name}
                      </p>
                      <p className="text-xs text-gray-500 dark:text-gray-400">
                        {file.size} • {file.date}
                      </p>
                    </div>
                  </div>
                  <div className="flex items-center gap-2">
                    <button className="p-1 hover:bg-gray-200 dark:hover:bg-gray-600 rounded">
                      <Eye className="h-4 w-4 text-gray-500 dark:text-gray-400" />
                    </button>
                    <button className="p-1 hover:bg-gray-200 dark:hover:bg-gray-600 rounded">
                      <Download className="h-4 w-4 text-gray-500 dark:text-gray-400" />
                    </button>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </motion.div>

        {/* Eventi prossimi */}
        <motion.div variants={itemVariants}>
          <div className="card p-6">
            <div className="flex items-center justify-between mb-4">
              <h2 className="text-lg font-semibold text-gray-900 dark:text-white">
                Prossimi Eventi
              </h2>
              <Calendar className="h-5 w-5 text-gray-400" />
            </div>
            <div className="space-y-3">
              {upcomingEvents.map((event) => (
                <div
                  key={event.id}
                  className="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg"
                >
                  <div className="flex-shrink-0">
                    <div className="w-10 h-10 bg-primary-100 dark:bg-primary-900/20 rounded-lg flex items-center justify-center">
                      <Clock className="h-5 w-5 text-primary" />
                    </div>
                  </div>
                  <div className="flex-1">
                    <p className="text-sm font-medium text-gray-900 dark:text-white">
                      {event.title}
                    </p>
                    <p className="text-xs text-gray-500 dark:text-gray-400">
                      {event.date} • {event.time}
                    </p>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </motion.div>

        {/* Attività recenti */}
        <motion.div variants={itemVariants}>
          <div className="card p-6">
            <div className="flex items-center justify-between mb-4">
              <h2 className="text-lg font-semibold text-gray-900 dark:text-white">
                Attività Recenti
              </h2>
              <MessageSquare className="h-5 w-5 text-gray-400" />
            </div>
            <div className="space-y-3">
              {recentActivities.map((activity) => (
                <div key={activity.id} className="flex gap-3">
                  <div className="flex-shrink-0">
                    <div className="w-8 h-8 bg-gray-200 dark:bg-gray-700 rounded-full flex items-center justify-center">
                      <span className="text-xs font-medium text-gray-600 dark:text-gray-300">
                        {activity.user.charAt(0)}
                      </span>
                    </div>
                  </div>
                  <div className="flex-1">
                    <p className="text-sm text-gray-900 dark:text-white">
                      <span className="font-medium">{activity.user}</span>{' '}
                      <span className="text-gray-600 dark:text-gray-400">{activity.action}</span>{' '}
                      <span className="font-medium">{activity.target}</span>
                    </p>
                    <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
                      {activity.time}
                    </p>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </motion.div>
      </div>
    </motion.div>
  );
};

export default Dashboard;