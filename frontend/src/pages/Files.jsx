import React, { useState, useEffect } from 'react';
import MainLayout from '../components/layout/MainLayout';
import {
  Upload,
  FolderPlus,
  Search,
  Grid,
  List,
  File,
  FileText,
  Image,
  Film,
  Music,
  Archive,
  Download,
  Trash2,
  Share2,
  MoreVertical,
  Folder,
  ChevronRight,
  Home
} from 'lucide-react';
import toast from 'react-hot-toast';

const Files = () => {
  const [viewMode, setViewMode] = useState('grid');
  const [searchTerm, setSearchTerm] = useState('');
  const [currentPath, setCurrentPath] = useState([]);
  const [selectedFiles, setSelectedFiles] = useState([]);
  const [showUploadModal, setShowUploadModal] = useState(false);

  // Demo files and folders
  const [items, setItems] = useState([
    { id: 1, name: 'Documenti', type: 'folder', size: '—', modified: '2024-01-10', items: 15 },
    { id: 2, name: 'Immagini', type: 'folder', size: '—', modified: '2024-01-09', items: 42 },
    { id: 3, name: 'Video', type: 'folder', size: '—', modified: '2024-01-08', items: 8 },
    { id: 4, name: 'Report_Q4_2024.pdf', type: 'pdf', size: '2.4 MB', modified: '2024-01-12' },
    { id: 5, name: 'Presentazione.pptx', type: 'ppt', size: '5.1 MB', modified: '2024-01-11' },
    { id: 6, name: 'Budget_2025.xlsx', type: 'xls', size: '890 KB', modified: '2024-01-10' },
    { id: 7, name: 'Contratto_cliente.docx', type: 'doc', size: '156 KB', modified: '2024-01-09' },
    { id: 8, name: 'Logo_aziendale.png', type: 'image', size: '245 KB', modified: '2024-01-08' },
    { id: 9, name: 'Video_tutorial.mp4', type: 'video', size: '125 MB', modified: '2024-01-07' },
    { id: 10, name: 'Backup.zip', type: 'archive', size: '1.8 GB', modified: '2024-01-06' },
  ]);

  const getFileIcon = (type) => {
    switch (type) {
      case 'folder':
        return <Folder className="w-12 h-12 text-blue-500" />;
      case 'pdf':
      case 'doc':
      case 'xls':
      case 'ppt':
        return <FileText className="w-12 h-12 text-red-500" />;
      case 'image':
        return <Image className="w-12 h-12 text-green-500" />;
      case 'video':
        return <Film className="w-12 h-12 text-purple-500" />;
      case 'audio':
        return <Music className="w-12 h-12 text-pink-500" />;
      case 'archive':
        return <Archive className="w-12 h-12 text-yellow-500" />;
      default:
        return <File className="w-12 h-12 text-gray-500" />;
    }
  };

  const getSmallFileIcon = (type) => {
    switch (type) {
      case 'folder':
        return <Folder className="w-5 h-5 text-blue-500" />;
      case 'pdf':
      case 'doc':
      case 'xls':
      case 'ppt':
        return <FileText className="w-5 h-5 text-red-500" />;
      case 'image':
        return <Image className="w-5 h-5 text-green-500" />;
      case 'video':
        return <Film className="w-5 h-5 text-purple-500" />;
      case 'audio':
        return <Music className="w-5 h-5 text-pink-500" />;
      case 'archive':
        return <Archive className="w-5 h-5 text-yellow-500" />;
      default:
        return <File className="w-5 h-5 text-gray-500" />;
    }
  };

  const handleFileClick = (item) => {
    if (item.type === 'folder') {
      setCurrentPath([...currentPath, item.name]);
      // Load folder contents
      toast.success(`Aperto: ${item.name}`);
    } else {
      // Preview or download file
      toast.success(`File selezionato: ${item.name}`);
    }
  };

  const handleBreadcrumbClick = (index) => {
    setCurrentPath(currentPath.slice(0, index));
  };

  const filteredItems = items.filter(item =>
    item.name.toLowerCase().includes(searchTerm.toLowerCase())
  );

  const storageInfo = {
    used: 45.2,
    total: 100,
    percentage: 45.2
  };

  return (
    <MainLayout>
      <div className="p-6">
        {/* Header */}
        <div className="mb-6">
          <h1 className="text-2xl font-bold text-gray-900">Gestione File</h1>
          <p className="text-gray-600 mt-1">Organizza e condividi i tuoi documenti</p>
        </div>

        {/* Storage Info */}
        <div className="bg-white rounded-lg shadow border border-gray-200 p-4 mb-6">
          <div className="flex items-center justify-between mb-2">
            <span className="text-sm font-medium text-gray-700">Spazio utilizzato</span>
            <span className="text-sm text-gray-600">{storageInfo.used} GB / {storageInfo.total} GB</span>
          </div>
          <div className="w-full bg-gray-200 rounded-full h-2">
            <div
              className="bg-blue-600 h-2 rounded-full transition-all duration-300"
              style={{ width: `${storageInfo.percentage}%` }}
            />
          </div>
        </div>

        {/* Breadcrumb */}
        <div className="bg-white rounded-lg shadow border border-gray-200 p-4 mb-6">
          <div className="flex items-center space-x-2 text-sm">
            <button
              onClick={() => setCurrentPath([])}
              className="flex items-center text-gray-600 hover:text-blue-600"
            >
              <Home className="w-4 h-4" />
            </button>
            {currentPath.map((path, index) => (
              <React.Fragment key={index}>
                <ChevronRight className="w-4 h-4 text-gray-400" />
                <button
                  onClick={() => handleBreadcrumbClick(index + 1)}
                  className="text-gray-600 hover:text-blue-600"
                >
                  {path}
                </button>
              </React.Fragment>
            ))}
          </div>
        </div>

        {/* Toolbar */}
        <div className="bg-white rounded-lg shadow border border-gray-200 p-4 mb-6">
          <div className="flex flex-col md:flex-row gap-4">
            <div className="flex-1">
              <div className="relative">
                <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-5 h-5" />
                <input
                  type="text"
                  placeholder="Cerca file e cartelle..."
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                />
              </div>
            </div>
            <div className="flex gap-2">
              <button
                onClick={() => setShowUploadModal(true)}
                className="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center gap-2"
              >
                <Upload className="w-5 h-5" />
                Carica
              </button>
              <button className="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 flex items-center gap-2">
                <FolderPlus className="w-5 h-5" />
                Nuova Cartella
              </button>
              <div className="flex border border-gray-300 rounded-lg">
                <button
                  onClick={() => setViewMode('grid')}
                  className={`p-2 ${viewMode === 'grid' ? 'bg-gray-100' : ''}`}
                >
                  <Grid className="w-5 h-5" />
                </button>
                <button
                  onClick={() => setViewMode('list')}
                  className={`p-2 ${viewMode === 'list' ? 'bg-gray-100' : ''}`}
                >
                  <List className="w-5 h-5" />
                </button>
              </div>
            </div>
          </div>
        </div>

        {/* Files Grid/List */}
        <div className="bg-white rounded-lg shadow border border-gray-200">
          {viewMode === 'grid' ? (
            <div className="p-4 grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
              {filteredItems.map((item) => (
                <div
                  key={item.id}
                  onClick={() => handleFileClick(item)}
                  className="group cursor-pointer"
                >
                  <div className="flex flex-col items-center p-4 rounded-lg hover:bg-gray-50 transition-colors">
                    <div className="mb-2">
                      {getFileIcon(item.type)}
                    </div>
                    <p className="text-sm text-center text-gray-900 group-hover:text-blue-600 line-clamp-2">
                      {item.name}
                    </p>
                    <p className="text-xs text-gray-500 mt-1">
                      {item.type === 'folder' ? `${item.items} elementi` : item.size}
                    </p>
                  </div>
                  <div className="opacity-0 group-hover:opacity-100 transition-opacity flex justify-center gap-1 mt-1">
                    <button className="p-1 rounded hover:bg-gray-200">
                      <Download className="w-4 h-4 text-gray-600" />
                    </button>
                    <button className="p-1 rounded hover:bg-gray-200">
                      <Share2 className="w-4 h-4 text-gray-600" />
                    </button>
                    <button className="p-1 rounded hover:bg-gray-200">
                      <Trash2 className="w-4 h-4 text-gray-600" />
                    </button>
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead className="bg-gray-50 border-b border-gray-200">
                  <tr>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Nome
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Dimensione
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Modificato
                    </th>
                    <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Azioni
                    </th>
                  </tr>
                </thead>
                <tbody className="bg-white divide-y divide-gray-200">
                  {filteredItems.map((item) => (
                    <tr
                      key={item.id}
                      onClick={() => handleFileClick(item)}
                      className="hover:bg-gray-50 cursor-pointer"
                    >
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="flex items-center">
                          {getSmallFileIcon(item.type)}
                          <span className="ml-3 text-sm font-medium text-gray-900">
                            {item.name}
                          </span>
                        </div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {item.type === 'folder' ? `${item.items} elementi` : item.size}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {new Date(item.modified).toLocaleDateString('it-IT')}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <div className="flex justify-end gap-2">
                          <button
                            onClick={(e) => {
                              e.stopPropagation();
                              toast.success('Download avviato');
                            }}
                            className="text-gray-400 hover:text-gray-600"
                          >
                            <Download className="w-5 h-5" />
                          </button>
                          <button
                            onClick={(e) => {
                              e.stopPropagation();
                              toast.success('Link condiviso');
                            }}
                            className="text-gray-400 hover:text-gray-600"
                          >
                            <Share2 className="w-5 h-5" />
                          </button>
                          <button
                            onClick={(e) => {
                              e.stopPropagation();
                              toast.error('File eliminato');
                            }}
                            className="text-gray-400 hover:text-red-600"
                          >
                            <Trash2 className="w-5 h-5" />
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>
    </MainLayout>
  );
};

export default Files;