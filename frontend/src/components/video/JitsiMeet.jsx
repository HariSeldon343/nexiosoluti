import React, { useEffect, useRef, useState, useCallback } from 'react';
import { useSelector } from 'react-redux';
import {
  Box,
  Paper,
  Typography,
  Button,
  IconButton,
  CircularProgress,
  Alert,
  AppBar,
  Toolbar,
  Tooltip,
  Badge,
  Avatar,
  AvatarGroup,
  Chip,
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  TextField,
  FormControlLabel,
  Switch,
  Select,
  MenuItem,
  FormControl,
  InputLabel,
  Snackbar,
  List,
  ListItem,
  ListItemText,
  ListItemAvatar,
  ListItemSecondaryAction,
  Divider,
  Grid,
  Card,
  CardContent,
  CardActions,
} from '@mui/material';
import {
  Videocam,
  VideocamOff,
  Mic,
  MicOff,
  ScreenShare,
  StopScreenShare,
  CallEnd,
  Chat,
  People,
  Settings,
  Fullscreen,
  FullscreenExit,
  RecordVoiceOver,
  PanTool,
  EmojiEmotions,
  MoreVert,
  ContentCopy,
  Share,
  Info,
  Warning,
  CheckCircle,
  Error as ErrorIcon,
  Close,
  VolumeUp,
  VolumeOff,
  VideoLibrary,
  CloudRecording,
  Subtitles,
  Poll,
  RaiseHand,
  Group,
  PersonAdd,
  Security,
  Blur,
  WbSunny,
  DarkMode,
} from '@mui/icons-material';

/**
 * Componente wrapper per Jitsi Meet IFrame API
 * Gestisce videoconferenze con room names univoci e configurazione personalizzata
 */
const JitsiMeet = ({
  roomName: propRoomName,
  displayName,
  password,
  subject,
  onMeetingEnd,
  onParticipantJoined,
  onParticipantLeft,
  config = {},
  interfaceConfig = {},
  containerStyle = {},
  enableRecording = false,
  enableLiveStreaming = false,
  startWithAudioMuted = false,
  startWithVideoMuted = false,
  preferredLanguage = 'it',
  customToolbarButtons = [],
}) => {
  const { user } = useSelector((state) => state.auth);
  const { currentTenant } = useSelector((state) => state.tenant);

  // Refs
  const jitsiContainerRef = useRef(null);
  const jitsiApiRef = useRef(null);

  // State
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [meetingStarted, setMeetingStarted] = useState(false);
  const [participants, setParticipants] = useState([]);
  const [isAudioMuted, setIsAudioMuted] = useState(startWithAudioMuted);
  const [isVideoMuted, setIsVideoMuted] = useState(startWithVideoMuted);
  const [isScreenSharing, setIsScreenSharing] = useState(false);
  const [isRecording, setIsRecording] = useState(false);
  const [isFullscreen, setIsFullscreen] = useState(false);
  const [roomUrl, setRoomUrl] = useState('');
  const [meetingInfo, setMeetingInfo] = useState({});
  const [showChat, setShowChat] = useState(false);
  const [showParticipants, setShowParticipants] = useState(false);
  const [showSettings, setShowSettings] = useState(false);
  const [notification, setNotification] = useState({ open: false, message: '', severity: 'info' });
  const [connectionQuality, setConnectionQuality] = useState({});
  const [dominantSpeaker, setDominantSpeaker] = useState(null);
  const [raisedHands, setRaisedHands] = useState([]);
  const [polls, setPolls] = useState([]);
  const [breakoutRooms, setBreakoutRooms] = useState([]);

  // Genera nome room univoco se non fornito
  const generateRoomName = useCallback(() => {
    if (propRoomName) {
      return propRoomName;
    }

    // Formato: tenant-{id}-{uuid}
    const tenantId = currentTenant?.id || 'default';
    const uuid = generateUUID();
    return `tenant-${tenantId}-${uuid}`;
  }, [propRoomName, currentTenant]);

  // Genera UUID per room name
  const generateUUID = () => {
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
      const r = Math.random() * 16 | 0;
      const v = c === 'x' ? r : (r & 0x3 | 0x8);
      return v.toString(16);
    });
  };

  // Inizializza Jitsi Meet
  useEffect(() => {
    if (!jitsiContainerRef.current) return;

    loadJitsiScript()
      .then(() => initializeJitsi())
      .catch((err) => {
        console.error('Error loading Jitsi:', err);
        setError('Impossibile caricare il sistema di videoconferenza');
        setLoading(false);
      });

    // Cleanup
    return () => {
      if (jitsiApiRef.current) {
        jitsiApiRef.current.dispose();
        jitsiApiRef.current = null;
      }
    };
  }, []);

  /**
   * Carica lo script di Jitsi Meet
   */
  const loadJitsiScript = () => {
    return new Promise((resolve, reject) => {
      if (window.JitsiMeetExternalAPI) {
        resolve();
        return;
      }

      const script = document.createElement('script');
      script.src = 'https://meet.jit.si/external_api.js';
      script.async = true;
      script.onload = resolve;
      script.onerror = reject;
      document.body.appendChild(script);
    });
  };

  /**
   * Inizializza l'API di Jitsi Meet
   */
  const initializeJitsi = () => {
    try {
      const roomName = generateRoomName();
      const domain = config.domain || 'meet.jit.si';

      // Configurazione Jitsi
      const options = {
        roomName: roomName,
        parentNode: jitsiContainerRef.current,
        width: '100%',
        height: '100%',
        configOverwrite: {
          startWithAudioMuted: startWithAudioMuted,
          startWithVideoMuted: startWithVideoMuted,
          disableModeratorIndicator: false,
          enableEmailInStats: false,
          enableWelcomePage: false,
          enableClosePage: false,
          disableInviteFunctions: false,
          doNotStoreRoom: true,
          enableCalendarIntegration: false,
          enableNoAudioDetection: true,
          enableNoisyMicDetection: true,
          enableLipSync: true,
          disableRtx: false,
          enableRemb: true,
          enableTcc: true,
          enableRecording: enableRecording,
          enableLiveStreaming: enableLiveStreaming,
          fileRecordingsEnabled: enableRecording,
          liveStreamingEnabled: enableLiveStreaming,
          transcribingEnabled: false,
          autoCaptionOnRecord: false,
          preferH264: true,
          disableH264: false,
          maxFullResolutionParticipants: 2,
          constraints: {
            video: {
              height: {
                ideal: 720,
                max: 720,
                min: 240,
              },
              width: {
                ideal: 1280,
                max: 1280,
                min: 320,
              },
            },
          },
          defaultLanguage: preferredLanguage,
          enableLayerSuspension: true,
          channelLastN: 4,
          startBitrate: 800,
          stereo: true,
          forceJVB121Ratio: true,
          enableTalkWhileMuted: false,
          disableAudioLevels: false,
          requireDisplayName: true,
          enableInsecureRoomNameWarning: true,
          enableAutomaticUrlCopy: true,
          subject: subject || `Riunione ${currentTenant?.name || 'NexioSolution'}`,
          ...config,
        },
        interfaceConfigOverwrite: {
          AUDIO_LEVEL_PRIMARY_COLOR: 'rgba(255,255,255,0.4)',
          AUDIO_LEVEL_SECONDARY_COLOR: 'rgba(255,255,255,0.2)',
          AUTO_PIN_LATEST_SCREEN_SHARE: true,
          BRAND_WATERMARK_LINK: '',
          CLOSE_PAGE_GUEST_HINT: false,
          DEFAULT_BACKGROUND: '#474747',
          DEFAULT_LOCAL_DISPLAY_NAME: 'io',
          DEFAULT_LOGO_URL: '/images/logo.png',
          DEFAULT_REMOTE_DISPLAY_NAME: 'Partecipante',
          DEFAULT_WELCOME_PAGE_LOGO_URL: '/images/logo.png',
          DISABLE_DOMINANT_SPEAKER_INDICATOR: false,
          DISABLE_FOCUS_INDICATOR: false,
          DISABLE_JOIN_LEAVE_NOTIFICATIONS: false,
          DISABLE_PRESENCE_STATUS: false,
          DISABLE_RINGING: false,
          DISABLE_TRANSCRIPTION_SUBTITLES: false,
          DISABLE_VIDEO_BACKGROUND: false,
          DISPLAY_WELCOME_FOOTER: false,
          DISPLAY_WELCOME_PAGE_ADDITIONAL_CARD: false,
          DISPLAY_WELCOME_PAGE_CONTENT: false,
          DISPLAY_WELCOME_PAGE_TOOLBAR_ADDITIONAL_CONTENT: false,
          ENABLE_DIAL_OUT: false,
          ENABLE_FEEDBACK_ANIMATION: false,
          FILM_STRIP_MAX_HEIGHT: 120,
          GENERATE_ROOMNAMES_ON_WELCOME_PAGE: false,
          HIDE_DEEP_LINKING_LOGO: true,
          HIDE_INVITE_MORE_HEADER: false,
          JITSI_WATERMARK_LINK: '',
          LANG_DETECTION: true,
          LOCAL_THUMBNAIL_RATIO: 16 / 9,
          MAX_DISPLAY_NAME_LENGTH: 50,
          MOBILE_APP_PROMO: false,
          NATIVE_APP_NAME: 'NexioSolution Meet',
          POLICY_LOGO: null,
          PROVIDER_NAME: 'NexioSolution',
          RECENT_LIST_ENABLED: false,
          REMOTE_THUMBNAIL_RATIO: 16 / 9,
          SETTINGS_SECTIONS: [
            'devices',
            'language',
            'moderator',
            'profile',
            'calendar',
            'sounds',
            'more',
          ],
          SHOW_BRAND_WATERMARK: false,
          SHOW_CHROME_EXTENSION_BANNER: false,
          SHOW_DEEP_LINKING_IMAGE: false,
          SHOW_JITSI_WATERMARK: false,
          SHOW_POWERED_BY: false,
          SHOW_PROMOTIONAL_CLOSE_PAGE: false,
          SHOW_WATERMARK_FOR_GUESTS: false,
          SUPPORT_URL: `${window.location.origin}/support`,
          TOOLBAR_ALWAYS_VISIBLE: false,
          TOOLBAR_BUTTONS: [
            'microphone',
            'camera',
            'desktop',
            'fullscreen',
            'fodeviceselection',
            'hangup',
            'chat',
            'recording',
            'livestreaming',
            'sharedvideo',
            'shareaudio',
            'settings',
            'raisehand',
            'videoquality',
            'filmstrip',
            'participants-pane',
            'feedback',
            'stats',
            'shortcuts',
            'tileview',
            'download',
            'help',
            'mute-everyone',
            'mute-video-everyone',
            'security',
            'toggle-camera',
            'closedcaptions',
            'participants',
            ...customToolbarButtons,
          ],
          TOOLBAR_TIMEOUT: 4000,
          VERTICAL_FILMSTRIP: true,
          VIDEO_LAYOUT_FIT: 'both',
          VIDEO_QUALITY_LABEL_DISABLED: false,
          ...interfaceConfig,
        },
        userInfo: {
          email: user?.email || '',
          displayName: displayName || user?.name || 'Ospite',
        },
      };

      // Aggiungi password se fornita
      if (password) {
        options.configOverwrite.roomPassword = password;
      }

      // Crea istanza Jitsi
      jitsiApiRef.current = new window.JitsiMeetExternalAPI(domain, options);

      // Imposta URL della room
      const fullRoomUrl = `https://${domain}/${roomName}`;
      setRoomUrl(fullRoomUrl);

      // Registra event listeners
      registerEventListeners();

      // Meeting iniziato
      setMeetingStarted(true);
      setLoading(false);

      // Salva informazioni meeting
      setMeetingInfo({
        roomName,
        domain,
        startTime: new Date(),
        subject: subject || `Riunione ${currentTenant?.name || 'NexioSolution'}`,
      });

    } catch (err) {
      console.error('Error initializing Jitsi:', err);
      setError('Errore nell\'inizializzazione della videoconferenza');
      setLoading(false);
    }
  };

  /**
   * Registra gli event listener di Jitsi
   */
  const registerEventListeners = () => {
    const api = jitsiApiRef.current;
    if (!api) return;

    // Eventi partecipanti
    api.on('participantJoined', handleParticipantJoined);
    api.on('participantLeft', handleParticipantLeft);
    api.on('participantKickedOut', handleParticipantKickedOut);
    api.on('participantRoleChanged', handleParticipantRoleChanged);

    // Eventi audio/video
    api.on('audioMuteStatusChanged', handleAudioMuteStatusChanged);
    api.on('videoMuteStatusChanged', handleVideoMuteStatusChanged);
    api.on('screenSharingStatusChanged', handleScreenSharingStatusChanged);

    // Eventi meeting
    api.on('readyToClose', handleReadyToClose);
    api.on('videoConferenceJoined', handleVideoConferenceJoined);
    api.on('videoConferenceLeft', handleVideoConferenceLeft);

    // Eventi recording
    if (enableRecording) {
      api.on('recordingStatusChanged', handleRecordingStatusChanged);
      api.on('recordingLinkAvailable', handleRecordingLinkAvailable);
    }

    // Eventi live streaming
    if (enableLiveStreaming) {
      api.on('liveStreamingStatusChanged', handleLiveStreamingStatusChanged);
    }

    // Altri eventi
    api.on('dominantSpeakerChanged', handleDominantSpeakerChanged);
    api.on('tileViewChanged', handleTileViewChanged);
    api.on('chatUpdated', handleChatUpdated);
    api.on('incomingMessage', handleIncomingMessage);
    api.on('outgoingMessage', handleOutgoingMessage);
    api.on('displayNameChange', handleDisplayNameChange);
    api.on('emailChange', handleEmailChange);
    api.on('feedbackSubmitted', handleFeedbackSubmitted);
    api.on('filmstripDisplayChanged', handleFilmstripDisplayChanged);
    api.on('raiseHandUpdated', handleRaiseHandUpdated);
    api.on('knockingParticipant', handleKnockingParticipant);
    api.on('logoClickListeningStatusChanged', handleLogoClickListeningStatusChanged);
    api.on('micError', handleMicError);
    api.on('cameraError', handleCameraError);
    api.on('errorOccurred', handleErrorOccurred);
    api.on('avatarChanged', handleAvatarChanged);
    api.on('browserSupport', handleBrowserSupport);
    api.on('deviceListChanged', handleDeviceListChanged);
    api.on('emailChange', handleEmailChange);
    api.on('endpointTextMessageReceived', handleEndpointTextMessageReceived);
    api.on('largeVideoChanged', handleLargeVideoChanged);
    api.on('log', handleLog);
    api.on('mouseEnter', handleMouseEnter);
    api.on('mouseLeave', handleMouseLeave);
    api.on('mouseMove', handleMouseMove);
    api.on('p2pStatusChanged', handleP2pStatusChanged);
    api.on('passwordRequired', handlePasswordRequired);
    api.on('videoAvailabilityChanged', handleVideoAvailabilityChanged);
    api.on('videoMuteStatusChanged', handleVideoMuteStatusChanged);
    api.on('subjectChange', handleSubjectChange);
    api.on('suspendDetected', handleSuspendDetected);

    // Quality
    api.on('videoQualityChanged', handleVideoQualityChanged);
    api.on('localConnectionQualityChanged', handleLocalConnectionQualityChanged);
    api.on('remoteConnectionQualityChanged', handleRemoteConnectionQualityChanged);

    // Breakout rooms
    api.on('breakoutRoomsUpdated', handleBreakoutRoomsUpdated);
  };

  // Event Handlers

  const handleParticipantJoined = (data) => {
    console.log('Participant joined:', data);
    setParticipants(prev => [...prev, data]);
    showNotification(`${data.displayName} è entrato nella riunione`, 'info');

    if (onParticipantJoined) {
      onParticipantJoined(data);
    }
  };

  const handleParticipantLeft = (data) => {
    console.log('Participant left:', data);
    setParticipants(prev => prev.filter(p => p.id !== data.id));
    showNotification(`${data.displayName} ha lasciato la riunione`, 'info');

    if (onParticipantLeft) {
      onParticipantLeft(data);
    }
  };

  const handleParticipantKickedOut = (data) => {
    console.log('Participant kicked out:', data);
    showNotification(`${data.kicked.displayName} è stato espulso dalla riunione`, 'warning');
  };

  const handleParticipantRoleChanged = (data) => {
    console.log('Participant role changed:', data);
    const role = data.role === 'moderator' ? 'moderatore' : 'partecipante';
    showNotification(`${data.displayName} è ora ${role}`, 'info');
  };

  const handleAudioMuteStatusChanged = (data) => {
    console.log('Audio mute status changed:', data);
    setIsAudioMuted(data.muted);
  };

  const handleVideoMuteStatusChanged = (data) => {
    console.log('Video mute status changed:', data);
    setIsVideoMuted(data.muted);
  };

  const handleScreenSharingStatusChanged = (data) => {
    console.log('Screen sharing status changed:', data);
    setIsScreenSharing(data.on);

    if (data.on) {
      showNotification(`${data.details?.displayName || 'Qualcuno'} sta condividendo lo schermo`, 'info');
    }
  };

  const handleReadyToClose = () => {
    console.log('Ready to close');
    handleLeaveMeeting();
  };

  const handleVideoConferenceJoined = (data) => {
    console.log('Video conference joined:', data);
    showNotification('Sei entrato nella riunione', 'success');

    // Ottieni lista partecipanti
    if (jitsiApiRef.current) {
      jitsiApiRef.current.getParticipantsInfo().then(participants => {
        setParticipants(participants);
      });
    }
  };

  const handleVideoConferenceLeft = (data) => {
    console.log('Video conference left:', data);
    showNotification('Hai lasciato la riunione', 'info');
  };

  const handleRecordingStatusChanged = (data) => {
    console.log('Recording status changed:', data);
    setIsRecording(data.on);

    if (data.on) {
      showNotification('Registrazione avviata', 'info');
    } else {
      showNotification('Registrazione terminata', 'info');
    }
  };

  const handleRecordingLinkAvailable = (data) => {
    console.log('Recording link available:', data);
    showNotification('Registrazione disponibile', 'success');

    // Salva link registrazione
    if (data.link) {
      saveMeetingRecording(data.link);
    }
  };

  const handleLiveStreamingStatusChanged = (data) => {
    console.log('Live streaming status changed:', data);

    if (data.on) {
      showNotification('Live streaming avviato', 'info');
    } else {
      showNotification('Live streaming terminato', 'info');
    }
  };

  const handleDominantSpeakerChanged = (data) => {
    console.log('Dominant speaker changed:', data);
    setDominantSpeaker(data.id);
  };

  const handleTileViewChanged = (data) => {
    console.log('Tile view changed:', data);
  };

  const handleChatUpdated = (data) => {
    console.log('Chat updated:', data);
  };

  const handleIncomingMessage = (data) => {
    console.log('Incoming message:', data);
    showNotification(`${data.from}: ${data.message}`, 'info');
  };

  const handleOutgoingMessage = (data) => {
    console.log('Outgoing message:', data);
  };

  const handleDisplayNameChange = (data) => {
    console.log('Display name changed:', data);
  };

  const handleEmailChange = (data) => {
    console.log('Email changed:', data);
  };

  const handleFeedbackSubmitted = (data) => {
    console.log('Feedback submitted:', data);
    showNotification('Grazie per il feedback!', 'success');
  };

  const handleFilmstripDisplayChanged = (data) => {
    console.log('Filmstrip display changed:', data);
  };

  const handleRaiseHandUpdated = (data) => {
    console.log('Raise hand updated:', data);

    if (data.handRaised) {
      setRaisedHands(prev => [...prev, data.id]);
      showNotification(`${data.displayName} ha alzato la mano`, 'info');
    } else {
      setRaisedHands(prev => prev.filter(id => id !== data.id));
    }
  };

  const handleKnockingParticipant = (data) => {
    console.log('Knocking participant:', data);
    showNotification(`${data.participant.name} sta chiedendo di entrare`, 'warning');
  };

  const handleLogoClickListeningStatusChanged = (data) => {
    console.log('Logo click listening status changed:', data);
  };

  const handleMicError = (data) => {
    console.log('Mic error:', data);
    showNotification('Errore microfono: ' + data.error, 'error');
  };

  const handleCameraError = (data) => {
    console.log('Camera error:', data);
    showNotification('Errore camera: ' + data.error, 'error');
  };

  const handleErrorOccurred = (data) => {
    console.log('Error occurred:', data);
    showNotification('Errore: ' + data.error, 'error');
  };

  const handleAvatarChanged = (data) => {
    console.log('Avatar changed:', data);
  };

  const handleBrowserSupport = (data) => {
    console.log('Browser support:', data);

    if (!data.supported) {
      showNotification('Browser non supportato', 'warning');
    }
  };

  const handleDeviceListChanged = (data) => {
    console.log('Device list changed:', data);
  };

  const handleEndpointTextMessageReceived = (data) => {
    console.log('Endpoint text message received:', data);
  };

  const handleLargeVideoChanged = (data) => {
    console.log('Large video changed:', data);
  };

  const handleLog = (data) => {
    console.log('Jitsi log:', data);
  };

  const handleMouseEnter = () => {
    // Mostra toolbar
  };

  const handleMouseLeave = () => {
    // Nascondi toolbar dopo timeout
  };

  const handleMouseMove = () => {
    // Reset timeout toolbar
  };

  const handleP2pStatusChanged = (data) => {
    console.log('P2P status changed:', data);
  };

  const handlePasswordRequired = () => {
    console.log('Password required');
    // Mostra dialog password
    const password = prompt('Inserisci la password della riunione:');
    if (password && jitsiApiRef.current) {
      jitsiApiRef.current.executeCommand('password', password);
    }
  };

  const handleVideoAvailabilityChanged = (data) => {
    console.log('Video availability changed:', data);
  };

  const handleSubjectChange = (data) => {
    console.log('Subject changed:', data);
    setMeetingInfo(prev => ({ ...prev, subject: data.subject }));
  };

  const handleSuspendDetected = () => {
    console.log('Suspend detected');
    showNotification('Connessione sospesa', 'warning');
  };

  const handleVideoQualityChanged = (data) => {
    console.log('Video quality changed:', data);
  };

  const handleLocalConnectionQualityChanged = (data) => {
    console.log('Local connection quality changed:', data);
    setConnectionQuality(prev => ({ ...prev, local: data }));
  };

  const handleRemoteConnectionQualityChanged = (data) => {
    console.log('Remote connection quality changed:', data);
    setConnectionQuality(prev => ({ ...prev, [data.id]: data }));
  };

  const handleBreakoutRoomsUpdated = (data) => {
    console.log('Breakout rooms updated:', data);
    setBreakoutRooms(data.rooms || []);
  };

  // Azioni utente

  const toggleAudio = () => {
    if (jitsiApiRef.current) {
      jitsiApiRef.current.executeCommand('toggleAudio');
    }
  };

  const toggleVideo = () => {
    if (jitsiApiRef.current) {
      jitsiApiRef.current.executeCommand('toggleVideo');
    }
  };

  const toggleScreenShare = () => {
    if (jitsiApiRef.current) {
      jitsiApiRef.current.executeCommand('toggleShareScreen');
    }
  };

  const toggleChat = () => {
    if (jitsiApiRef.current) {
      jitsiApiRef.current.executeCommand('toggleChat');
    }
    setShowChat(!showChat);
  };

  const toggleParticipantsList = () => {
    if (jitsiApiRef.current) {
      jitsiApiRef.current.executeCommand('toggleParticipantsPane');
    }
    setShowParticipants(!showParticipants);
  };

  const toggleFullscreen = () => {
    if (!isFullscreen) {
      jitsiContainerRef.current?.requestFullscreen();
    } else {
      document.exitFullscreen();
    }
    setIsFullscreen(!isFullscreen);
  };

  const toggleRecording = () => {
    if (jitsiApiRef.current) {
      jitsiApiRef.current.executeCommand('toggleRecording');
    }
  };

  const toggleRaiseHand = () => {
    if (jitsiApiRef.current) {
      jitsiApiRef.current.executeCommand('toggleRaiseHand');
    }
  };

  const toggleTileView = () => {
    if (jitsiApiRef.current) {
      jitsiApiRef.current.executeCommand('toggleTileView');
    }
  };

  const toggleVirtualBackground = () => {
    if (jitsiApiRef.current) {
      jitsiApiRef.current.executeCommand('toggleVirtualBackgroundDialog');
    }
  };

  const setVideoQuality = (quality) => {
    if (jitsiApiRef.current) {
      jitsiApiRef.current.executeCommand('setVideoQuality', quality);
    }
  };

  const muteEveryone = () => {
    if (jitsiApiRef.current) {
      jitsiApiRef.current.executeCommand('muteEveryone');
    }
  };

  const kickParticipant = (participantId) => {
    if (jitsiApiRef.current) {
      jitsiApiRef.current.executeCommand('kickParticipant', participantId);
    }
  };

  const grantModerator = (participantId) => {
    if (jitsiApiRef.current) {
      jitsiApiRef.current.executeCommand('grantModerator', participantId);
    }
  };

  const sendChatMessage = (message) => {
    if (jitsiApiRef.current) {
      jitsiApiRef.current.executeCommand('sendChatMessage', message);
    }
  };

  const inviteParticipants = (emails) => {
    if (jitsiApiRef.current) {
      jitsiApiRef.current.invite(emails);
    }
  };

  const handleLeaveMeeting = () => {
    if (jitsiApiRef.current) {
      jitsiApiRef.current.dispose();
      jitsiApiRef.current = null;
    }

    setMeetingStarted(false);

    // Salva statistiche meeting
    saveMeetingStats();

    if (onMeetingEnd) {
      onMeetingEnd({
        roomName: meetingInfo.roomName,
        duration: new Date() - meetingInfo.startTime,
        participants: participants.length,
      });
    }
  };

  const copyRoomLink = () => {
    navigator.clipboard.writeText(roomUrl);
    showNotification('Link copiato negli appunti', 'success');
  };

  const shareRoom = () => {
    if (navigator.share) {
      navigator.share({
        title: meetingInfo.subject,
        text: `Unisciti alla riunione: ${meetingInfo.subject}`,
        url: roomUrl,
      });
    } else {
      copyRoomLink();
    }
  };

  const saveMeetingStats = async () => {
    try {
      const stats = {
        roomName: meetingInfo.roomName,
        subject: meetingInfo.subject,
        startTime: meetingInfo.startTime,
        endTime: new Date(),
        duration: new Date() - meetingInfo.startTime,
        participants: participants.length,
        maxParticipants: participants.length,
        recording: isRecording,
      };

      // Invia statistiche al backend
      await fetch('/api/meetings/stats', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${localStorage.getItem('token')}`,
        },
        body: JSON.stringify(stats),
      });
    } catch (err) {
      console.error('Error saving meeting stats:', err);
    }
  };

  const saveMeetingRecording = async (recordingUrl) => {
    try {
      await fetch('/api/meetings/recordings', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${localStorage.getItem('token')}`,
        },
        body: JSON.stringify({
          roomName: meetingInfo.roomName,
          recordingUrl: recordingUrl,
          date: new Date(),
        }),
      });
    } catch (err) {
      console.error('Error saving recording:', err);
    }
  };

  const showNotification = (message, severity = 'info') => {
    setNotification({ open: true, message, severity });
  };

  // Render loading
  if (loading) {
    return (
      <Box
        display="flex"
        justifyContent="center"
        alignItems="center"
        height="100vh"
        bgcolor="#1a1a1a"
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
        bgcolor="#1a1a1a"
        p={3}
      >
        <Alert severity="error">
          {error}
        </Alert>
      </Box>
    );
  }

  return (
    <Box sx={{ height: '100vh', display: 'flex', flexDirection: 'column', bgcolor: '#1a1a1a' }}>
      {/* Custom Toolbar */}
      <AppBar position="static" color="transparent" elevation={0}>
        <Toolbar variant="dense" sx={{ bgcolor: 'rgba(0,0,0,0.8)', backdropFilter: 'blur(10px)' }}>
          <Typography variant="h6" sx={{ flexGrow: 1, color: 'white' }}>
            {meetingInfo.subject}
          </Typography>

          {/* Room info */}
          <Chip
            label={`Room: ${meetingInfo.roomName}`}
            size="small"
            sx={{ mr: 2, bgcolor: 'rgba(255,255,255,0.1)', color: 'white' }}
          />

          {/* Participants count */}
          <Badge badgeContent={participants.length} color="primary" sx={{ mr: 2 }}>
            <People sx={{ color: 'white' }} />
          </Badge>

          {/* Recording indicator */}
          {isRecording && (
            <Chip
              icon={<CloudRecording />}
              label="REC"
              size="small"
              color="error"
              sx={{ mr: 2 }}
            />
          )}

          {/* Actions */}
          <Tooltip title="Copia link">
            <IconButton onClick={copyRoomLink} sx={{ color: 'white' }}>
              <ContentCopy />
            </IconButton>
          </Tooltip>

          <Tooltip title="Condividi">
            <IconButton onClick={shareRoom} sx={{ color: 'white' }}>
              <Share />
            </IconButton>
          </Tooltip>

          <Tooltip title={isFullscreen ? 'Esci da schermo intero' : 'Schermo intero'}>
            <IconButton onClick={toggleFullscreen} sx={{ color: 'white' }}>
              {isFullscreen ? <FullscreenExit /> : <Fullscreen />}
            </IconButton>
          </Tooltip>

          <Button
            variant="contained"
            color="error"
            startIcon={<CallEnd />}
            onClick={handleLeaveMeeting}
            sx={{ ml: 2 }}
          >
            Esci
          </Button>
        </Toolbar>
      </AppBar>

      {/* Jitsi Container */}
      <Box
        ref={jitsiContainerRef}
        sx={{
          flexGrow: 1,
          position: 'relative',
          bgcolor: '#1a1a1a',
          ...containerStyle,
        }}
      />

      {/* Custom Controls Overlay */}
      {meetingStarted && (
        <Box
          sx={{
            position: 'absolute',
            bottom: 20,
            left: '50%',
            transform: 'translateX(-50%)',
            display: 'flex',
            gap: 1,
            bgcolor: 'rgba(0,0,0,0.8)',
            backdropFilter: 'blur(10px)',
            borderRadius: 2,
            p: 1,
          }}
        >
          <Tooltip title={isAudioMuted ? 'Attiva microfono' : 'Disattiva microfono'}>
            <IconButton
              onClick={toggleAudio}
              sx={{
                bgcolor: isAudioMuted ? 'error.main' : 'rgba(255,255,255,0.1)',
                color: 'white',
                '&:hover': { bgcolor: isAudioMuted ? 'error.dark' : 'rgba(255,255,255,0.2)' },
              }}
            >
              {isAudioMuted ? <MicOff /> : <Mic />}
            </IconButton>
          </Tooltip>

          <Tooltip title={isVideoMuted ? 'Attiva video' : 'Disattiva video'}>
            <IconButton
              onClick={toggleVideo}
              sx={{
                bgcolor: isVideoMuted ? 'error.main' : 'rgba(255,255,255,0.1)',
                color: 'white',
                '&:hover': { bgcolor: isVideoMuted ? 'error.dark' : 'rgba(255,255,255,0.2)' },
              }}
            >
              {isVideoMuted ? <VideocamOff /> : <Videocam />}
            </IconButton>
          </Tooltip>

          <Tooltip title={isScreenSharing ? 'Interrompi condivisione' : 'Condividi schermo'}>
            <IconButton
              onClick={toggleScreenShare}
              sx={{
                bgcolor: isScreenSharing ? 'primary.main' : 'rgba(255,255,255,0.1)',
                color: 'white',
                '&:hover': { bgcolor: isScreenSharing ? 'primary.dark' : 'rgba(255,255,255,0.2)' },
              }}
            >
              {isScreenSharing ? <StopScreenShare /> : <ScreenShare />}
            </IconButton>
          </Tooltip>

          <Divider orientation="vertical" flexItem sx={{ bgcolor: 'rgba(255,255,255,0.2)' }} />

          <Tooltip title="Chat">
            <IconButton
              onClick={toggleChat}
              sx={{
                bgcolor: showChat ? 'primary.main' : 'rgba(255,255,255,0.1)',
                color: 'white',
                '&:hover': { bgcolor: showChat ? 'primary.dark' : 'rgba(255,255,255,0.2)' },
              }}
            >
              <Chat />
            </IconButton>
          </Tooltip>

          <Tooltip title="Partecipanti">
            <IconButton
              onClick={toggleParticipantsList}
              sx={{
                bgcolor: showParticipants ? 'primary.main' : 'rgba(255,255,255,0.1)',
                color: 'white',
                '&:hover': { bgcolor: showParticipants ? 'primary.dark' : 'rgba(255,255,255,0.2)' },
              }}
            >
              <People />
            </IconButton>
          </Tooltip>

          <Tooltip title="Alza la mano">
            <IconButton
              onClick={toggleRaiseHand}
              sx={{
                bgcolor: 'rgba(255,255,255,0.1)',
                color: 'white',
                '&:hover': { bgcolor: 'rgba(255,255,255,0.2)' },
              }}
            >
              <PanTool />
            </IconButton>
          </Tooltip>

          {enableRecording && (
            <Tooltip title={isRecording ? 'Ferma registrazione' : 'Avvia registrazione'}>
              <IconButton
                onClick={toggleRecording}
                sx={{
                  bgcolor: isRecording ? 'error.main' : 'rgba(255,255,255,0.1)',
                  color: 'white',
                  '&:hover': { bgcolor: isRecording ? 'error.dark' : 'rgba(255,255,255,0.2)' },
                }}
              >
                <CloudRecording />
              </IconButton>
            </Tooltip>
          )}

          <Tooltip title="Vista griglia">
            <IconButton
              onClick={toggleTileView}
              sx={{
                bgcolor: 'rgba(255,255,255,0.1)',
                color: 'white',
                '&:hover': { bgcolor: 'rgba(255,255,255,0.2)' },
              }}
            >
              <VideoLibrary />
            </IconButton>
          </Tooltip>

          <Tooltip title="Sfondo virtuale">
            <IconButton
              onClick={toggleVirtualBackground}
              sx={{
                bgcolor: 'rgba(255,255,255,0.1)',
                color: 'white',
                '&:hover': { bgcolor: 'rgba(255,255,255,0.2)' },
              }}
            >
              <Blur />
            </IconButton>
          </Tooltip>

          <Tooltip title="Impostazioni">
            <IconButton
              onClick={() => setShowSettings(true)}
              sx={{
                bgcolor: 'rgba(255,255,255,0.1)',
                color: 'white',
                '&:hover': { bgcolor: 'rgba(255,255,255,0.2)' },
              }}
            >
              <Settings />
            </IconButton>
          </Tooltip>
        </Box>
      )}

      {/* Snackbar notifiche */}
      <Snackbar
        open={notification.open}
        autoHideDuration={6000}
        onClose={() => setNotification({ ...notification, open: false })}
        anchorOrigin={{ vertical: 'top', horizontal: 'center' }}
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

export default JitsiMeet;