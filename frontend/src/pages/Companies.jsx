import React, { useState } from 'react';
import MainLayout from '../components/layout/MainLayout';
import { Building, Plus, Search, Edit, Trash2, Eye, MapPin, Phone, Mail, Globe } from 'lucide-react';

const Companies = () => {
  const [searchTerm, setSearchTerm] = useState('');

  // Demo companies data
  const companies = [
    {
      id: 1,
      name: 'Tech Solutions Srl',
      vat: 'IT12345678901',
      address: 'Via Roma 123, Milano',
      phone: '+39 02 1234567',
      email: 'info@techsolutions.it',
      website: 'www.techsolutions.it',
      employees: 50,
      status: 'active'
    },
    {
      id: 2,
      name: 'Digital Marketing Agency',
      vat: 'IT98765432109',
      address: 'Corso Italia 45, Roma',
      phone: '+39 06 9876543',
      email: 'contact@digitalagency.it',
      website: 'www.digitalagency.it',
      employees: 25,
      status: 'active'
    },
    {
      id: 3,
      name: 'Consulting Group SpA',
      vat: 'IT11122233344',
      address: 'Piazza Duomo 1, Firenze',
      phone: '+39 055 1112233',
      email: 'info@consultinggroup.it',
      website: 'www.consultinggroup.it',
      employees: 120,
      status: 'active'
    },
    {
      id: 4,
      name: 'Innovation Lab',
      vat: 'IT55566677788',
      address: 'Via Torino 89, Torino',
      phone: '+39 011 5556677',
      email: 'hello@innovationlab.it',
      website: 'www.innovationlab.it',
      employees: 15,
      status: 'inactive'
    }
  ];

  const filteredCompanies = companies.filter(company =>
    company.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
    company.vat.toLowerCase().includes(searchTerm.toLowerCase())
  );

  return (
    <MainLayout>
      <div className="p-6">
        {/* Header */}
        <div className="mb-6">
          <h1 className="text-2xl font-bold text-gray-900">Gestione Aziende</h1>
          <p className="text-gray-600 mt-1">Gestisci le aziende e i loro dati</p>
        </div>

        {/* Stats */}
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
          <div className="bg-white rounded-lg shadow p-4 border border-gray-200">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600">Totale Aziende</p>
                <p className="text-2xl font-bold text-gray-900">{companies.length}</p>
              </div>
              <Building className="w-8 h-8 text-blue-500" />
            </div>
          </div>
          <div className="bg-white rounded-lg shadow p-4 border border-gray-200">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600">Attive</p>
                <p className="text-2xl font-bold text-green-600">
                  {companies.filter(c => c.status === 'active').length}
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
                <p className="text-sm text-gray-600">Inattive</p>
                <p className="text-2xl font-bold text-gray-600">
                  {companies.filter(c => c.status === 'inactive').length}
                </p>
              </div>
              <div className="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center">
                <div className="w-3 h-3 bg-gray-500 rounded-full"></div>
              </div>
            </div>
          </div>
          <div className="bg-white rounded-lg shadow p-4 border border-gray-200">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600">Dipendenti Tot.</p>
                <p className="text-2xl font-bold text-blue-600">
                  {companies.reduce((sum, c) => sum + c.employees, 0)}
                </p>
              </div>
              <div className="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                <span className="text-blue-600 text-sm font-bold">Σ</span>
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
                  placeholder="Cerca aziende per nome o P.IVA..."
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                />
              </div>
            </div>
            <button className="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center gap-2">
              <Plus className="w-5 h-5" />
              Nuova Azienda
            </button>
          </div>
        </div>

        {/* Companies List */}
        <div className="bg-white rounded-lg shadow border border-gray-200">
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead className="bg-gray-50 border-b border-gray-200">
                <tr>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Azienda
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    P.IVA
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Contatti
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Dipendenti
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Stato
                  </th>
                  <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Azioni
                  </th>
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-gray-200">
                {filteredCompanies.map((company) => (
                  <tr key={company.id} className="hover:bg-gray-50">
                    <td className="px-6 py-4">
                      <div>
                        <div className="text-sm font-medium text-gray-900">{company.name}</div>
                        <div className="text-sm text-gray-500 flex items-center gap-1 mt-1">
                          <MapPin className="w-3 h-3" />
                          {company.address}
                        </div>
                      </div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                      {company.vat}
                    </td>
                    <td className="px-6 py-4">
                      <div className="text-sm text-gray-900">
                        <div className="flex items-center gap-1">
                          <Phone className="w-3 h-3 text-gray-400" />
                          {company.phone}
                        </div>
                        <div className="flex items-center gap-1 mt-1">
                          <Mail className="w-3 h-3 text-gray-400" />
                          {company.email}
                        </div>
                      </div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                      {company.employees}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <span className={`px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full ${
                        company.status === 'active'
                          ? 'bg-green-100 text-green-800'
                          : 'bg-gray-100 text-gray-800'
                      }`}>
                        {company.status === 'active' ? 'Attiva' : 'Inattiva'}
                      </span>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                      <div className="flex justify-end gap-2">
                        <button className="text-gray-400 hover:text-blue-600">
                          <Eye className="w-5 h-5" />
                        </button>
                        <button className="text-gray-400 hover:text-blue-600">
                          <Edit className="w-5 h-5" />
                        </button>
                        <button className="text-gray-400 hover:text-red-600">
                          <Trash2 className="w-5 h-5" />
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </MainLayout>
  );
};

export default Companies;