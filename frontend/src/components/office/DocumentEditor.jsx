import React, { useEffect, useRef, useState, useCallback } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useSelector, useDispatch } from 'react-redux';
import {
  Box,
  Paper,
  AppBar,
  Toolbar,
  Typography,
  IconButton,
  Button,
  CircularProgress,
  Alert,
  Chip,
  Tooltip,
  Menu,
  MenuItem,
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  TextField,
  Select,
  FormControl,
  InputLabel,
  Snackbar,
  LinearProgress,
  Avatar,
  AvatarGroup,
  Badge,
} from '@mui/material';
import {
  ArrowBack,
  Save,
  Download,
  Share,
  History,
  Print,
  MoreVert,
  CloudUpload,
  CloudDone,
  CloudOff,
  Edit,
  Visibility,
  People,
  Comment,
  ZoomIn,
  ZoomOut,
  Fullscreen,
  FullscreenExit,
  Undo,
  Redo,
  FormatBold,
  FormatItalic,
  FormatUnderlined,
  Link as LinkIcon,
  Image as ImageIcon,
  TableChart,
  Functions,
  CheckCircle,
  Error as ErrorIcon,
  Warning,
} from '@mui/icons-material';
import { documentService } from '../../services/documentService';
import { onlyOfficeService } from '../../services/onlyOfficeService';

/**
 * Componente per l'editing di documenti con OnlyOffice
 * Gestisce l'iframe dell'editor e la toolbar personalizzata
 */
const DocumentEditor = () => {
  const { documentId } = useParams();
  const navigate = useNavigate();
  const dispatch = useDispatch();
  const { user } = useSelector((state) => state.auth);
  const { currentTenant } = useSelector((state) => state.tenant);

  // Refs
  const editorContainerRef = useRef(null);
  const docEditorRef = useRef(null);

  // State
  const [document, setDocument] = useState(null);
  const [editorConfig, setEditorConfig] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [saveStatus, setSaveStatus] = useState('saved'); // saved, saving, error
  const [connectedUsers, setConnectedUsers] = useState([]);
  const [isFullscreen, setIsFullscreen] = useState(false);
  const [showVersionHistory, setShowVersionHistory] = useState(false);
  const [showShareDialog, setShowShareDialog] = useState(false);
  const [exportFormat, setExportFormat] = useState('');
  const [notification, setNotification] = useState({ open: false, message: '', severity: 'info' });
  const [anchorEl, setAnchorEl] = useState(null);
  const [editorReady, setEditorReady] = useState(false);
  const [documentInfo, setDocumentInfo] = useState({});
  const [canEdit, setCanEdit] = useState(false);

  // Carica il documento e la configurazione dell'editor
  useEffect(() => {
    loadDocument();

    // Cleanup
    return () => {
      if (docEditorRef.current) {
        docEditorRef.current.destroyEditor();
      }
    };
  }, [documentId]);

  // Inizializza l'editor quando la configurazione è pronta
  useEffect(() => {
    if (editorConfig && editorContainerRef.current && !docEditorRef.current) {
      initializeEditor();
    }
  }, [editorConfig]);

  // Gestione WebSocket per aggiornamenti real-time
  useEffect(() => {
    if (document) {
      const channel = `document.${documentId}`;

      // Ascolta aggiornamenti utenti connessi
      window.Echo.channel(channel)
        .listen('DocumentUsersUpdated', (e) => {
          setConnectedUsers(e.users);
        })
        .listen('DocumentSaved', (e) => {
          if (e.version) {
            showNotification(`Documento salvato - Versione ${e.version.version}`, 'success');
            setSaveStatus('saved');
          }
        })
        .listen('DocumentLocked', (e) => {
          if (e.lockedBy !== user.id) {
            showNotification(`Documento bloccato da ${e.userName}`, 'warning');
            setCanEdit(false);
          }
        })
        .listen('DocumentUnlocked', (e) => {
          showNotification('Documento sbloccato', 'info');
          setCanEdit(true);
        });

      return () => {
        window.Echo.leave(channel);
      };
    }
  }, [document, documentId, user.id]);

  /**
   * Carica il documento dal server
   */
  const loadDocument = async () => {
    try {
      setLoading(true);
      setError(null);

      // Carica i dettagli del documento
      const docResponse = await documentService.getDocument(documentId);
      setDocument(docResponse.data);

      // Ottieni la configurazione per OnlyOffice
      const configResponse = await onlyOfficeService.getEditorConfig(documentId);
      setEditorConfig(configResponse.data);
      setCanEdit(configResponse.data.editorConfig.mode === 'edit');

      // Imposta informazioni documento
      setDocumentInfo({
        title: docResponse.data.name,
        type: docResponse.data.type,
        size: docResponse.data.file_size,
        lastModified: docResponse.data.updated_at,
        owner: docResponse.data.owner,
      });

    } catch (err) {
      console.error('Error loading document:', err);
      setError(err.response?.data?.message || 'Errore nel caricamento del documento');
    } finally {
      setLoading(false);
    }
  };

  /**
   * Inizializza l'editor OnlyOffice
   */
  const initializeEditor = () => {
    if (!window.DocsAPI) {
      console.error('OnlyOffice API not loaded');
      setError('Editor non disponibile. Ricarica la pagina.');
      return;
    }

    try {
      // Aggiungi event handlers alla configurazione
      const config = {
        ...editorConfig,
        events: {
          onAppReady: onAppReady,
          onDocumentReady: onDocumentReady,
          onDocumentStateChange: onDocumentStateChange,
          onError: onError,
          onWarning: onWarning,
          onInfo: onInfo,
          onRequestSaveAs: onRequestSaveAs,
          onRequestInsertImage: onRequestInsertImage,
          onRequestCompareFile: onRequestCompareFile,
          onRequestHistory: onRequestHistory,
          onRequestHistoryData: onRequestHistoryData,
          onRequestHistoryClose: onRequestHistoryClose,
          onMetaChange: onMetaChange,
          onRequestRename: onRequestRename,
          onMakeActionLink: onMakeActionLink,
          onRequestUsers: onRequestUsers,
          onRequestSendNotify: onRequestSendNotify,
          onCollaborativeChanges: onCollaborativeChanges,
        },
      };

      // Crea l'istanza dell'editor
      docEditorRef.current = new window.DocsAPI.DocEditor('onlyoffice-editor', config);

    } catch (err) {
      console.error('Error initializing editor:', err);
      setError('Errore nell\'inizializzazione dell\'editor');
    }
  };

  // Event Handlers per OnlyOffice

  const onAppReady = () => {
    console.log('OnlyOffice App Ready');
    setEditorReady(true);
  };

  const onDocumentReady = () => {
    console.log('Document Ready');
    showNotification('Documento pronto', 'success');
  };

  const onDocumentStateChange = (event) => {
    console.log('Document State Change:', event);
    if (event.data) {
      setSaveStatus('saving');
    } else {
      setSaveStatus('saved');
    }
  };

  const onError = (event) => {
    console.error('OnlyOffice Error:', event);
    setError(event.data.message || 'Errore nell\'editor');
    setSaveStatus('error');
  };

  const onWarning = (event) => {
    console.warn('OnlyOffice Warning:', event);
    showNotification(event.data.message || 'Attenzione', 'warning');
  };

  const onInfo = (event) => {
    console.info('OnlyOffice Info:', event);
    showNotification(event.data.message || 'Info', 'info');
  };

  const onRequestSaveAs = (event) => {
    console.log('Save As Request:', event);
    // Implementa logica per salvare con nome
    handleSaveAs(event.data);
  };

  const onRequestInsertImage = (event) => {
    console.log('Insert Image Request:', event);
    // Apri dialog per selezione immagine
    handleInsertImage();
  };

  const onRequestCompareFile = (event) => {
    console.log('Compare File Request:', event);
    // Implementa confronto file
    handleCompareFile();
  };

  const onRequestHistory = () => {
    console.log('History Request');
    setShowVersionHistory(true);
  };

  const onRequestHistoryData = (event) => {
    console.log('History Data Request:', event);
    // Fornisci dati versione richiesta
    return getVersionData(event.data.version);
  };

  const onRequestHistoryClose = () => {
    console.log('History Close');
    setShowVersionHistory(false);
  };

  const onMetaChange = (event) => {
    console.log('Meta Change:', event);
    // Aggiorna metadati documento
    updateDocumentMeta(event.data);
  };

  const onRequestRename = (event) => {
    console.log('Rename Request:', event);
    // Rinomina documento
    handleRename(event.data.title);
  };

  const onMakeActionLink = (event) => {
    console.log('Action Link:', event);
    // Genera link di azione
    return generateActionLink(event.data);
  };

  const onRequestUsers = () => {
    console.log('Request Users');
    // Restituisci lista utenti per menzioni
    return getAvailableUsers();
  };

  const onRequestSendNotify = (event) => {
    console.log('Send Notify:', event);
    // Invia notifica
    sendNotification(event.data);
  };

  const onCollaborativeChanges = () => {
    console.log('Collaborative Changes');
    // Gestisci modifiche collaborative
    setSaveStatus('saving');
  };

  // Azioni Toolbar

  const handleSave = () => {
    if (docEditorRef.current) {
      docEditorRef.current.downloadAs();
      setSaveStatus('saving');
    }
  };

  const handleDownload = async () => {
    try {
      const response = await documentService.downloadDocument(documentId);
      const url = window.URL.createObjectURL(new Blob([response.data]));
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute('download', document.name);
      document.body.appendChild(link);
      link.click();
      link.remove();
      showNotification('Download avviato', 'success');
    } catch (err) {
      showNotification('Errore nel download', 'error');
    }
  };

  const handleExport = async (format) => {
    try {
      setExportFormat(format);
      const response = await onlyOfficeService.exportDocument(documentId, format);

      if (response.data.url) {
        window.open(response.data.url, '_blank');
        showNotification(`Documento esportato in formato ${format.toUpperCase()}`, 'success');
      }
    } catch (err) {
      showNotification('Errore nell\'esportazione', 'error');
    }
  };

  const handlePrint = () => {
    if (docEditorRef.current) {
      docEditorRef.current.print();
    }
  };

  const handleShare = () => {
    setShowShareDialog(true);
  };

  const handleFullscreen = () => {
    if (!isFullscreen) {
      editorContainerRef.current?.requestFullscreen();
    } else {
      document.exitFullscreen();
    }
    setIsFullscreen(!isFullscreen);
  };

  const handleVersionRestore = async (versionId) => {
    try {
      await documentService.restoreVersion(documentId, versionId);
      showNotification('Versione ripristinata', 'success');
      loadDocument(); // Ricarica documento
    } catch (err) {
      showNotification('Errore nel ripristino', 'error');
    }
  };

  const handleSaveAs = async (data) => {
    try {
      const response = await documentService.saveAs(documentId, {
        title: data.title,
        folder: data.folder,
      });
      showNotification('Documento salvato con nuovo nome', 'success');
      navigate(`/documents/${response.data.id}/edit`);
    } catch (err) {
      showNotification('Errore nel salvataggio', 'error');
    }
  };

  const handleRename = async (newName) => {
    try {
      await documentService.updateDocument(documentId, { name: newName });
      setDocumentInfo({ ...documentInfo, title: newName });
      showNotification('Documento rinominato', 'success');
    } catch (err) {
      showNotification('Errore nella rinomina', 'error');
    }
  };

  const handleInsertImage = () => {
    // Implementa selezione immagine
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    input.onchange = async (e) => {
      const file = e.target.files[0];
      if (file) {
        // Upload immagine e inserisci nell'editor
        const formData = new FormData();
        formData.append('image', file);

        try {
          const response = await documentService.uploadImage(formData);
          if (docEditorRef.current && response.data.url) {
            docEditorRef.current.insertImage(response.data.url);
          }
        } catch (err) {
          showNotification('Errore nel caricamento immagine', 'error');
        }
      }
    };
    input.click();
  };

  const handleCompareFile = () => {
    // Implementa confronto documenti
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = '.docx,.doc,.odt';
    input.onchange = async (e) => {
      const file = e.target.files[0];
      if (file) {
        // Upload e confronta
        const formData = new FormData();
        formData.append('file', file);

        try {
          const response = await documentService.compareDocument(documentId, formData);
          if (response.data.url && docEditorRef.current) {
            docEditorRef.current.setRevisedFile(response.data.url);
          }
        } catch (err) {
          showNotification('Errore nel confronto', 'error');
        }
      }
    };
    input.click();
  };

  const updateDocumentMeta = async (meta) => {
    try {
      await documentService.updateDocumentMeta(documentId, meta);
    } catch (err) {
      console.error('Error updating meta:', err);
    }
  };

  const getVersionData = async (version) => {
    try {
      const response = await documentService.getDocumentVersion(documentId, version);
      return response.data;
    } catch (err) {
      console.error('Error getting version data:', err);
      return null;
    }
  };

  const generateActionLink = (data) => {
    // Genera link per azioni specifiche
    const baseUrl = window.location.origin;
    return `${baseUrl}/documents/${documentId}/action/${data.action}`;
  };

  const getAvailableUsers = async () => {
    try {
      const response = await documentService.getDocumentUsers(documentId);
      return response.data.map(user => ({
        id: user.id.toString(),
        name: user.name,
        email: user.email,
      }));
    } catch (err) {
      return [];
    }
  };

  const sendNotification = async (data) => {
    try {
      await documentService.sendNotification(documentId, {
        users: data.users,
        message: data.message,
      });
    } catch (err) {
      console.error('Error sending notification:', err);
    }
  };

  const showNotification = (message, severity = 'info') => {
    setNotification({ open: true, message, severity });
  };

  const getSaveStatusIcon = () => {
    switch (saveStatus) {
      case 'saving':
        return <CircularProgress size={20} />;
      case 'saved':
        return <CloudDone color="success" />;
      case 'error':
        return <CloudOff color="error" />;
      default:
        return null;
    }
  };

  const getSaveStatusText = () => {
    switch (saveStatus) {
      case 'saving':
        return 'Salvataggio...';
      case 'saved':
        return 'Salvato';
      case 'error':
        return 'Errore salvataggio';
      default:
        return '';
    }
  };

  // Render loading
  if (loading) {
    return (
      <Box
        display="flex"
        justifyContent="center"
        alignItems="center"
        height="100vh"
      >
        <CircularProgress />
      </Box>
    );
  }

  // Render error
  if (error) {
    return (
      <Box
        display="flex"
        justifyContent="center"
        alignItems="center"
        height="100vh"
        p={3}
      >
        <Alert
          severity="error"
          action={
            <Button color="inherit" onClick={() => navigate('/documents')}>
              Torna ai documenti
            </Button>
          }
        >
          {error}
        </Alert>
      </Box>
    );
  }

  return (
    <Box sx={{ height: '100vh', display: 'flex', flexDirection: 'column' }}>
      {/* Toolbar personalizzata */}
      <AppBar position="static" color="default" elevation={1}>
        <Toolbar variant="dense">
          <IconButton
            edge="start"
            onClick={() => navigate('/documents')}
            sx={{ mr: 2 }}
          >
            <ArrowBack />
          </IconButton>

          <Typography variant="h6" sx={{ flexGrow: 1 }}>
            {documentInfo.title}
          </Typography>

          {/* Status salvataggio */}
          <Box display="flex" alignItems="center" mr={2}>
            {getSaveStatusIcon()}
            <Typography variant="body2" ml={1}>
              {getSaveStatusText()}
            </Typography>
          </Box>

          {/* Utenti connessi */}
          {connectedUsers.length > 0 && (
            <AvatarGroup max={4} sx={{ mr: 2 }}>
              {connectedUsers.map((user) => (
                <Tooltip key={user.id} title={user.name}>
                  <Avatar
                    alt={user.name}
                    src={user.avatar}
                    sx={{ width: 32, height: 32 }}
                  >
                    {user.name.charAt(0)}
                  </Avatar>
                </Tooltip>
              ))}
            </AvatarGroup>
          )}

          {/* Azioni documento */}
          {canEdit && (
            <IconButton onClick={handleSave} disabled={saveStatus === 'saving'}>
              <Save />
            </IconButton>
          )}

          <IconButton onClick={handleDownload}>
            <Download />
          </IconButton>

          <IconButton onClick={handlePrint}>
            <Print />
          </IconButton>

          <IconButton onClick={handleShare}>
            <Share />
          </IconButton>

          <IconButton onClick={() => setShowVersionHistory(true)}>
            <History />
          </IconButton>

          <IconButton onClick={handleFullscreen}>
            {isFullscreen ? <FullscreenExit /> : <Fullscreen />}
          </IconButton>

          <IconButton
            onClick={(e) => setAnchorEl(e.currentTarget)}
          >
            <MoreVert />
          </IconButton>
        </Toolbar>

        {/* Progress bar per caricamento */}
        {saveStatus === 'saving' && <LinearProgress />}
      </AppBar>

      {/* Container per l'editor OnlyOffice */}
      <Box
        ref={editorContainerRef}
        sx={{
          flexGrow: 1,
          position: 'relative',
          backgroundColor: '#f5f5f5',
        }}
      >
        <div
          id="onlyoffice-editor"
          style={{
            width: '100%',
            height: '100%',
          }}
        />
      </Box>

      {/* Menu opzioni */}
      <Menu
        anchorEl={anchorEl}
        open={Boolean(anchorEl)}
        onClose={() => setAnchorEl(null)}
      >
        <MenuItem onClick={() => handleExport('pdf')}>
          Esporta come PDF
        </MenuItem>
        <MenuItem onClick={() => handleExport('docx')}>
          Esporta come DOCX
        </MenuItem>
        <MenuItem onClick={() => handleExport('html')}>
          Esporta come HTML
        </MenuItem>
        <MenuItem onClick={handleCompareFile}>
          Confronta con...
        </MenuItem>
        <MenuItem onClick={() => setShowShareDialog(true)}>
          Impostazioni condivisione
        </MenuItem>
      </Menu>

      {/* Dialog condivisione */}
      <Dialog
        open={showShareDialog}
        onClose={() => setShowShareDialog(false)}
        maxWidth="sm"
        fullWidth
      >
        <DialogTitle>Condividi documento</DialogTitle>
        <DialogContent>
          {/* Implementa form condivisione */}
          <Typography>Funzionalità condivisione da implementare</Typography>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setShowShareDialog(false)}>
            Annulla
          </Button>
          <Button variant="contained" onClick={() => setShowShareDialog(false)}>
            Condividi
          </Button>
        </DialogActions>
      </Dialog>

      {/* Dialog cronologia versioni */}
      <Dialog
        open={showVersionHistory}
        onClose={() => setShowVersionHistory(false)}
        maxWidth="md"
        fullWidth
      >
        <DialogTitle>Cronologia versioni</DialogTitle>
        <DialogContent>
          {/* Implementa lista versioni */}
          <Typography>Cronologia versioni da implementare</Typography>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setShowVersionHistory(false)}>
            Chiudi
          </Button>
        </DialogActions>
      </Dialog>

      {/* Snackbar notifiche */}
      <Snackbar
        open={notification.open}
        autoHideDuration={6000}
        onClose={() => setNotification({ ...notification, open: false })}
        anchorOrigin={{ vertical: 'bottom', horizontal: 'right' }}
      >
        <Alert
          onClose={() => setNotification({ ...notification, open: false })}
          severity={notification.severity}
          sx={{ width: '100%' }}
        >
          {notification.message}
        </Alert>
      </Snackbar>
    </Box>
  );
};

export default DocumentEditor;