import { useState, useCallback } from 'react';
import { useDropzone } from 'react-dropzone';
import { motion, AnimatePresence } from 'framer-motion';
import {
  Folder,
  File,
  FileText,
  Image,
  Film,
  Music,
  Archive,
  Upload,
  Download,
  Trash2,
  Share2,
  Eye,
  Edit,
  Copy,
  Move,
  MoreVertical,
  Grid,
  List,
  Search,
  Filter,
  ChevronRight,
  Home,
  Clock,
  CheckCircle,
  XCircle,
  AlertCircle,
  Users,
  Lock,
  Unlock,
  GitBranch,
  X
} from 'lucide-react';
import { format } from 'date-fns';
import { it } from 'date-fns/locale';
import toast from 'react-hot-toast';
import { Document, Page, pdfjs } from 'react-pdf';

// Configura worker PDF.js
pdfjs.GlobalWorkerOptions.workerSrc = `//unpkg.com/pdfjs-dist@${pdfjs.version}/build/pdf.worker.min.js`;

/**
 * File Manager con drag & drop, preview e workflow approvazioni
 */
const FileManager = () => {
  const [viewMode, setViewMode] = useState('grid'); // grid | list
  const [selectedFiles, setSelectedFiles] = useState([]);
  const [currentPath, setCurrentPath] = useState([]);
  const [showUploadModal, setShowUploadModal] = useState(false);
  const [showPreview, setShowPreview] = useState(false);
  const [previewFile, setPreviewFile] = useState(null);
  const [showWorkflowModal, setShowWorkflowModal] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const [filterType, setFilterType] = useState('all');

  // Files mock data
  const [files, setFiles] = useState([
    {
      id: 1,
      name: 'Documenti',
      type: 'folder',
      size: null,
      modified: new Date(),
      shared: true,
      items: 12
    },
    {
      id: 2,
      name: 'Report Q4 2024.pdf',
      type: 'pdf',
      size: 2458624,
      modified: new Date(),
      shared: false,
      status: 'approved',
      workflow: {
        status: 'approved',
        approver: 'Mario Rossi',
        date: new Date()
      },
      versions: [
        { version: 'v2.0', date: new Date(), author: 'Laura Bianchi' },
        { version: 'v1.0', date: new Date(2024, 0, 1), author: 'Giuseppe Verdi' }
      ]
    },
    {
      id: 3,
      name: 'Presentazione.pptx',
      type: 'presentation',
      size: 5342208,
      modified: new Date(2024, 1, 15),
      shared: true,
      status: 'pending',
      workflow: {
        status: 'pending',
        requestedBy: 'Anna Neri',
        date: new Date()
      }
    },
    {
      id: 4,
      name: 'Logo_aziendale.png',
      type: 'image',
      size: 245760,
      modified: new Date(2024, 2, 10),
      shared: false,
      status: null
    },
    {
      id: 5,
      name: 'Video_tutorial.mp4',
      type: 'video',
      size: 15728640,
      modified: new Date(2024, 2, 5),
      shared: true,
      status: 'rejected',
      workflow: {
        status: 'rejected',
        rejectedBy: 'Paolo Bianchi',
        reason: 'Qualità video insufficiente',
        date: new Date()
      }
    }
  ]);

  // Dropzone configuration
  const onDrop = useCallback((acceptedFiles) => {
    const newFiles = acceptedFiles.map(file => ({
      id: Date.now() + Math.random(),
      name: file.name,
      type: getFileType(file.name),
      size: file.size,
      modified: new Date(),
      shared: false,
      status: null,
      file: file // File object per upload
    }));

    setFiles(prev => [...prev, ...newFiles]);
    toast.success(`${acceptedFiles.length} file caricati con successo`);
  }, []);

  const { getRootProps, getInputProps, isDragActive } = useDropzone({
    onDrop,
    multiple: true
  });

  // Determina tipo file dall'estensione
  const getFileType = (filename) => {
    const ext = filename.split('.').pop().toLowerCase();
    const typeMap = {
      pdf: 'pdf',
      doc: 'document',
      docx: 'document',
      xls: 'spreadsheet',
      xlsx: 'spreadsheet',
      ppt: 'presentation',
      pptx: 'presentation',
      jpg: 'image',
      jpeg: 'image',
      png: 'image',
      gif: 'image',
      mp4: 'video',
      avi: 'video',
      mov: 'video',
      mp3: 'audio',
      wav: 'audio',
      zip: 'archive',
      rar: 'archive'
    };
    return typeMap[ext] || 'file';
  };

  // Icona per tipo file
  const getFileIcon = (type) => {
    const iconMap = {
      folder: Folder,
      pdf: FileText,
      document: FileText,
      spreadsheet: FileText,
      presentation: FileText,
      image: Image,
      video: Film,
      audio: Music,
      archive: Archive,
      file: File
    };
    return iconMap[type] || File;
  };

  // Colore stato workflow
  const getStatusColor = (status) => {
    const colorMap = {
      approved: 'text-success',
      pending: 'text-warning',
      rejected: 'text-error'
    };
    return colorMap[status] || 'text-gray-500';
  };

  // Formatta dimensione file
  const formatFileSize = (bytes) => {
    if (!bytes) return '-';
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    return Math.round(bytes / Math.pow(1024, i) * 100) / 100 + ' ' + sizes[i];
  };

  // Gestione selezione file
  const toggleFileSelection = (fileId) => {
    setSelectedFiles(prev =>
      prev.includes(fileId)
        ? prev.filter(id => id !== fileId)
        : [...prev, fileId]
    );
  };

  // Apri preview file
  const openPreview = (file) => {
    if (file.type === 'folder') {
      // Naviga nella cartella
      setCurrentPath([...currentPath, file.name]);
    } else {
      setPreviewFile(file);
      setShowPreview(true);
    }
  };

  // Download file
  const downloadFile = (file) => {
    toast.success(`Download di ${file.name} avviato`);
  };

  // Elimina file
  const deleteFiles = () => {
    if (selectedFiles.length === 0) {
      toast.error('Seleziona almeno un file');
      return;
    }

    setFiles(prev => prev.filter(f => !selectedFiles.includes(f.id)));
    setSelectedFiles([]);
    toast.success(`${selectedFiles.length} file eliminati`);
  };

  // Condividi file
  const shareFiles = () => {
    if (selectedFiles.length === 0) {
      toast.error('Seleziona almeno un file');
      return;
    }
    toast.success('Link di condivisione copiato');
  };

  // Filtra files
  const filteredFiles = files.filter(file => {
    const matchesSearch = file.name.toLowerCase().includes(searchQuery.toLowerCase());
    const matchesType = filterType === 'all' || file.type === filterType;
    return matchesSearch && matchesType;
  });

  return (
    <div className="flex flex-col h-full">
      {/* Header */}
      <div className="border-b border-gray-200 dark:border-gray-700 bg-white dark:bg-dark-card p-4">
        <div className="flex items-center justify-between mb-4">
          <div className="flex items-center gap-4">
            <h1 className="text-xl font-semibold text-gray-900 dark:text-white">
              File Manager
            </h1>
            {/* Breadcrumb */}
            <nav className="flex items-center gap-2 text-sm">
              <button
                onClick={() => setCurrentPath([])}
                className="text-gray-500 hover:text-primary"
              >
                <Home className="h-4 w-4" />
              </button>
              {currentPath.map((path, index) => (
                <React.Fragment key={index}>
                  <ChevronRight className="h-4 w-4 text-gray-400" />
                  <button
                    onClick={() => setCurrentPath(currentPath.slice(0, index + 1))}
                    className="text-gray-500 hover:text-primary"
                  >
                    {path}
                  </button>
                </React.Fragment>
              ))}
            </nav>
          </div>

          <div className="flex items-center gap-2">
            <button
              onClick={() => setShowUploadModal(true)}
              className="btn-primary flex items-center gap-2"
            >
              <Upload className="h-4 w-4" />
              Carica
            </button>
            <button
              onClick={() => setShowWorkflowModal(true)}
              className="btn-secondary flex items-center gap-2"
            >
              <GitBranch className="h-4 w-4" />
              Workflow
            </button>
          </div>
        </div>

        {/* Toolbar */}
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-4">
            {/* Search */}
            <div className="relative">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
              <input
                type="search"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder="Cerca file..."
                className="input pl-9 w-64"
              />
            </div>

            {/* Filter */}
            <select
              value={filterType}
              onChange={(e) => setFilterType(e.target.value)}
              className="input w-32"
            >
              <option value="all">Tutti</option>
              <option value="folder">Cartelle</option>
              <option value="document">Documenti</option>
              <option value="image">Immagini</option>
              <option value="video">Video</option>
            </select>

            {/* Actions */}
            {selectedFiles.length > 0 && (
              <div className="flex items-center gap-2">
                <span className="text-sm text-gray-500">
                  {selectedFiles.length} selezionati
                </span>
                <button onClick={shareFiles} className="btn-ghost p-2">
                  <Share2 className="h-4 w-4" />
                </button>
                <button onClick={downloadFile} className="btn-ghost p-2">
                  <Download className="h-4 w-4" />
                </button>
                <button onClick={deleteFiles} className="btn-ghost p-2 text-error">
                  <Trash2 className="h-4 w-4" />
                </button>
              </div>
            )}
          </div>

          {/* View mode */}
          <div className="flex items-center gap-1">
            <button
              onClick={() => setViewMode('grid')}
              className={`p-2 rounded ${viewMode === 'grid' ? 'bg-gray-100 dark:bg-gray-800' : ''}`}
            >
              <Grid className="h-4 w-4" />
            </button>
            <button
              onClick={() => setViewMode('list')}
              className={`p-2 rounded ${viewMode === 'list' ? 'bg-gray-100 dark:bg-gray-800' : ''}`}
            >
              <List className="h-4 w-4" />
            </button>
          </div>
        </div>
      </div>

      {/* File area with dropzone */}
      <div className="flex-1 p-6 overflow-auto">
        <div
          {...getRootProps()}
          className={`h-full ${isDragActive ? 'bg-primary-50 dark:bg-primary-900/20 border-2 border-dashed border-primary rounded-lg' : ''}`}
        >
          <input {...getInputProps()} />

          {isDragActive ? (
            <div className="h-full flex items-center justify-center">
              <div className="text-center">
                <Upload className="h-12 w-12 text-primary mx-auto mb-4" />
                <p className="text-lg font-medium text-gray-900 dark:text-white">
                  Rilascia i file qui
                </p>
                <p className="text-sm text-gray-500 dark:text-gray-400 mt-2">
                  I file verranno caricati automaticamente
                </p>
              </div>
            </div>
          ) : viewMode === 'grid' ? (
            // Grid view
            <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
              {filteredFiles.map(file => {
                const Icon = getFileIcon(file.type);
                const isSelected = selectedFiles.includes(file.id);

                return (
                  <motion.div
                    key={file.id}
                    whileHover={{ scale: 1.05 }}
                    whileTap={{ scale: 0.95 }}
                    className={`relative group cursor-pointer ${isSelected ? 'ring-2 ring-primary rounded-lg' : ''}`}
                    onClick={() => toggleFileSelection(file.id)}
                    onDoubleClick={() => openPreview(file)}
                  >
                    <div className="p-4 bg-white dark:bg-dark-card rounded-lg border border-gray-200 dark:border-gray-700 hover:shadow-md transition-shadow">
                      {/* File icon */}
                      <div className="mb-3 relative">
                        <Icon className="h-12 w-12 text-gray-400 mx-auto" />
                        {file.workflow && (
                          <div className={`absolute -top-1 -right-1 ${getStatusColor(file.workflow.status)}`}>
                            {file.workflow.status === 'approved' && <CheckCircle className="h-5 w-5" />}
                            {file.workflow.status === 'pending' && <Clock className="h-5 w-5" />}
                            {file.workflow.status === 'rejected' && <XCircle className="h-5 w-5" />}
                          </div>
                        )}
                      </div>

                      {/* File info */}
                      <p className="text-sm font-medium text-gray-900 dark:text-white truncate">
                        {file.name}
                      </p>
                      <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        {file.type === 'folder' ? `${file.items} elementi` : formatFileSize(file.size)}
                      </p>

                      {/* Actions */}
                      <div className="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity">
                        <button
                          onClick={(e) => {
                            e.stopPropagation();
                            // Mostra menu contestuale
                          }}
                          className="p-1 bg-white dark:bg-gray-800 rounded shadow-sm"
                        >
                          <MoreVertical className="h-4 w-4" />
                        </button>
                      </div>
                    </div>
                  </motion.div>
                );
              })}
            </div>
          ) : (
            // List view
            <div className="bg-white dark:bg-dark-card rounded-lg border border-gray-200 dark:border-gray-700">
              <table className="w-full">
                <thead>
                  <tr className="border-b border-gray-200 dark:border-gray-700">
                    <th className="text-left p-4 text-sm font-medium text-gray-700 dark:text-gray-300">
                      Nome
                    </th>
                    <th className="text-left p-4 text-sm font-medium text-gray-700 dark:text-gray-300">
                      Dimensione
                    </th>
                    <th className="text-left p-4 text-sm font-medium text-gray-700 dark:text-gray-300">
                      Modificato
                    </th>
                    <th className="text-left p-4 text-sm font-medium text-gray-700 dark:text-gray-300">
                      Stato
                    </th>
                    <th className="text-left p-4 text-sm font-medium text-gray-700 dark:text-gray-300">
                      Azioni
                    </th>
                  </tr>
                </thead>
                <tbody>
                  {filteredFiles.map(file => {
                    const Icon = getFileIcon(file.type);
                    const isSelected = selectedFiles.includes(file.id);

                    return (
                      <tr
                        key={file.id}
                        className={`border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer ${
                          isSelected ? 'bg-primary-50 dark:bg-primary-900/20' : ''
                        }`}
                        onClick={() => toggleFileSelection(file.id)}
                        onDoubleClick={() => openPreview(file)}
                      >
                        <td className="p-4">
                          <div className="flex items-center gap-3">
                            <Icon className="h-5 w-5 text-gray-400" />
                            <span className="text-sm font-medium text-gray-900 dark:text-white">
                              {file.name}
                            </span>
                            {file.shared && (
                              <Users className="h-4 w-4 text-gray-400" />
                            )}
                          </div>
                        </td>
                        <td className="p-4 text-sm text-gray-600 dark:text-gray-400">
                          {file.type === 'folder' ? `${file.items} elementi` : formatFileSize(file.size)}
                        </td>
                        <td className="p-4 text-sm text-gray-600 dark:text-gray-400">
                          {format(file.modified, 'dd MMM yyyy', { locale: it })}
                        </td>
                        <td className="p-4">
                          {file.workflow && (
                            <span className={`text-sm font-medium ${getStatusColor(file.workflow.status)}`}>
                              {file.workflow.status === 'approved' && 'Approvato'}
                              {file.workflow.status === 'pending' && 'In attesa'}
                              {file.workflow.status === 'rejected' && 'Rifiutato'}
                            </span>
                          )}
                        </td>
                        <td className="p-4">
                          <div className="flex items-center gap-2">
                            <button
                              onClick={(e) => {
                                e.stopPropagation();
                                openPreview(file);
                              }}
                              className="p-1 hover:bg-gray-100 dark:hover:bg-gray-700 rounded"
                            >
                              <Eye className="h-4 w-4" />
                            </button>
                            <button
                              onClick={(e) => {
                                e.stopPropagation();
                                downloadFile(file);
                              }}
                              className="p-1 hover:bg-gray-100 dark:hover:bg-gray-700 rounded"
                            >
                              <Download className="h-4 w-4" />
                            </button>
                            <button
                              onClick={(e) => {
                                e.stopPropagation();
                                // Mostra menu
                              }}
                              className="p-1 hover:bg-gray-100 dark:hover:bg-gray-700 rounded"
                            >
                              <MoreVertical className="h-4 w-4" />
                            </button>
                          </div>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>

      {/* Preview Modal */}
      <AnimatePresence>
        {showPreview && previewFile && (
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4"
            onClick={() => setShowPreview(false)}
          >
            <motion.div
              initial={{ scale: 0.9 }}
              animate={{ scale: 1 }}
              exit={{ scale: 0.9 }}
              onClick={(e) => e.stopPropagation()}
              className="relative w-full max-w-4xl max-h-[90vh] bg-white dark:bg-dark-card rounded-xl overflow-hidden"
            >
              {/* Header */}
              <div className="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
                <div className="flex items-center gap-3">
                  <FileText className="h-5 w-5 text-gray-400" />
                  <div>
                    <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                      {previewFile.name}
                    </h3>
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                      {formatFileSize(previewFile.size)} • Modificato {format(previewFile.modified, 'dd MMM yyyy', { locale: it })}
                    </p>
                  </div>
                </div>
                <button
                  onClick={() => setShowPreview(false)}
                  className="p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg"
                >
                  <X className="h-5 w-5" />
                </button>
              </div>

              {/* Content */}
              <div className="p-6 overflow-auto max-h-[60vh]">
                {previewFile.type === 'image' ? (
                  <img
                    src="/api/placeholder/800/600"
                    alt={previewFile.name}
                    className="w-full h-auto rounded-lg"
                  />
                ) : previewFile.type === 'pdf' ? (
                  <div className="flex justify-center">
                    <Document file="/api/placeholder/pdf">
                      <Page pageNumber={1} />
                    </Document>
                  </div>
                ) : (
                  <div className="text-center py-12">
                    <FileText className="h-16 w-16 text-gray-300 mx-auto mb-4" />
                    <p className="text-gray-500 dark:text-gray-400">
                      Anteprima non disponibile per questo tipo di file
                    </p>
                    <button
                      onClick={() => downloadFile(previewFile)}
                      className="btn-primary mt-4"
                    >
                      <Download className="h-4 w-4 mr-2" />
                      Scarica file
                    </button>
                  </div>
                )}
              </div>

              {/* Versions */}
              {previewFile.versions && (
                <div className="p-4 border-t border-gray-200 dark:border-gray-700">
                  <h4 className="text-sm font-semibold text-gray-900 dark:text-white mb-3">
                    Cronologia versioni
                  </h4>
                  <div className="space-y-2">
                    {previewFile.versions.map((version, index) => (
                      <div key={index} className="flex items-center justify-between text-sm">
                        <div className="flex items-center gap-3">
                          <GitBranch className="h-4 w-4 text-gray-400" />
                          <span className="font-medium text-gray-900 dark:text-white">
                            {version.version}
                          </span>
                          <span className="text-gray-500 dark:text-gray-400">
                            {version.author}
                          </span>
                        </div>
                        <span className="text-gray-500 dark:text-gray-400">
                          {format(version.date, 'dd MMM yyyy', { locale: it })}
                        </span>
                      </div>
                    ))}
                  </div>
                </div>
              )}
            </motion.div>
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  );
};

export default FileManager;