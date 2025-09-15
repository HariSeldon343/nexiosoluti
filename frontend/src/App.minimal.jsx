import React, { useState } from 'react';

function App() {
  const [count, setCount] = useState(0);
  const [message, setMessage] = useState('');

  const testBackend = async () => {
    try {
      const response = await fetch('http://localhost:8000/api/health');
      const data = await response.json();
      setMessage(`Backend: ${data.status || 'Connesso'}`);
    } catch (error) {
      setMessage('Backend non disponibile');
    }
  };

  return (
    <div style={{
      padding: '40px',
      fontFamily: 'Arial, sans-serif',
      maxWidth: '800px',
      margin: '0 auto'
    }}>
      <h1 style={{ color: '#333', marginBottom: '20px' }}>
        NexioSolution - Test Minimale
      </h1>

      <div style={{
        backgroundColor: '#f0f0f0',
        padding: '20px',
        borderRadius: '8px',
        marginBottom: '20px'
      }}>
        <p style={{ fontSize: '18px', color: '#666' }}>
          Se vedi questo messaggio, React funziona correttamente!
        </p>

        <div style={{ marginTop: '20px' }}>
          <button
            onClick={() => setCount(count + 1)}
            style={{
              backgroundColor: '#4CAF50',
              color: 'white',
              padding: '10px 20px',
              border: 'none',
              borderRadius: '4px',
              cursor: 'pointer',
              marginRight: '10px'
            }}
          >
            Click Test: {count}
          </button>

          <button
            onClick={() => alert('Alert funziona!')}
            style={{
              backgroundColor: '#2196F3',
              color: 'white',
              padding: '10px 20px',
              border: 'none',
              borderRadius: '4px',
              cursor: 'pointer',
              marginRight: '10px'
            }}
          >
            Test Alert
          </button>

          <button
            onClick={testBackend}
            style={{
              backgroundColor: '#FF9800',
              color: 'white',
              padding: '10px 20px',
              border: 'none',
              borderRadius: '4px',
              cursor: 'pointer'
            }}
          >
            Test Backend
          </button>
        </div>

        {message && (
          <div style={{
            marginTop: '20px',
            padding: '10px',
            backgroundColor: '#fff',
            borderRadius: '4px'
          }}>
            {message}
          </div>
        )}
      </div>

      <div style={{
        backgroundColor: '#e8f5e9',
        padding: '15px',
        borderRadius: '8px',
        marginBottom: '20px'
      }}>
        <h3 style={{ color: '#2e7d32' }}>Stato Sistema:</h3>
        <ul style={{ marginLeft: '20px' }}>
          <li>React: Funzionante</li>
          <li>Hot Reload: {import.meta.hot ? 'Attivo' : 'Non attivo'}</li>
          <li>Ambiente: {import.meta.env.MODE}</li>
          <li>Porta: 3000</li>
        </ul>
      </div>

      <div style={{
        backgroundColor: '#fff3e0',
        padding: '15px',
        borderRadius: '8px'
      }}>
        <h3 style={{ color: '#e65100' }}>Test Funzionalità:</h3>
        <ul style={{ marginLeft: '20px' }}>
          <li>Rendering componenti: OK</li>
          <li>State management: OK (contatore: {count})</li>
          <li>Event handling: OK</li>
          <li>Stili inline: OK</li>
        </ul>
      </div>
    </div>
  );
}

export default App;