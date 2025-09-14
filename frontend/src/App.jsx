import React from 'react'

function App() {
  return (
    <div className="min-h-screen bg-gray-100">
      <div className="container mx-auto p-8">
        <h1 className="text-4xl font-bold text-gray-800 mb-4">
          NexioSolution
        </h1>
        <p className="text-lg text-gray-600">
          Piattaforma Collaborativa Multi-Tenant
        </p>
        <div className="mt-8 p-6 bg-white rounded-lg shadow">
          <h2 className="text-2xl font-semibold mb-4">Benvenuto!</h2>
          <p>La piattaforma è attiva e funzionante.</p>
          <div className="mt-4 space-x-4">
            <button className="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
              Accedi
            </button>
            <button className="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
              Info
            </button>
          </div>
          <div className="mt-6 p-4 bg-blue-50 rounded">
            <h3 className="font-semibold text-blue-900">Stato Sistema</h3>
            <ul className="mt-2 space-y-1 text-sm text-blue-700">
              <li>✓ Frontend React attivo</li>
              <li>✓ Tailwind CSS configurato</li>
              <li>✓ Vite Dev Server in esecuzione</li>
              <li>✓ Hot Module Replacement attivo</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  )
}

export default App
