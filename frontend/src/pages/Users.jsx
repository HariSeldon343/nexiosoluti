import React, { useState } from 'react';
import MainLayout from '../components/layout/MainLayout';
import { Users as UsersIcon, Plus, Search, Edit, Trash2, Shield, Mail, Phone, Calendar, MoreVertical } from 'lucide-react';

const Users = () => {
  const [searchTerm, setSearchTerm] = useState('');

  // Demo users data
  const users = [
    {
      id: 1,
      name: 'Mario Rossi',
      email: 'mario.rossi@example.com',
      role: 'Admin',
      department: 'IT',
      status: 'active',
      lastLogin: '2024-01-14 10:30',
      avatar: null
    },
    {
      id: 2,
      name: 'Laura Bianchi',
      email: 'laura.bianchi@example.com',
      role: 'Manager',
      department: 'Sales',
      status: 'active',
      lastLogin: '2024-01-14 09:15',
      avatar: null
    },
    {
      id: 3,
      name: 'Giuseppe Verdi',
      email: 'giuseppe.verdi@example.com',
      role: 'User',
      department: 'Marketing',
      status: 'active',
      lastLogin: '2024-01-13 14:20',
      avatar: null
    },
    {
      id: 4,
      name: 'Anna Neri',
      email: 'anna.neri@example.com',
      role: 'User',
      department: 'HR',
      status: 'inactive',
      lastLogin: '2024-01-10 11:45',
      avatar: null
    },
    {
      id: 5,
      name: 'Marco Blu',
      email: 'marco.blu@example.com',
      role: 'Manager',
      department: 'Finance',
      status: 'active',
      lastLogin: '2024-01-14 08:00',
      avatar: null
    }
  ];

  const filteredUsers = users.filter(user =>
    user.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
    user.email.toLowerCase().includes(searchTerm.toLowerCase()) ||
    user.role.toLowerCase().includes(searchTerm.toLowerCase())
  );

  const getRoleBadgeColor = (role) => {
    switch(role) {
      case 'Admin':
        return 'bg-red-100 text-red-800';
      case 'Manager':
        return 'bg-blue-100 text-blue-800';
      default:
        return 'bg-gray-100 text-gray-800';
    }
  };

  return (
    <MainLayout>
      <div className="p-6">
        {/* Header */}
        <div className="mb-6">
          <h1 className="text-2xl font-bold text-gray-900">Gestione Utenti</h1>
          <p className="text-gray-600 mt-1">Gestisci gli utenti e i loro permessi</p>
        </div>

        {/* Stats */}
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
          <div className="bg-white rounded-lg shadow p-4 border border-gray-200">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600">Totale Utenti</p>
                <p className="text-2xl font-bold text-gray-900">{users.length}</p>
              </div>
              <UsersIcon className="w-8 h-8 text-blue-500" />
            </div>
          </div>
          <div className="bg-white rounded-lg shadow p-4 border border-gray-200">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600">Attivi</p>
                <p className="text-2xl font-bold text-green-600">
                  {users.filter(u => u.status === 'active').length}
                </p>
              </div>
              <div className="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                <div className="w-3 h-3 bg-green-500 rounded-full"></div>
              </div>
            </div>
          </div>
          <div className="bg-white rounded-lg shadow p-4 border border-gray-200">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600">Admin</p>
                <p className="text-2xl font-bold text-red-600">
                  {users.filter(u => u.role === 'Admin').length}
                </p>
              </div>
              <Shield className="w-8 h-8 text-red-500" />
            </div>
          </div>
          <div className="bg-white rounded-lg shadow p-4 border border-gray-200">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600">Manager</p>
                <p className="text-2xl font-bold text-blue-600">
                  {users.filter(u => u.role === 'Manager').length}
                </p>
              </div>
              <div className="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                <span className="text-blue-600 text-sm font-bold">M</span>
              </div>
            </div>
          </div>
        </div>

        {/* Search and Actions */}
        <div className="bg-white rounded-lg shadow border border-gray-200 p-4 mb-6">
          <div className="flex flex-col md:flex-row gap-4">
            <div className="flex-1">
              <div className="relative">
                <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-5 h-5" />
                <input
                  type="text"
                  placeholder="Cerca utenti per nome, email o ruolo..."
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                />
              </div>
            </div>
            <button className="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center gap-2">
              <Plus className="w-5 h-5" />
              Nuovo Utente
            </button>
          </div>
        </div>

        {/* Users Grid */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {filteredUsers.map((user) => (
            <div key={user.id} className="bg-white rounded-lg shadow border border-gray-200 p-4">
              <div className="flex items-start justify-between">
                <div className="flex items-center gap-3">
                  <div className="w-12 h-12 bg-gray-300 rounded-full flex items-center justify-center">
                    <span className="text-gray-600 font-semibold text-lg">
                      {user.name.split(' ').map(n => n[0]).join('')}
                    </span>
                  </div>
                  <div>
                    <h3 className="font-semibold text-gray-900">{user.name}</h3>
                    <p className="text-sm text-gray-600">{user.department}</p>
                  </div>
                </div>
                <button className="text-gray-400 hover:text-gray-600">
                  <MoreVertical className="w-5 h-5" />
                </button>
              </div>

              <div className="mt-4 space-y-2">
                <div className="flex items-center gap-2 text-sm text-gray-600">
                  <Mail className="w-4 h-4" />
                  <span>{user.email}</span>
                </div>
                <div className="flex items-center gap-2 text-sm text-gray-600">
                  <Calendar className="w-4 h-4" />
                  <span>Ultimo accesso: {user.lastLogin}</span>
                </div>
              </div>

              <div className="mt-4 flex items-center justify-between">
                <div className="flex items-center gap-2">
                  <span className={`px-2 py-1 text-xs font-semibold rounded-full ${getRoleBadgeColor(user.role)}`}>
                    {user.role}
                  </span>
                  <span className={`px-2 py-1 text-xs font-semibold rounded-full ${
                    user.status === 'active'
                      ? 'bg-green-100 text-green-800'
                      : 'bg-gray-100 text-gray-800'
                  }`}>
                    {user.status === 'active' ? 'Attivo' : 'Inattivo'}
                  </span>
                </div>
                <div className="flex gap-1">
                  <button className="p-1 text-gray-400 hover:text-blue-600 rounded">
                    <Edit className="w-4 h-4" />
                  </button>
                  <button className="p-1 text-gray-400 hover:text-red-600 rounded">
                    <Trash2 className="w-4 h-4" />
                  </button>
                </div>
              </div>
            </div>
          ))}
        </div>
      </div>
    </MainLayout>
  );
};

export default Users;