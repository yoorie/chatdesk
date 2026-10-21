import { appUrl } from '@/lib/appUrl';
import { brandName } from '../../../lib/brand';
import { friendlyStatus } from '@/lib/friendly';
import { Badge } from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import { EmptyState } from '@/Components/UI/EmptyState';
import { useConfirm } from '@/hooks/useConfirm';
import { useLatest } from '@/hooks/useLatest';
import { useToast } from '@/hooks/useToast';
import AppShell from '@/Layouts/AppShell';
import { isSameAccountId } from '@/lib/utils';
import { useRealtime } from '@/Providers/RealtimeProvider';
import { Head, Link, router, usePage } from '@inertiajs/react';
import axios from 'axios';
import {
  AlertCircle,
  ArrowLeft,
  Check,
  CheckCircle,
  ChevronDown,
  ClipboardList,
  Clock,
  Edit3,
  ExternalLink,
  File as FileIcon,
  FileText,
  Flag,
  Image as ImageIcon,
  Lock,
  MapPin,
  MessageSquare,
  Mic,
  MoreVertical,
  PanelRight,
  Paperclip,
  Pause,
  Play,
  Plus,
  Reply,
  RotateCcw,
  Search,
  Send,
  ShoppingBag,
  Smile,
  Star,
  StickyNote,
  Trash2,
  User,
  UserX,
  X,
  Zap,
} from 'lucide-react';
import type { MouseEvent } from 'react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { encodeMp3, flattenAudioSamples, loadLameEncoder } from './inbox/audio';
import { ContactDetailsBody } from './inbox/ContactDetailsBody';
import { ConversationList } from './inbox/ConversationList';
import {
  auditToTimelineMessage,
  canReactToMessage,
  contactDisplayPhone,
  conversationModeLabel,
  conversationSlaLabel,
  conversationSourceLabel,
  customerServiceWindowFor,
  EMOJI_QUICK,
  emptyContactGallery,
  formatFileSize,
  getConversationLabels,
  INBOX_LABELS,
  inboxDebug,
  isOptimisticMatch,
  isRecordedVoiceFile,
  isRenderableMessageUpdate,
  isSameProviderMessage,
  mergeLastInboundAt,
  messageReplyMetaId,
  messageReplyPreview,
  normalizeChatMessage,
  normalizeConversation,
  normalizeConversationList,
  noteToTimelineMessage,
  QUICK_REPLIES,
  resolveMediaType,
  shortcutFromButtonLabel,
  slaClasses,
  sortMessages,
} from './inbox/helpers';
import { locationMapTiles, locationToWorldPixel, worldPixelToLocation } from './inbox/location';
import { MessageTimeline } from './inbox/MessageTimeline';
import {
  countLoadedMessages,
  resolveMessageCounts,
  visibleTimelineMessages,
} from './inbox/timeline';
import {
  type Agent,
  type CatalogProductOption,
  type ChatMessage,
  type ContactGallery,
  type Conversation,
  type InteractiveMode,
  type LocationMapState,
  type LocationSearchResult,
  type MediaPreview,
  type SavedButtonOption,
  type SavedFormOption,
  type SavedListOption,
  type TemplateOption,
} from './inbox/types';

interface InitialThread {
  conversation_id: number;
  server_time?: string | null;
  messages?: unknown[];
  notes?: any[];
  audit_events?: any[];
}

function seedMessagesFromInitialThread(
  thread: InitialThread | null | undefined
): Record<number, ChatMessage[]> {
  if (!thread?.conversation_id) return {};

  const messages = (Array.isArray(thread.messages) ? thread.messages : [])
    .map(normalizeChatMessage)
    .filter((message): message is ChatMessage => Boolean(message));
  const notes = (Array.isArray(thread.notes) ? thread.notes : []).map(noteToTimelineMessage);
  const events = (Array.isArray(thread.audit_events) ? thread.audit_events : []).map(
    auditToTimelineMessage
  );

  return { [Number(thread.conversation_id)]: sortMessages([...messages, ...notes, ...events]) };
}

function initialStatusCursor(thread: InitialThread | null | undefined): Record<number, string> {
  if (!thread?.conversation_id || !thread.server_time) return {};
  const serverTime = new Date(thread.server_time).getTime();
  if (!Number.isFinite(serverTime)) return {};

  return { [Number(thread.conversation_id)]: new Date(serverTime - 30000).toISOString() };
}

export default function ConversationsIndex({
  account,
  conversations: initialConversations,
  connections,
  agents: initialAgents = [],
  templates = [],
  catalog_products: catalogProducts = [],
  saved_buttons: savedButtons = [],
  saved_lists: savedLists = [],
  saved_forms: savedForms = [],
  filters: initialFilters = { search: '' },
  selected_conversation_id: selectedConversationId = null,
  initial_thread: initialThread = null,
  new_chat_open: newChatOpen = false,
}: {
  account: any;
  conversations: {
    data: Conversation[];
    links: any;
    meta: any;
  };
  connections?: Array<{ id: number; name: string }>;
  agents?: Agent[];
  templates?: TemplateOption[];
  catalog_products?: CatalogProductOption[];
  saved_buttons?: SavedButtonOption[];
  saved_lists?: SavedListOption[];
  saved_forms?: SavedFormOption[];
  filters?: {
    search?: string;
    assignee?: string;
    status?: string;
    connection_id?: string | number;
  };
  selected_conversation_id?: number | null;
  initial_thread?: InitialThread | null;
  new_chat_open?: boolean;
}) {
  const { subscribe, connected } = useRealtime();
  const { addToast } = useToast();
  const confirm = useConfirm();
  const { auth, workspace_permissions } = usePage().props as any;
  const currentUserId = auth?.user?.id;
  const canDeleteChats = Boolean(workspace_permissions?.['chats.delete']);
  const notifyAssignmentEnabled = auth?.user?.notify_assignment_enabled ?? true;
  const soundEnabled = auth?.user?.notify_sound_enabled ?? true;
  const [conversations, setConversations] = useState<Conversation[]>(
    normalizeConversationList(initialConversations?.data)
  );
  const [notificationPermission, setNotificationPermission] = useState<NotificationPermission>(
    typeof window !== 'undefined' && 'Notification' in window ? Notification.permission : 'denied'
  );
  const [loading] = useState(false);
  const [searchQuery, setSearchQuery] = useState(initialFilters?.search ?? '');
  const [statusFilter, setStatusFilter] = useState<string>(initialFilters?.status ?? 'all');
  const [connectionFilter, setConnectionFilter] = useState<number | 'all'>(
    initialFilters?.connection_id !== undefined && initialFilters?.connection_id !== 'all'
      ? Number(initialFilters.connection_id)
      : 'all'
  );
  const [labelFilter, setLabelFilter] = useState<string>('all');
  const [bulkMode, setBulkMode] = useState(false);
  const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set());
  const [assigneeFilter, setAssigneeFilter] = useState<'all' | 'me' | 'unassigned'>(
    (initialFilters?.assignee as 'all' | 'me' | 'unassigned') ?? 'all'
  );
  const [assigningId, setAssigningId] = useState<number | null>(null);
  const [activeId, setActiveId] = useState<number | null>(
    selectedConversationId ? Number(selectedConversationId) : null
  );
  const [mobileShowDetail, setMobileShowDetail] = useState(false);
  const [showContactPanel, setShowContactPanel] = useState(true);
  const [mobileContactPanelOpen, setMobileContactPanelOpen] = useState(false);
  const [editingContact, setEditingContact] = useState(false);
  const [savingContact, setSavingContact] = useState(false);
  const [contactForm, setContactForm] = useState({
    name: '',
    email: '',
    phone: '',
    company: '',
    status: 'active',
    notes: '',
  });
  const [actionsMenuOpen, setActionsMenuOpen] = useState(false);
  const [starredOnly, setStarredOnly] = useState(false);
  const [contactTab, setContactTab] = useState<
    'profile' | 'media' | 'notes' | 'activity' | 'orders'
  >('profile');
  const [showNewChat, setShowNewChat] = useState(Boolean(newChatOpen));
  const [newChatForm, setNewChatForm] = useState({
    name: '',
    wa_id: '',
    connection_id: connections?.[0]?.id ? String(connections[0].id) : '',
  });
  const [creatingChat, setCreatingChat] = useState(false);
  const [deletingConversationId, setDeletingConversationId] = useState<number | null>(null);
  // The open chat's latest messages arrive with the page, so the thread renders without a spinner.
  const [seededMessages] = useState<Record<number, ChatMessage[]>>(() =>
    seedMessagesFromInitialThread(initialThread)
  );
  const [messagesByConversation, setMessagesByConversation] =
    useState<Record<number, ChatMessage[]>>(seededMessages);
  const [loadingConversationId, setLoadingConversationId] = useState<number | null>(null);
  const [messageReactions, setMessageReactions] = useState<Record<string, string>>({});
  const [mediaPreview, setMediaPreview] = useState<MediaPreview | null>(null);
  const [galleryByConversation, setGalleryByConversation] = useState<
    Record<number, ContactGallery>
  >({});
  const [galleryLoadingId, setGalleryLoadingId] = useState<number | null>(null);
  const [replyWindowNow, setReplyWindowNow] = useState(() => Date.now());
  const [messageDraft, setMessageDraft] = useState('');
  const [replyTo, setReplyTo] = useState<ChatMessage | null>(null);
  const [sendingMessage, setSendingMessage] = useState(false);
  const [noteMode, setNoteMode] = useState(false);
  const [showEmoji, setShowEmoji] = useState(false);
  const [showAttachments, setShowAttachments] = useState(false);
  const [showLocationPicker, setShowLocationPicker] = useState(false);
  const [showTemplates, setShowTemplates] = useState(false);
  const [showQuickReplies, setShowQuickReplies] = useState(false);
  const [showInteractiveComposer, setShowInteractiveComposer] = useState(false);
  const [interactiveMode, setInteractiveMode] = useState<InteractiveMode>('buttons');
  const [savedButtonOptions, setSavedButtonOptions] = useState<SavedButtonOption[]>(
    Array.isArray(savedButtons) ? savedButtons : []
  );
  const [newInteractiveButtonLabel, setNewInteractiveButtonLabel] = useState('');
  const [savingInteractiveButton, setSavingInteractiveButton] = useState(false);
  const [interactiveDraft, setInteractiveDraft] = useState({
    header_text: '',
    body_text: 'How can we help you?',
    footer_text: '',
    buttons: ['Talk to sales', 'Support'],
    saved_button_ids: [] as number[],
    list_id: '',
    list_button_text: 'Choose',
    rows: ['Pricing', 'Book demo', 'Talk to support'],
    form_id: '',
    form_button_text: 'Open form',
    product_id: '',
    catalog_id: '',
    product_retailer_id: '',
    cta_display_text: 'Open link',
    cta_url: '',
    payment_amount: '',
    payment_description: 'WhatsApp payment request',
    payment_button_text: 'Pay now',
    payment_expire_after_days: '',
    contact_name: `${brandName()} Support`,
    contact_phone: '',
    contact_email: '',
  });
  const [attachments, setAttachments] = useState<File[]>([]);
  const [filePickerAccept, setFilePickerAccept] = useState<string | undefined>();
  const [locationInput, setLocationInput] = useState({ label: '', latitude: '', longitude: '' });
  const [locationMap, setLocationMap] = useState<LocationMapState>({
    lat: 28.6139,
    lng: 77.209,
    zoom: 12,
  });
  const [locationSearch, setLocationSearch] = useState('');
  const [locationResults, setLocationResults] = useState<LocationSearchResult[]>([]);
  const [locationSearching, setLocationSearching] = useState(false);
  const [recordingVoice, setRecordingVoice] = useState(false);

  useEffect(() => {
    setSavedButtonOptions(Array.isArray(savedButtons) ? savedButtons : []);
  }, [savedButtons]);
  const messagesEndRef = useRef<HTMLDivElement | null>(null);
  const composerRef = useRef<HTMLDivElement | null>(null);
  const attachmentPreviewRef = useRef<HTMLDivElement | null>(null);
  const fileInputRef = useRef<HTMLInputElement | null>(null);
  const voiceStreamRef = useRef<MediaStream | null>(null);
  const voiceAudioContextRef = useRef<AudioContext | null>(null);
  const voiceProcessorRef = useRef<ScriptProcessorNode | null>(null);
  const voiceSourceRef = useRef<MediaStreamAudioSourceNode | null>(null);
  const voiceSamplesRef = useRef<Float32Array[]>([]);
  const agents: Agent[] = Array.isArray(initialAgents) ? initialAgents : [];
  const lastPollRef = useRef<Date>(new Date(Date.now() - 15000));
  const messagesByConversationRef = useRef<Record<number, ChatMessage[]>>(seededMessages);
  const conversationFetchSequenceRef = useRef<Record<number, number>>({});
  const conversationStatusCursorRef = useRef<Record<number, string>>(
    initialStatusCursor(initialThread)
  );
  const seededConversationIdRef = useRef<number | null>(
    initialThread?.conversation_id ? Number(initialThread.conversation_id) : null
  );
  const processedMessageIds = useRef<Set<string>>(new Set());
  const browserNotificationIds = useRef<Set<string>>(new Set());
  const assignmentStateRef = useRef<Map<number, number | null>>(
    new Map(
      normalizeConversationList(initialConversations?.data).map((c) => [
        c.id,
        c.assigned_to ?? null,
      ])
    )
  );
  const handoffStateRef = useRef<Map<number, string | null>>(
    new Map(
      normalizeConversationList(initialConversations?.data).map((c) => [
        c.id,
        c.handoff_status ?? null,
      ])
    )
  );

  useEffect(() => {
    messagesByConversationRef.current = messagesByConversation;
  }, [messagesByConversation]);

  useEffect(() => {
    const interval = window.setInterval(() => setReplyWindowNow(Date.now()), 60000);
    return () => window.clearInterval(interval);
  }, []);

  const closeComposerPopovers = useCallback(() => {
    setShowEmoji(false);
    setShowAttachments(false);
    setShowLocationPicker(false);
    setShowTemplates(false);
    setShowQuickReplies(false);
    setShowInteractiveComposer(false);
  }, []);

  useEffect(() => {
    const hasOpenPopover =
      showEmoji ||
      showAttachments ||
      showLocationPicker ||
      showTemplates ||
      showQuickReplies ||
      showInteractiveComposer;

    if (!hasOpenPopover) return;

    const handlePointerDown = (event: PointerEvent) => {
      const target = event.target;
      if (target instanceof Node && composerRef.current?.contains(target)) {
        return;
      }

      closeComposerPopovers();
    };

    document.addEventListener('pointerdown', handlePointerDown);
    return () => document.removeEventListener('pointerdown', handlePointerDown);
  }, [
    closeComposerPopovers,
    showAttachments,
    showEmoji,
    showInteractiveComposer,
    showLocationPicker,
    showQuickReplies,
    showTemplates,
  ]);

  useEffect(() => {
    const hasOpenPopover =
      showEmoji ||
      showAttachments ||
      showLocationPicker ||
      showTemplates ||
      showQuickReplies ||
      showInteractiveComposer;

    if (!hasOpenPopover) return;

    const handleKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        closeComposerPopovers();
      }
    };

    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, [
    closeComposerPopovers,
    showAttachments,
    showEmoji,
    showInteractiveComposer,
    showLocationPicker,
    showQuickReplies,
    showTemplates,
  ]);

  useEffect(() => {
    closeComposerPopovers();
  }, [activeId, noteMode, closeComposerPopovers]);

  const playNotificationSound = useCallback(() => {
    if (!soundEnabled) return;
    try {
      const AudioContextRef = (window as any).AudioContext || (window as any).webkitAudioContext;
      if (!AudioContextRef) return;
      const context = new AudioContextRef();
      const gain = context.createGain();
      gain.gain.value = 0.12;
      gain.connect(context.destination);

      [0, 0.16].forEach((offset, index) => {
        const oscillator = context.createOscillator();
        oscillator.type = 'sine';
        oscillator.frequency.value = index === 0 ? 880 : 1174;
        oscillator.connect(gain);
        oscillator.start(context.currentTime + offset);
        oscillator.stop(context.currentTime + offset + 0.14);
      });
      window.setTimeout(() => void context.close?.(), 420);
    } catch (error) {
      if (inboxDebug) {
        console.warn('[Notifications] Unable to play sound', error);
      }
    }
  }, [soundEnabled]);

  const requestBrowserNotifications = useCallback(async () => {
    if (typeof window === 'undefined' || !('Notification' in window)) {
      addToast({
        title: 'Browser notifications unavailable',
        description: 'This browser does not support notifications.',
        variant: 'warning',
      });
      return;
    }

    const permission = await Notification.requestPermission();
    setNotificationPermission(permission);
    if (permission === 'granted') {
      playNotificationSound();
    }
    addToast({
      title:
        permission === 'granted'
          ? 'Browser notifications enabled'
          : 'Browser notifications blocked',
      description:
        permission === 'granted'
          ? 'Incoming messages will show browser alerts and play sound.'
          : 'Enable notifications from browser site settings to receive call alerts.',
      variant: permission === 'granted' ? 'success' : 'warning',
    });
  }, [addToast, playNotificationSound]);

  const showBrowserNotification = useCallback(
    (id: string, title: string, body?: string, conversationId?: number) => {
      if (typeof window === 'undefined' || !('Notification' in window)) return;
      if (Notification.permission !== 'granted') return;
      if (browserNotificationIds.current.has(id)) return;

      browserNotificationIds.current.add(id);
      if (browserNotificationIds.current.size > 80) {
        const ids = Array.from(browserNotificationIds.current);
        browserNotificationIds.current = new Set(ids.slice(-40));
      }

      const notification = new Notification(title, {
        body,
        tag: id,
        icon: appUrl('/favicon.ico'),
        badge: appUrl('/favicon.ico'),
        silent: false,
        renotify: true,
      } as NotificationOptions & { renotify?: boolean });

      notification.onclick = () => {
        window.focus();
        if (conversationId) {
          router.get(
            route('app.whatsapp.conversations.index', { conversation: conversationId }),
            {},
            {
              preserveState: true,
              preserveScroll: true,
            }
          );
        }
        notification.close();
      };
    },
    []
  );

  const notifyInboundMessage = useCallback(
    (payload: {
      id: string;
      conversationId?: number;
      contactName?: string | null;
      preview?: string | null;
      activeConversationId?: number | null;
    }) => {
      const title = payload.contactName
        ? `New message from ${payload.contactName}`
        : 'New WhatsApp message';
      const description = payload.preview || 'Open inbox to reply.';

      addToast({
        title: 'New message',
        description: payload.contactName ? `From ${payload.contactName}` : description,
        variant: 'info',
        duration: 3000,
      });
      playNotificationSound();

      if (
        notificationPermission === 'granted' ||
        (typeof Notification !== 'undefined' && Notification.permission === 'granted')
      ) {
        showBrowserNotification(payload.id, title, description, payload.conversationId);
      }
    },
    [addToast, notificationPermission, playNotificationSound, showBrowserNotification]
  );

  const parseMessageDate = (value: string | null) => {
    if (!value) return null;
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? null : date;
  };

  const formatTime = (value: string | null) => {
    const date = parseMessageDate(value);
    if (!date) return '';
    return new Intl.DateTimeFormat(undefined, { hour: '2-digit', minute: '2-digit' }).format(date);
  };

  const formatDateKey = (value: string | null) => {
    const date = parseMessageDate(value);
    if (!date) return 'unknown';
    return `${date.getFullYear()}-${date.getMonth() + 1}-${date.getDate()}`;
  };

  const formatDateLabel = (value: string | null) => {
    const date = parseMessageDate(value);
    if (!date) return 'Unknown date';

    const today = new Date();
    const yesterday = new Date();
    yesterday.setDate(today.getDate() - 1);

    if (formatDateKey(value) === formatDateKey(today.toISOString())) return 'Today';
    if (formatDateKey(value) === formatDateKey(yesterday.toISOString())) return 'Yesterday';

    return new Intl.DateTimeFormat(undefined, {
      day: '2-digit',
      month: 'short',
      year: date.getFullYear() === today.getFullYear() ? undefined : 'numeric',
    }).format(date);
  };

  const formatExactDateTime = (value: string | null) => {
    const date = parseMessageDate(value);
    if (!date) return '';
    return new Intl.DateTimeFormat(undefined, {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    }).format(date);
  };

  const formatRelative = (value: string | null) => {
    if (!value) return '';
    const diff = Date.now() - new Date(value).getTime();
    const minutes = Math.max(1, Math.round(diff / 60000));
    if (minutes < 60) return `${minutes}m`;
    const hours = Math.round(minutes / 60);
    if (hours < 24) return `${hours}h`;
    return `${Math.round(hours / 24)}d`;
  };

  const api =
    typeof window !== 'undefined' && (window as any).axios ? (window as any).axios : axios;

  const fetchInboxStreamAndMerge = useCallback(() => {
    if (!account?.id) return;
    const since = lastPollRef.current.toISOString();
    api
      .get(route('app.whatsapp.inbox.stream', {}), { params: { since } })
      .then((response: any) => {
        const list = response.data?.updated_conversations;
        const currentAccountId = account?.id;
        if (list?.length) {
          setConversations((prev) => {
            const byId = new Map(prev.map((c) => [c.id, c]));
            list.forEach((conv: any) => {
              const normalized = normalizeConversation(conv);
              if (!normalized) return;
              if (
                currentAccountId != null &&
                normalized.account_id != null &&
                !isSameAccountId(normalized.account_id, currentAccountId)
              ) {
                return;
              }
              const existing = byId.get(normalized.id);
              byId.set(
                normalized.id,
                existing
                  ? {
                      ...existing,
                      ...normalized,
                      last_inbound_message_at: mergeLastInboundAt(
                        existing.last_inbound_message_at,
                        normalized.last_inbound_message_at
                      ),
                    }
                  : normalized
              );
            });
            return Array.from(byId.values()).sort((a, b) => {
              const timeA = a.last_message_at ? new Date(a.last_message_at).getTime() : 0;
              const timeB = b.last_message_at ? new Date(b.last_message_at).getTime() : 0;
              return timeB - timeA;
            });
          });
        }

        const notifications = Array.isArray(response.data?.new_message_notifications)
          ? response.data.new_message_notifications
          : [];
        notifications.forEach((item: any) => {
          const conversationId = Number(item.conversation_id);
          const eventId = `poll-inbound-${conversationId}-${item.last_activity_at || ''}`;
          if (!Number.isFinite(conversationId) || processedMessageIds.current.has(eventId)) return;
          processedMessageIds.current.add(eventId);
          notifyInboundMessage({
            id: eventId,
            conversationId,
            contactName: item.contact?.name || item.contact?.wa_id || null,
            preview: item.last_message_preview || null,
            activeConversationId: activeId,
          });
        });
        const serverTime = new Date(response.data?.server_time || new Date()).getTime();
        lastPollRef.current = new Date(
          (Number.isFinite(serverTime) ? serverTime : Date.now()) - 10000
        );
      })
      .catch((err: any) => {
        if (inboxDebug) {
          console.warn('[Inbox] Stream fetch failed:', err?.message);
        }
      });
  }, [account?.id, activeId, notifyInboundMessage, api]);

  // Keep state in sync with server payload (in case of hydration/props mismatch)
  useEffect(() => {
    setConversations(normalizeConversationList(initialConversations?.data));
  }, [initialConversations]);

  // Backend search: debounce and reload with search param
  const lastSearchRef = useRef(initialFilters?.search ?? '');
  useEffect(() => {
    const t = setTimeout(() => {
      if (searchQuery === lastSearchRef.current) return;
      lastSearchRef.current = searchQuery;
      router.get(
        route('app.whatsapp.conversations.index', {}),
        {
          search: searchQuery || undefined,
          assignee: assigneeFilter !== 'all' ? assigneeFilter : undefined,
          status: statusFilter !== 'all' ? statusFilter : undefined,
          connection_id: connectionFilter !== 'all' ? connectionFilter : undefined,
        },
        { preserveState: true }
      );
    }, 400);
    return () => clearTimeout(t);
  }, [searchQuery, assigneeFilter, statusFilter, connectionFilter]);

  // Reload when assignee, status, or connection filter changes (server-side filter for correct pagination)
  const lastFiltersRef = useRef({
    assignee: assigneeFilter,
    status: statusFilter,
    connection_id: connectionFilter,
  });
  useEffect(() => {
    if (
      lastFiltersRef.current.assignee === assigneeFilter &&
      lastFiltersRef.current.status === statusFilter &&
      lastFiltersRef.current.connection_id === connectionFilter
    ) {
      return;
    }
    lastFiltersRef.current = {
      assignee: assigneeFilter,
      status: statusFilter,
      connection_id: connectionFilter,
    };
    router.get(
      route('app.whatsapp.conversations.index', {}),
      {
        search: searchQuery || undefined,
        assignee: assigneeFilter !== 'all' ? assigneeFilter : undefined,
        status: statusFilter !== 'all' ? statusFilter : undefined,
        connection_id: connectionFilter !== 'all' ? connectionFilter : undefined,
      },
      { preserveState: true }
    );
  }, [assigneeFilter, statusFilter, connectionFilter, searchQuery]);

  // Filter conversations (client-side for status/connection; search is server-side when we use backend)
  const filteredConversations = useMemo(() => {
    return conversations.filter((conv) => {
      // Only show conversations for current account (avoid 404 when clicking)
      if (
        conv.account_id != null &&
        account?.id != null &&
        !isSameAccountId(conv.account_id, account.id)
      ) {
        return false;
      }
      // Client-side search only when we have server results (backend already filtered by search; client refines by status/connection)
      if (searchQuery) {
        const query = searchQuery.toLowerCase();
        const matchesName = conv.contact.name?.toLowerCase().includes(query);
        const matchesWaId = conv.contact.wa_id?.toLowerCase().includes(query);
        const matchesPreview = conv.last_message_preview?.toLowerCase().includes(query);
        if (!matchesName && !matchesWaId && !matchesPreview) {
          return false;
        }
      }

      // Status filter
      if (statusFilter !== 'all' && conv.status !== statusFilter) {
        return false;
      }

      // Connection filter
      if (connectionFilter !== 'all' && conv.connection.id !== connectionFilter) {
        return false;
      }

      // Assignee filter
      if (assigneeFilter === 'me') {
        if (currentUserId == null || conv.assigned_to !== currentUserId) return false;
      } else if (assigneeFilter === 'unassigned') {
        if (conv.assigned_to != null) return false;
      }

      if (starredOnly && conv.priority !== 'urgent') {
        return false;
      }

      if (labelFilter !== 'all' && !getConversationLabels(conv).includes(labelFilter)) {
        return false;
      }

      return true;
    });
  }, [
    conversations,
    account?.id,
    searchQuery,
    statusFilter,
    connectionFilter,
    assigneeFilter,
    currentUserId,
    starredOnly,
    labelFilter,
  ]);

  const activeConversation = useMemo(() => {
    return (
      filteredConversations.find((conversation) => conversation.id === activeId) ??
      filteredConversations[0] ??
      null
    );
  }, [filteredConversations, activeId]);

  const activeMessages = useMemo(
    () => (activeConversation ? (messagesByConversation[activeConversation.id] ?? []) : []),
    [activeConversation, messagesByConversation]
  );
  const activeConversationLoading = Boolean(
    activeConversation && loadingConversationId === activeConversation.id
  );
  const activeAutomationState = activeConversation?.automation_state ?? null;
  const activeBotIsReplying = Boolean(activeConversation?.automation_processing);
  const activeManualMode = Boolean(
    activeConversation?.bot_paused || activeConversation?.handoff_status === 'manual'
  );
  const activeAgentName = activeConversation?.assigned_to
    ? (agents.find((agent) => agent.id === activeConversation.assigned_to)?.name ?? 'Assigned')
    : 'Unassigned';
  const activeLabels = activeConversation ? getConversationLabels(activeConversation) : [];
  // Auto-reply status rows are trimmed to one friendly line, and only when a reply went out or failed.
  const activeDisplayMessages = useMemo(
    () => visibleTimelineMessages(activeMessages.filter((message) => message.type !== 'reaction')),
    [activeMessages]
  );
  // Real customer/business messages only: timeline events are not messages, so they must not
  // count as "received", keep the 24h reply window open, or trigger mark-as-read.
  const activeTimelineMessages = activeMessages.filter(
    (message) => !['internal_note', 'reaction', 'automation_status'].includes(message.type)
  );
  const activeNotes = activeMessages.filter((message) => message.type === 'internal_note');
  const firstAttachment = attachments[0] ?? null;
  const attachmentSummary = firstAttachment
    ? `${firstAttachment.name || 'Attachment'}${attachments.length > 1 ? ` +${attachments.length - 1}` : ''}`
    : null;
  const activeMessageReactions = useMemo(() => {
    const reactions: Record<string, string> = {};
    const byMetaId = new Map<string, ChatMessage>();
    activeMessages.forEach((message) => {
      if (message.meta_message_id) {
        byMetaId.set(message.meta_message_id, message);
      }
    });

    activeMessages
      .filter((message) => message.type === 'reaction')
      .forEach((message) => {
        const emoji = message.payload?.reaction?.emoji || message.text_body;
        const targetLocalId = message.payload?.reaction?.target_local_message_id;
        const targetMetaId = message.payload?.reaction?.message_id;
        const target = targetLocalId
          ? activeMessages.find((item) => String(item.id) === String(targetLocalId))
          : targetMetaId
            ? byMetaId.get(String(targetMetaId))
            : null;
        if (emoji && target) {
          reactions[String(target.id)] = emoji;
        }
      });

    return reactions;
  }, [activeMessages]);
  const activeMessageCounts = resolveMessageCounts(
    activeConversation?.message_counts,
    countLoadedMessages(activeTimelineMessages)
  );
  const activeInboundCount = activeMessageCounts.received;
  const activeOutboundCount = activeMessageCounts.sent;
  const activeGallery = activeConversation
    ? (galleryByConversation[activeConversation.id] ?? emptyContactGallery())
    : emptyContactGallery();
  const activeMediaCount =
    activeGallery.counts.total ||
    activeTimelineMessages.filter((message) =>
      ['image', 'video', 'document', 'audio', 'sticker'].includes(message.type)
    ).length;
  const activeReplyWindow = customerServiceWindowFor(
    activeConversation,
    activeTimelineMessages,
    replyWindowNow
  );
  const freeFormReplyBlocked = !noteMode && !activeReplyWindow.isOpen;
  const lastInboundAt = activeReplyWindow.lastInboundAt;
  const lastOutboundAt =
    [...activeTimelineMessages].reverse().find((message) => message.direction === 'outbound')
      ?.created_at ?? null;
  const pickedLocation = useMemo(() => {
    const latitude = Number(locationInput.latitude);
    const longitude = Number(locationInput.longitude);

    return Number.isFinite(latitude) && Number.isFinite(longitude) ? { latitude, longitude } : null;
  }, [locationInput.latitude, locationInput.longitude]);
  const pickedLocationMapUrl = pickedLocation
    ? `https://www.google.com/maps/search/?api=1&query=${pickedLocation.latitude},${pickedLocation.longitude}`
    : null;
  const locationTiles = useMemo(() => locationMapTiles(locationMap), [locationMap]);

  useEffect(() => {
    if (!activeConversation) {
      setActiveId(null);
      return;
    }
    if (activeId !== activeConversation.id) {
      setActiveId(activeConversation.id);
    }
  }, [activeConversation, activeId]);

  useEffect(() => {
    if (selectedConversationId) {
      setActiveId(Number(selectedConversationId));
      setMobileShowDetail(true);
    }
  }, [selectedConversationId]);

  useEffect(() => {
    if (!activeConversation || galleryByConversation[activeConversation.id]) {
      return;
    }

    let cancelled = false;
    setGalleryLoadingId(activeConversation.id);

    axios
      .get(route('app.whatsapp.conversations.gallery', { conversation: activeConversation.id }))
      .then((response) => {
        if (cancelled) return;
        setGalleryByConversation((current) => ({
          ...current,
          [activeConversation.id]: {
            media: Array.isArray(response.data?.media) ? response.data.media : [],
            documents: Array.isArray(response.data?.documents) ? response.data.documents : [],
            links: Array.isArray(response.data?.links) ? response.data.links : [],
            counts: {
              media: Number(response.data?.counts?.media ?? 0),
              documents: Number(response.data?.counts?.documents ?? 0),
              links: Number(response.data?.counts?.links ?? 0),
              total: Number(response.data?.counts?.total ?? 0),
            },
          },
        }));
      })
      .catch(() => {
        if (cancelled) return;
        setGalleryByConversation((current) => ({
          ...current,
          [activeConversation.id]: emptyContactGallery(),
        }));
      })
      .finally(() => {
        if (!cancelled) {
          setGalleryLoadingId(null);
        }
      });

    return () => {
      cancelled = true;
    };
  }, [activeConversation, galleryByConversation]);

  useEffect(() => {
    if (newChatOpen) {
      setShowNewChat(true);
    }
  }, [newChatOpen]);

  const mergeMessages = useCallback((conversationId: number, incoming: ChatMessage[]) => {
    if (!incoming.length) return;
    setMessagesByConversation((prev) => {
      const existing = prev[conversationId] ?? [];
      const byId = new Map<string, ChatMessage>();
      existing.forEach((message) => byId.set(String(message.id), message));
      incoming.forEach((message) => {
        for (const [id, existingMessage] of byId) {
          if (
            isOptimisticMatch(existingMessage, message) ||
            isSameProviderMessage(existingMessage, message)
          ) {
            byId.delete(id);
          }
        }

        const id = String(message.id);
        const current = byId.get(id);
        byId.set(
          id,
          current
            ? { ...current, ...message, payload: message.payload ?? current.payload }
            : message
        );
      });

      const next = {
        ...prev,
        [conversationId]: sortMessages(Array.from(byId.values())),
      };
      messagesByConversationRef.current = next;

      return next;
    });
  }, []);

  const patchMessage = useCallback((conversationId: number, incoming: ChatMessage) => {
    setMessagesByConversation((prev) => {
      const existing = prev[conversationId] ?? [];
      const index = existing.findIndex((message) => String(message.id) === String(incoming.id));
      if (index === -1) {
        if (!isRenderableMessageUpdate(incoming)) {
          return prev;
        }

        const next = {
          ...prev,
          [conversationId]: sortMessages([...existing, incoming]),
        };
        messagesByConversationRef.current = next;

        return next;
      }

      const updated = [...existing];
      updated[index] = {
        ...updated[index],
        ...incoming,
        payload: incoming.payload ?? updated[index].payload,
      };

      const next = {
        ...prev,
        [conversationId]: sortMessages(updated),
      };
      messagesByConversationRef.current = next;

      return next;
    });
  }, []);

  const conversationsRef = useLatest(conversations);
  const fetchConversationMessages = useCallback(
    (conversationId: number, reset = false) => {
      const sequence = (conversationFetchSequenceRef.current[conversationId] ?? 0) + 1;
      conversationFetchSequenceRef.current[conversationId] = sequence;
      if (reset) {
        setLoadingConversationId(conversationId);
      }

      const current = reset ? [] : (messagesByConversationRef.current[conversationId] ?? []);
      const afterMessageId = current
        .map((message) => Number(message.id))
        .filter((id) => Number.isFinite(id))
        .reduce((max, id) => Math.max(max, id), 0);
      const afterNoteId = current
        .map((message) => String(message.id).match(/^note-(\d+)$/)?.[1])
        .filter((id): id is string => Boolean(id))
        .map((id) => Number(id))
        .filter((id) => Number.isFinite(id))
        .reduce((max, id) => Math.max(max, id), 0);
      const afterAuditId = current
        .map((message) => String(message.id).match(/^audit-(\d+)$/)?.[1])
        .filter((id): id is string => Boolean(id))
        .map((id) => Number(id))
        .filter((id) => Number.isFinite(id))
        .reduce((max, id) => Math.max(max, id), 0);
      const afterUpdatedAt = conversationStatusCursorRef.current[conversationId];

      return axios
        .get(route('app.whatsapp.inbox.conversation.stream', { conversation: conversationId }), {
          params: {
            after_message_id: reset ? 0 : afterMessageId,
            after_note_id: reset ? 0 : afterNoteId,
            after_audit_id: reset ? 0 : afterAuditId,
            after_updated_at: reset ? undefined : afterUpdatedAt,
          },
        })
        .then((response) => {
          if (!reset && conversationFetchSequenceRef.current[conversationId] !== sequence) {
            return;
          }

          if (response.data?.server_time) {
            const serverTime = new Date(response.data.server_time).getTime();
            if (Number.isFinite(serverTime)) {
              conversationStatusCursorRef.current[conversationId] = new Date(
                serverTime - 30000
              ).toISOString();
            }
          }

          const incoming = Array.isArray(response.data?.new_messages)
            ? response.data.new_messages.map(normalizeChatMessage).filter(Boolean)
            : [];
          const incomingNotes = Array.isArray(response.data?.new_notes)
            ? response.data.new_notes.map(noteToTimelineMessage)
            : [];
          const incomingAuditEvents = Array.isArray(response.data?.new_audit_events)
            ? response.data.new_audit_events.map(auditToTimelineMessage)
            : [];
          const timelineItems = sortMessages([
            ...incoming,
            ...incomingNotes,
            ...incomingAuditEvents,
          ]);
          mergeMessages(conversationId, timelineItems);

          const updatedMessages = Array.isArray(response.data?.updated_messages)
            ? response.data.updated_messages.map(normalizeChatMessage).filter(Boolean)
            : [];
          updatedMessages.forEach((message: ChatMessage) => patchMessage(conversationId, message));

          const updatedConversation = response.data?.conversation;
          if (updatedConversation?.id) {
            const fallbackConversation = conversationsRef.current.find(
              (conversation) => conversation.id === Number(updatedConversation.id)
            );
            const normalizedUpdated = normalizeConversation({
              ...fallbackConversation,
              ...updatedConversation,
              contact: fallbackConversation?.contact,
              connection: fallbackConversation?.connection,
            });
            const patch = normalizedUpdated ?? updatedConversation;
            setConversations((current) =>
              current.map((conversation) => {
                if (conversation.id !== Number(updatedConversation.id)) {
                  return conversation;
                }

                return {
                  ...conversation,
                  ...patch,
                  last_inbound_message_at: mergeLastInboundAt(
                    conversation.last_inbound_message_at,
                    patch.last_inbound_message_at
                  ),
                };
              })
            );
          }
        })
        .catch((error) => {
          if (inboxDebug) {
            console.warn('[Inbox] Failed to load conversation messages:', error?.message);
          }
        })
        .finally(() => {
          if (reset && conversationFetchSequenceRef.current[conversationId] === sequence) {
            setLoadingConversationId((current) => (current === conversationId ? null : current));
          }
        });
    },
    [conversationsRef, mergeMessages, patchMessage]
  );

  useEffect(() => {
    if (!activeConversation?.id) return;
    setActionsMenuOpen(false);

    // Already rendered from the page props: the regular poll / realtime keeps it fresh.
    if (seededConversationIdRef.current === activeConversation.id) {
      seededConversationIdRef.current = null;
      return;
    }
    seededConversationIdRef.current = null;

    // Opened before: show what we have and fetch only what's new, without a spinner.
    if ((messagesByConversationRef.current[activeConversation.id] ?? []).length > 0) {
      fetchConversationMessages(activeConversation.id);
      return;
    }

    fetchConversationMessages(activeConversation.id, true);
  }, [activeConversation?.id, fetchConversationMessages]);

  useEffect(() => {
    if (!activeConversation?.id) return;

    const interval = setInterval(
      () => {
        if (document.hidden || loadingConversationId === activeConversation.id) return;
        fetchConversationMessages(activeConversation.id);
      },
      connected ? 10000 : 3000
    );

    return () => clearInterval(interval);
  }, [activeConversation?.id, fetchConversationMessages, loadingConversationId, connected]);

  useEffect(() => {
    if (!account?.id || !activeConversation?.id) return;

    const channel = `account.${account.id}.whatsapp.conversation.${activeConversation.id}`;
    const unsubscribeMessageCreated = subscribe(
      channel,
      '.whatsapp.message.created',
      (data: any) => {
        const message = normalizeChatMessage(data.message);
        if (!message) return;
        mergeMessages(activeConversation.id, [message]);
      }
    );
    const unsubscribeMessageUpdated = subscribe(
      channel,
      '.whatsapp.message.updated',
      (data: any) => {
        const message = normalizeChatMessage(data.message);
        if (!message) return;
        patchMessage(activeConversation.id, message);
      }
    );
    const unsubscribeNoteAdded = subscribe(channel, '.whatsapp.note.added', (data: any) => {
      if (!data?.note) return;
      mergeMessages(activeConversation.id, [noteToTimelineMessage(data.note)]);
    });
    const unsubscribeAuditAdded = subscribe(channel, '.whatsapp.audit.added', (data: any) => {
      if (!data?.audit_event) return;
      mergeMessages(activeConversation.id, [auditToTimelineMessage(data.audit_event)]);
    });
    const unsubscribeConversationUpdated = subscribe(
      channel,
      '.whatsapp.conversation.updated',
      (data: any) => {
        const incoming = normalizeConversation(data.conversation);
        if (!incoming) return;
        setConversations((prev) => applyConversationUpdated(prev, incoming));
      }
    );

    return () => {
      unsubscribeMessageCreated();
      unsubscribeMessageUpdated();
      unsubscribeNoteAdded();
      unsubscribeAuditAdded();
      unsubscribeConversationUpdated();
    };
  }, [account?.id, activeConversation?.id, subscribe, mergeMessages, patchMessage]);

  useEffect(() => {
    if (!activeConversation?.id) return;
    const hasUnreadInbound = activeTimelineMessages.some(
      (message) => message.direction === 'inbound' && !message.read_at
    );
    if (!hasUnreadInbound) return;

    const timeout = window.setTimeout(() => {
      axios
        .post(route('app.whatsapp.conversations.read', { conversation: activeConversation.id }))
        .catch((error) => {
          if (inboxDebug) {
            console.warn('[Inbox] Failed to mark conversation read:', error?.message);
          }
        });
    }, 700);

    return () => window.clearTimeout(timeout);
  }, [activeConversation?.id, activeTimelineMessages]);

  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ block: 'end' });
  }, [activeConversation?.id, activeMessages.length]);

  const showReplyWindowClosedToast = () => {
    addToast({
      title:
        activeReplyWindow.state === 'closed' ? '24-hour reply window closed' : 'Template required',
      description:
        'More than 24 hours have passed since the customer last replied. Send an approved template to restart the chat.',
      variant: 'warning',
      duration: 6000,
    });
    setShowTemplates(true);
    setShowQuickReplies(false);
    setShowInteractiveComposer(false);
  };

  const sendInlineMessage = () => {
    if (!activeConversation || sendingMessage) return;

    if (!noteMode && activeBotIsReplying) {
      addToast({
        title: 'Bot is replying',
        description: 'Switch this chat to Manual if you want to take over now.',
        variant: 'warning',
      });
      return;
    }

    if (!noteMode && !activeReplyWindow.isOpen) {
      showReplyWindowClosedToast();
      return;
    }

    if (!noteMode && attachments.length > 0) {
      sendAttachments(messageDraft.trim() || undefined);
      return;
    }

    if (!messageDraft.trim()) return;

    const body = messageDraft.trim();
    if (noteMode) {
      setMessageDraft('');
      setSendingMessage(true);
      axios
        .post(
          route('app.whatsapp.conversations.notes.store', { conversation: activeConversation.id }),
          { note: body }
        )
        .then((response) => {
          const note = response.data?.note;
          mergeMessages(activeConversation.id, [
            noteToTimelineMessage({
              id: Number(note?.id ?? Date.now()),
              note: note?.note ?? body,
              created_at: note?.created_at ?? new Date().toISOString(),
              created_by: note?.created_by ?? null,
            }),
          ]);
          addToast({ title: 'Note added', variant: 'success' });
          fetchConversationMessages(activeConversation.id);
        })
        .catch((error) => {
          setMessageDraft(body);
          addToast({
            title: 'Note not saved',
            description: error?.response?.data?.message || 'Please try again.',
            variant: 'error',
          });
        })
        .finally(() => setSendingMessage(false));
      return;
    }

    const replyPreview = messageReplyPreview(replyTo);
    const payload = {
      message: body,
      ...(replyTo?.id && typeof replyTo.id === 'number' ? { reply_message_id: replyTo.id } : {}),
    };
    const optimistic: ChatMessage = {
      id: `optimistic-${Date.now()}`,
      direction: 'outbound',
      type: 'text',
      text_body: body,
      status: 'queued',
      created_at: new Date().toISOString(),
      optimistic: true,
      reply_to: replyPreview,
      reply_to_message_id: replyTo?.id ?? null,
      reply_to_meta_message_id: messageReplyMetaId(replyTo),
    };

    setMessageDraft('');
    setReplyTo(null);
    setSendingMessage(true);
    mergeMessages(activeConversation.id, [optimistic]);
    setConversations((prev) =>
      prev.map((conversation) =>
        conversation.id === activeConversation.id
          ? { ...conversation, last_message_preview: body, last_message_at: optimistic.created_at }
          : conversation
      )
    );

    axios
      .post(
        route('app.whatsapp.conversations.send', { conversation: activeConversation.id }),
        payload,
        {
          headers: { Accept: 'application/json' },
        }
      )
      .then((response) => {
        const sentMessage = normalizeChatMessage(response.data?.data?.message);
        if (sentMessage) {
          mergeMessages(activeConversation.id, [sentMessage]);
        }
        fetchConversationMessages(activeConversation.id);
      })
      .catch((error) => {
        const failedMessage = normalizeChatMessage(error?.response?.data?.data?.message);
        if (failedMessage) {
          patchMessage(activeConversation.id, failedMessage);
        }
        addToast({
          title: 'Message not sent',
          description:
            error?.response?.data?.message_detail ||
            error?.response?.data?.error ||
            error?.response?.data?.message ||
            'Please try again.',
          variant: 'error',
        });
        fetchConversationMessages(activeConversation.id);
      })
      .finally(() => {
        setSendingMessage(false);
      });
  };

  const createNewChat = () => {
    if (creatingChat) return;
    setCreatingChat(true);
    router.post(route('app.whatsapp.conversations.store'), newChatForm, {
      preserveScroll: true,
      onSuccess: () => {
        setShowNewChat(false);
        setNewChatForm({
          name: '',
          wa_id: '',
          connection_id: connections?.[0]?.id ? String(connections[0].id) : '',
        });
        addToast({ title: 'Chat created', variant: 'success' });
      },
      onError: (errors) => {
        addToast({
          title: 'Chat not created',
          description: String(
            errors.wa_id ||
              errors.connection_id ||
              errors.name ||
              'Check the contact details and try again.'
          ),
          variant: 'error',
        });
      },
      onFinish: () => setCreatingChat(false),
    });
  };

  const deleteConversation = async () => {
    if (!activeConversation || deletingConversationId) return;
    const confirmed = await confirm({
      title: 'Delete chat',
      message: `Delete chat with ${activeConversation.contact.name || activeConversation.contact.wa_id}? This removes the conversation history from this workspace.`,
      confirmText: 'Delete chat',
      variant: 'danger',
    });
    if (!confirmed) return;

    const conversationId = activeConversation.id;
    setDeletingConversationId(conversationId);
    router.delete(route('app.whatsapp.conversations.destroy', { conversation: conversationId }), {
      preserveScroll: true,
      onSuccess: () => {
        setConversations((current) =>
          current.filter((conversation) => conversation.id !== conversationId)
        );
        setMessagesByConversation((current) => {
          const next = { ...current };
          delete next[conversationId];
          return next;
        });
        setActiveId(null);
        setActionsMenuOpen(false);
        addToast({ title: 'Chat deleted', variant: 'success' });
      },
      onError: () =>
        addToast({ title: 'Chat not deleted', description: 'Please try again.', variant: 'error' }),
      onFinish: () => setDeletingConversationId(null),
    });
  };

  const insertQuickReply = (text: string) => {
    const firstName = activeConversation?.contact.name?.split(' ')[0] || 'there';
    setMessageDraft(text.replace('{{name}}', firstName));
    closeComposerPopovers();
  };

  const applyTemplate = (template: TemplateOption) => {
    if (!activeConversation) return;
    if ((template.variable_count ?? 0) > 0) {
      setMessageDraft(
        (template.body_text || template.name).replace(
          /\{\{\d+\}\}/g,
          activeConversation.contact.name?.split(' ')[0] || 'there'
        )
      );
      setShowTemplates(false);
      addToast({
        title: 'Template added to composer',
        description: 'Review variables before sending.',
        variant: 'info',
      });
      return;
    }

    setSendingMessage(true);
    router.post(
      route('app.whatsapp.conversations.send-template', { conversation: activeConversation.id }),
      {
        template_id: template.id,
        variables: [],
      },
      {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
          setShowTemplates(false);
          fetchConversationMessages(activeConversation.id, true);
        },
        onError: (errors) =>
          addToast({
            title: 'Template not sent',
            description: (errors as any)?.template || 'Please try again.',
            variant: 'error',
          }),
        onFinish: () => setSendingMessage(false),
      }
    );
  };

  const saveInteractiveButton = async () => {
    const label = newInteractiveButtonLabel.trim();
    if (!label || savingInteractiveButton) return;

    setSavingInteractiveButton(true);
    try {
      const response = await axios.post(
        route('app.quick-replies.store'),
        {
          type: 'button',
          label,
          shortcut: shortcutFromButtonLabel(label),
          message: label,
          is_active: true,
        },
        { headers: { Accept: 'application/json' } }
      );

      const saved = response.data?.data;
      if (saved?.id) {
        setSavedButtonOptions((current) => {
          const withoutDuplicate = current.filter(
            (button) => Number(button.id) !== Number(saved.id)
          );
          return [saved, ...withoutDuplicate];
        });
        setInteractiveDraft((current) => ({
          ...current,
          saved_button_ids: [
            ...current.saved_button_ids.filter((id) => Number(id) !== Number(saved.id)),
            Number(saved.id),
          ].slice(-3),
        }));
        setNewInteractiveButtonLabel('');
        addToast({
          title: 'Button saved',
          description: `"${label}" is now available for interactive messages.`,
          variant: 'success',
        });
      }
    } catch (error: any) {
      addToast({
        title: 'Button not saved',
        description:
          error?.response?.data?.message ||
          Object.values(error?.response?.data?.errors || {})?.flat()?.[0] ||
          'Check the label and try again.',
        variant: 'error',
      });
    } finally {
      setSavingInteractiveButton(false);
    }
  };

  const deleteSavedInteractiveButton = async (button: SavedButtonOption) => {
    const confirmed = await confirm({
      title: 'Delete saved button',
      message: `Delete "${button.button_text || button.label}" from saved buttons? This also removes it from Quick Replies.`,
      confirmText: 'Delete button',
      variant: 'danger',
    });

    if (!confirmed) return;

    try {
      await axios.delete(route('app.quick-replies.destroy', { quickReply: button.id }), {
        headers: { Accept: 'application/json' },
      });
      setSavedButtonOptions((current) =>
        current.filter((item) => Number(item.id) !== Number(button.id))
      );
      setInteractiveDraft((current) => ({
        ...current,
        saved_button_ids: current.saved_button_ids.filter((id) => Number(id) !== Number(button.id)),
      }));
      addToast({
        title: 'Button deleted',
        description: `"${button.button_text || button.label}" was removed.`,
        variant: 'success',
      });
    } catch (error: any) {
      addToast({
        title: 'Button not deleted',
        description: error?.response?.data?.message || 'Open Quick Replies to manage this button.',
        variant: 'error',
      });
    }
  };

  const sendInteractiveMessage = async () => {
    if (!activeConversation || sendingMessage) return;
    if (!activeReplyWindow.isOpen) {
      showReplyWindowClosedToast();
      return;
    }

    setSendingMessage(true);
    const body = interactiveDraft.body_text.trim();

    try {
      let endpoint = '';
      let payload: any = {};

      if (interactiveMode === 'buttons') {
        const selectedSavedButtons = savedButtonOptions
          .filter((button) => interactiveDraft.saved_button_ids.includes(Number(button.id)))
          .map((button) => button.button_text || button.label)
          .filter(Boolean);
        const buttons = selectedSavedButtons
          .map((text, index) => ({
            id: `btn_${index + 1}_${text
              .toLowerCase()
              .replace(/[^a-z0-9]+/g, '_')
              .slice(0, 40)}`,
            text: text.trim(),
          }))
          .filter((button) => button.text);
        endpoint = route('app.whatsapp.conversations.send-buttons', {
          conversation: activeConversation.id,
        });
        payload = {
          body_text: body,
          header_text: interactiveDraft.header_text.trim() || undefined,
          footer_text: interactiveDraft.footer_text.trim() || undefined,
          buttons,
        };
      } else if (interactiveMode === 'list') {
        endpoint = route('app.whatsapp.conversations.send-list', {
          conversation: activeConversation.id,
        });
        payload = { list_id: Number(interactiveDraft.list_id) };
      } else if (interactiveMode === 'form') {
        const form = savedForms.find(
          (item) => String(item.id) === String(interactiveDraft.form_id)
        );
        endpoint = route('app.whatsapp.conversations.send-flow', {
          conversation: activeConversation.id,
        });
        payload = {
          flow_id: form?.meta_flow_id || '',
          body_text: body || `Please complete this WhatsApp form: ${form?.name || 'Form'}`,
          header_text: interactiveDraft.header_text.trim() || undefined,
          footer_text: interactiveDraft.footer_text.trim() || undefined,
          cta: interactiveDraft.form_button_text.trim() || 'Open form',
          flow_action: 'navigate',
        };
      } else if (interactiveMode === 'product') {
        endpoint = route('app.whatsapp.conversations.send-product', {
          conversation: activeConversation.id,
        });
        payload = {
          mode: 'single',
          product_id: Number(interactiveDraft.product_id),
          body_text: body || 'Here is the product.',
          footer_text: interactiveDraft.footer_text.trim() || undefined,
        };
      } else if (interactiveMode === 'link') {
        endpoint = route('app.whatsapp.conversations.send-cta-url', {
          conversation: activeConversation.id,
        });
        payload = {
          body_text: body,
          header_text: interactiveDraft.header_text.trim() || undefined,
          footer_text: interactiveDraft.footer_text.trim() || undefined,
          display_text: interactiveDraft.cta_display_text.trim() || 'Open link',
          url: interactiveDraft.cta_url.trim(),
        };
      } else {
        endpoint = route('app.whatsapp.conversations.send-contact-card', {
          conversation: activeConversation.id,
        });
        payload = {
          formatted_name: interactiveDraft.contact_name.trim(),
          phone: interactiveDraft.contact_phone.trim(),
          email: interactiveDraft.contact_email.trim() || undefined,
        };
      }

      const response = await axios.post(endpoint, payload, {
        headers: { Accept: 'application/json' },
      });
      const sentMessage = response.data?.data?.message;
      if (sentMessage) {
        mergeMessages(activeConversation.id, [sentMessage]);
      }
      setShowInteractiveComposer(false);
      addToast({
        title:
          interactiveMode === 'form'
            ? 'Form sent'
            : interactiveMode === 'product'
              ? 'Product sent'
              : interactiveMode === 'payment'
                ? 'Payment link sent'
                : interactiveMode === 'link'
                  ? 'Link button sent'
                  : interactiveMode === 'contact'
                    ? 'Contact card sent'
                    : 'Interactive message sent',
        variant: 'success',
      });
      fetchConversationMessages(activeConversation.id, true);
    } catch (error: any) {
      addToast({
        title: 'Interactive message not sent',
        description:
          error?.response?.data?.error ||
          error?.response?.data?.message ||
          'Check the fields and try again.',
        variant: 'error',
      });
    } finally {
      setSendingMessage(false);
    }
  };

  const openFilePicker = (accept?: string) => {
    setFilePickerAccept(accept);
    setShowAttachments(false);
    window.setTimeout(() => {
      if (fileInputRef.current) {
        fileInputRef.current.accept = accept ?? '';
        fileInputRef.current.click();
      }
    }, 0);
  };

  const addPickedFiles = (files: FileList | null) => {
    if (!files?.length) {
      addToast({
        title: 'No file selected',
        description: 'Choose an image, video, audio, or document to attach.',
        variant: 'info',
      });
      return;
    }
    const picked = Array.from(files);
    const usableFiles = picked.filter((file) => file.size > 0 && (file.name || file.type));

    if (usableFiles.length === 0) {
      addToast({
        title: 'File not attached',
        description: 'The selected file appears to be empty or unavailable.',
        variant: 'warning',
      });
      if (fileInputRef.current) {
        fileInputRef.current.value = '';
      }
      return;
    }

    setNoteMode(false);
    setShowEmoji(false);
    setShowTemplates(false);
    setShowQuickReplies(false);
    setShowInteractiveComposer(false);
    setShowLocationPicker(false);
    setShowAttachments(false);
    setAttachments((current) => [...current, ...usableFiles]);
    addToast({
      title: usableFiles.length > 1 ? 'Files attached' : 'File attached',
      description:
        usableFiles.length === 1
          ? usableFiles[0].name || 'Ready to send.'
          : `${usableFiles.length} files ready to send.`,
      variant: 'success',
      duration: 1600,
    });
    if (fileInputRef.current) {
      fileInputRef.current.value = '';
    }
  };

  useEffect(() => {
    if (attachments.length > 0) {
      attachmentPreviewRef.current?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }
  }, [attachments.length]);

  const removeAttachment = (index: number) => {
    setAttachments((current) => current.filter((_, currentIndex) => currentIndex !== index));
  };

  const sendAttachments = async (caption?: string) => {
    if (!activeConversation || attachments.length === 0 || sendingMessage) return;
    if (!activeReplyWindow.isOpen) {
      showReplyWindowClosedToast();
      return;
    }

    setSendingMessage(true);

    for (let index = 0; index < attachments.length; index += 1) {
      const file = attachments[index];
      const fileType = resolveMediaType(file);

      const formData = new FormData();
      formData.append('type', fileType);
      formData.append('attachment', file);
      if (fileType === 'audio' && isRecordedVoiceFile(file)) {
        formData.append('is_voice', '1');
      }
      if (replyTo?.id && typeof replyTo.id === 'number') {
        formData.append('reply_message_id', String(replyTo.id));
      }
      if (caption && index === 0) {
        formData.append('caption', caption);
      }

      try {
        const response = await axios.post(
          route('app.whatsapp.conversations.send-media', { conversation: activeConversation.id }),
          formData,
          { headers: { 'Content-Type': 'multipart/form-data', Accept: 'application/json' } }
        );
        const sentMessage = response.data?.data?.message;
        if (sentMessage) {
          mergeMessages(activeConversation.id, [sentMessage]);
        }
      } catch (error: any) {
        addToast({
          title: 'Attachment not sent',
          description:
            error?.response?.data?.message ||
            error?.response?.data?.errors?.attachment?.[0] ||
            'Please try another file.',
          variant: 'error',
        });
        setSendingMessage(false);
        return;
      }
    }

    addToast({
      title: attachments.length > 1 ? 'Attachments sent' : 'Attachment sent',
      variant: 'success',
    });
    setAttachments([]);
    setMessageDraft('');
    setReplyTo(null);
    setSendingMessage(false);
    fetchConversationMessages(activeConversation.id, true);
  };

  const selectLocation = (latitude: number, longitude: number, label?: string) => {
    const lat = Number(latitude.toFixed(6));
    const lng = Number(longitude.toFixed(6));

    setLocationInput((current) => ({
      label: label ?? current.label,
      latitude: String(lat),
      longitude: String(lng),
    }));
    setLocationMap((current) => ({
      ...current,
      lat,
      lng,
      zoom: Math.max(current.zoom, 14),
    }));
  };

  const searchLocations = async () => {
    const query = locationSearch.trim();
    if (!query) {
      setLocationResults([]);
      return;
    }

    setLocationSearching(true);
    try {
      const response = await axios.get('https://nominatim.openstreetmap.org/search', {
        params: {
          q: query,
          format: 'jsonv2',
          limit: 5,
        },
      });
      const results = Array.isArray(response.data)
        ? (response.data
            .map((item: any): LocationSearchResult | null => {
              const lat = Number(item.lat);
              const lng = Number(item.lon);
              if (!Number.isFinite(lat) || !Number.isFinite(lng)) return null;

              return {
                label: String(item.display_name || query),
                lat,
                lng,
              };
            })
            .filter(Boolean) as LocationSearchResult[])
        : [];
      setLocationResults(results);
      if (results.length === 0) {
        addToast({
          title: 'No location found',
          description: 'Try a more specific place, address, or landmark.',
          variant: 'info',
        });
      }
    } catch {
      addToast({
        title: 'Location search failed',
        description: 'Use current location or enter coordinates manually.',
        variant: 'warning',
      });
    } finally {
      setLocationSearching(false);
    }
  };

  const handleLocationMapClick = (event: MouseEvent<HTMLDivElement>) => {
    const rect = event.currentTarget.getBoundingClientRect();
    const centerPixel = locationToWorldPixel(locationMap.lat, locationMap.lng, locationMap.zoom);
    const worldX = centerPixel.x + (event.clientX - rect.left - rect.width / 2);
    const worldY = centerPixel.y + (event.clientY - rect.top - rect.height / 2);
    const location = worldPixelToLocation(worldX, worldY, locationMap.zoom);

    selectLocation(location.lat, location.lng);
  };

  const useCurrentLocationForPicker = () => {
    if (!navigator.geolocation) {
      addToast({
        title: 'Location unavailable',
        description: 'This browser does not support location sharing.',
        variant: 'warning',
      });
      return;
    }

    navigator.geolocation.getCurrentPosition(
      (position) => {
        selectLocation(
          position.coords.latitude,
          position.coords.longitude,
          locationInput.label || 'Current location'
        );
      },
      () => {
        addToast({
          title: 'Location blocked',
          description: 'Allow location access to use your current location.',
          variant: 'warning',
        });
      },
      { enableHighAccuracy: true, timeout: 10000 }
    );
  };

  const sendLocation = (latitude: number, longitude: number, label = 'Shared location') => {
    if (!activeConversation) return;
    if (!activeReplyWindow.isOpen) {
      showReplyWindowClosedToast();
      return;
    }
    setSendingMessage(true);
    axios
      .post(
        route('app.whatsapp.conversations.send-location', { conversation: activeConversation.id }),
        {
          latitude,
          longitude,
          name: label,
          address: null,
        }
      )
      .then(() => {
        addToast({ title: 'Location sent', variant: 'success' });
        setShowLocationPicker(false);
        setLocationInput({ label: '', latitude: '', longitude: '' });
        setLocationResults([]);
        setLocationSearch('');
        fetchConversationMessages(activeConversation.id, true);
      })
      .catch((error) => {
        addToast({
          title: 'Location not sent',
          description: error?.response?.data?.message || 'Check the coordinates and try again.',
          variant: 'error',
        });
      })
      .finally(() => setSendingMessage(false));
  };

  const sendManualLocation = () => {
    const latitude = Number(locationInput.latitude);
    const longitude = Number(locationInput.longitude);
    if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
      addToast({
        title: 'Coordinates needed',
        description: 'Enter valid latitude and longitude.',
        variant: 'warning',
      });
      return;
    }
    if (latitude < -90 || latitude > 90 || longitude < -180 || longitude > 180) {
      addToast({
        title: 'Coordinates invalid',
        description: 'Latitude must be between -90 and 90, longitude between -180 and 180.',
        variant: 'warning',
      });
      return;
    }
    sendLocation(latitude, longitude, locationInput.label || 'Shared location');
  };

  const updateConversationMeta = (
    updates: Partial<{
      status: string;
      assigned_to: number | null;
      priority: string | null;
      tag: string;
    }>
  ) => {
    if (!activeConversation) return;
    const previous = activeConversation;
    const conversationUpdates = { ...updates };
    delete conversationUpdates.tag;
    setConversations((current) =>
      current.map((conversation) =>
        conversation.id === activeConversation.id
          ? { ...conversation, ...conversationUpdates }
          : conversation
      )
    );
    axios
      .post(
        route('app.whatsapp.conversations.update', { conversation: activeConversation.id }),
        updates,
        {
          headers: { Accept: 'application/json' },
        }
      )
      .then((response) => {
        const updated = response.data?.conversation;
        if (!updated) return;
        setConversations((current) =>
          current.map((conversation) =>
            conversation.id === activeConversation.id
              ? {
                  ...conversation,
                  status: Object.prototype.hasOwnProperty.call(updated, 'status')
                    ? updated.status
                    : conversation.status,
                  assigned_to: Object.prototype.hasOwnProperty.call(updated, 'assigned_to')
                    ? updated.assigned_to
                    : conversation.assigned_to,
                  priority: Object.prototype.hasOwnProperty.call(updated, 'priority')
                    ? updated.priority
                    : conversation.priority,
                  contact: {
                    ...conversation.contact,
                    ...(updated.contact ?? {}),
                    tags: updated.contact?.tags ?? conversation.contact.tags ?? [],
                  },
                }
              : conversation
          )
        );
      })
      .catch(() => {
        setConversations((current) =>
          current.map((conversation) => (conversation.id === previous.id ? previous : conversation))
        );
        addToast({
          title: 'Conversation update failed',
          description: 'Please try again.',
          variant: 'error',
        });
      });
  };

  const addConversationLabel = (labelId: string) => {
    const label = INBOX_LABELS.find((item) => item.id === labelId);
    if (!label) return;
    if (labelId === 'vip') {
      updateConversationMeta({ priority: 'urgent', tag: label.label });
    } else {
      updateConversationMeta({ tag: label.label });
    }
    addToast({ title: `${label.label} added`, variant: 'success', duration: 1200 });
  };

  const stopAutomation = async () => {
    if (!activeConversation?.automation_state) return;

    const confirmed = await confirm({
      title: 'Stop automation?',
      message:
        'This clears the current automation journey for this chat. Future messages can still trigger a new flow.',
      confirmText: 'Stop automation',
      cancelText: 'Keep running',
      variant: 'danger',
    });
    if (!confirmed) return;

    axios
      .post(
        route('app.whatsapp.conversations.automation.stop', { conversation: activeConversation.id })
      )
      .then((response) => {
        const updated = response.data?.conversation;
        setConversations((current) =>
          current.map((conversation) =>
            conversation.id === activeConversation.id
              ? { ...conversation, automation_state: updated?.automation_state ?? null }
              : conversation
          )
        );
        fetchConversationMessages(activeConversation.id);
        addToast({ title: 'Automation stopped', variant: 'success' });
      })
      .catch(() => {
        addToast({
          title: 'Could not stop automation',
          description: 'Please try again.',
          variant: 'error',
        });
      });
  };

  const toggleBotPaused = async (paused: boolean) => {
    if (!activeConversation) return;

    axios
      .post(
        route('app.whatsapp.conversations.bot.toggle', { conversation: activeConversation.id }),
        {
          paused,
          reason: paused ? 'Paused by agent from inbox' : null,
          assign_to_me: paused,
        }
      )
      .then((response) => {
        const updated = response.data?.conversation ?? {};
        setConversations((current) =>
          current.map((conversation) =>
            conversation.id === activeConversation.id
              ? {
                  ...conversation,
                  assigned_to: Object.prototype.hasOwnProperty.call(updated, 'assigned_to')
                    ? updated.assigned_to
                    : conversation.assigned_to,
                  automation_state: updated.automation_state ?? null,
                  automation_processing: Boolean(updated.automation_processing ?? false),
                  automation_processing_mode: updated.automation_processing_mode ?? null,
                  bot_paused: Boolean(updated.bot_paused ?? false),
                  bot_paused_reason: updated.bot_paused_reason ?? null,
                  handoff_status: updated.handoff_status ?? null,
                  handoff_reason: updated.handoff_reason ?? null,
                }
              : conversation
          )
        );
        fetchConversationMessages(activeConversation.id);
        addToast({ title: paused ? 'Bot paused' : 'Bot resumed', variant: 'success' });
      })
      .catch(() => {
        addToast({
          title: 'Bot setting failed',
          description: 'Please try again.',
          variant: 'error',
        });
      });
  };

  const toggleSelectedConversation = (conversationId: number) => {
    setSelectedIds((current) => {
      const next = new Set(current);
      if (next.has(conversationId)) {
        next.delete(conversationId);
      } else {
        next.add(conversationId);
      }
      return next;
    });
  };

  const setReaction = (message: ChatMessage, reaction: string) => {
    if (!activeConversation) return;
    if (!canReactToMessage(message)) {
      if (!message.meta_message_id && !message.optimistic) {
        fetchConversationMessages(activeConversation.id, true);
      }
      addToast({
        title: 'Reaction not sent',
        description: message.optimistic
          ? 'Wait until this outgoing message is sent.'
          : 'Reactions work only on messages that exist in WhatsApp. Notes, generated drafts, and unsynced system messages cannot receive reactions.',
        variant: 'warning',
      });
      return;
    }

    setMessageReactions((current) => ({ ...current, [String(message.id)]: reaction }));
    axios
      .post(
        route('app.whatsapp.conversations.send-reaction', { conversation: activeConversation.id }),
        {
          message_id: message.id,
          emoji: reaction,
        }
      )
      .then((response) => {
        const sentMessage = response.data?.data?.message;
        if (sentMessage) {
          mergeMessages(activeConversation.id, [sentMessage]);
        }
        addToast({
          title: 'Reaction sent',
          description: reaction,
          variant: 'success',
          duration: 1200,
        });
      })
      .catch((error) => {
        setMessageReactions((current) => {
          const next = { ...current };
          delete next[String(message.id)];
          return next;
        });
        addToast({
          title: 'Reaction not sent',
          description: error?.response?.data?.message || 'Please try again.',
          variant: 'error',
        });
      });
  };

  const retryMessage = (message: ChatMessage) => {
    if (!activeConversation || message.status !== 'failed' || sendingMessage) return;

    setSendingMessage(true);
    axios
      .post(
        route('app.whatsapp.conversations.retry-message', {
          conversation: activeConversation.id,
          message: message.id,
        })
      )
      .then((response) => {
        const sentMessage = response.data?.data?.message;
        if (sentMessage) {
          patchMessage(activeConversation.id, sentMessage);
        }
        fetchConversationMessages(activeConversation.id);
        addToast({ title: 'Message resent', variant: 'success' });
      })
      .catch((error) => {
        const retryError =
          error?.response?.data?.error || error?.response?.data?.message || 'Please try again.';
        addToast({
          title: 'Retry failed',
          description: retryError,
          variant: 'error',
        });
      })
      .finally(() => setSendingMessage(false));
  };

  const contactRouteKey = activeConversation?.contact.slug || activeConversation?.contact.id;
  const contactHref = contactRouteKey
    ? route('app.contacts.index', { contact: contactRouteKey })
    : null;

  const openContactEditor = () => {
    if (!activeConversation?.contact?.id) return;

    setContactForm({
      name: activeConversation.contact.name || '',
      email: activeConversation.contact.email || '',
      phone: activeConversation.contact.phone || activeConversation.contact.wa_id || '',
      company: activeConversation.contact.company || '',
      status: activeConversation.contact.status || 'active',
      notes: activeConversation.contact.notes || '',
    });
    setEditingContact(true);
  };

  const saveContactDetails = () => {
    if (!activeConversation?.contact?.id || savingContact) return;

    const contactId = activeConversation.contact.id;
    const contactRouteKey = activeConversation.contact.slug || contactId;
    const updates = {
      name: contactForm.name.trim() || activeConversation.contact.wa_id,
      email: contactForm.email.trim() || null,
      phone: contactForm.phone.trim() || null,
      company: contactForm.company.trim() || null,
      status: contactForm.status || 'active',
      notes: contactForm.notes.trim() || null,
    };

    setSavingContact(true);
    router.put(route('app.contacts.update', { contact: contactRouteKey }), updates, {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => {
        setConversations((current) =>
          current.map((conversation) =>
            conversation.contact.id === contactId
              ? { ...conversation, contact: { ...conversation.contact, ...updates } }
              : conversation
          )
        );
        setEditingContact(false);
        addToast({
          title: 'Contact updated',
          description: 'Details are saved in the inbox and contact record.',
          variant: 'success',
        });
      },
      onError: (errors) => {
        const firstError = Object.values(errors || {})[0];
        addToast({
          title: 'Contact update failed',
          description:
            (typeof firstError === 'string' ? firstError : null) ||
            'Please check the fields and try again.',
          variant: 'error',
        });
      },
      onFinish: () => setSavingContact(false),
    });
  };

  const toggleVoiceRecording = async () => {
    if (recordingVoice) {
      const context = voiceAudioContextRef.current;
      const sampleRate = context?.sampleRate || 44100;
      const samples = flattenAudioSamples(voiceSamplesRef.current);

      voiceProcessorRef.current?.disconnect();
      voiceSourceRef.current?.disconnect();
      voiceStreamRef.current?.getTracks().forEach((track) => track.stop());
      context?.close().catch(() => undefined);

      voiceProcessorRef.current = null;
      voiceSourceRef.current = null;
      voiceStreamRef.current = null;
      voiceAudioContextRef.current = null;
      voiceSamplesRef.current = [];
      setRecordingVoice(false);

      if (samples.length < sampleRate / 2) {
        addToast({
          title: 'Voice message too short',
          description: 'Record at least half a second.',
          variant: 'warning',
        });
        return;
      }

      try {
        const lame = await loadLameEncoder();
        const blob = encodeMp3(lame, samples, sampleRate);
        if (blob.size < 256) {
          throw new Error('Encoded voice message is empty.');
        }
        const file = new File([blob], `voice-message-${Date.now()}.mp3`, { type: 'audio/mpeg' });
        setAttachments((current) => [...current, file]);
        addToast({
          title: 'Voice message ready',
          description: 'Press Send to deliver it.',
          variant: 'success',
        });
      } catch (error: any) {
        addToast({
          title: 'Voice message not created',
          description: error?.message || 'Please upload an MP3 audio file instead.',
          variant: 'error',
        });
      }
      return;
    }

    const AudioContextClass = window.AudioContext || (window as any).webkitAudioContext;
    if (!navigator.mediaDevices?.getUserMedia || !AudioContextClass) {
      addToast({
        title: 'Voice recording unavailable',
        description: 'This browser does not support audio recording.',
        variant: 'warning',
      });
      return;
    }

    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      const context = new AudioContextClass();
      const source = context.createMediaStreamSource(stream);
      const processor = context.createScriptProcessor(4096, 1, 1);
      voiceSamplesRef.current = [];

      processor.onaudioprocess = (event) => {
        voiceSamplesRef.current.push(new Float32Array(event.inputBuffer.getChannelData(0)));
      };

      source.connect(processor);
      processor.connect(context.destination);

      voiceStreamRef.current = stream;
      voiceAudioContextRef.current = context;
      voiceSourceRef.current = source;
      voiceProcessorRef.current = processor;
      setRecordingVoice(true);
    } catch (error) {
      addToast({
        title: 'Microphone blocked',
        description: 'Allow microphone access to record a voice message.',
        variant: 'warning',
      });
    }
  };

  // Helper functions for realtime updates
  const applyConversationUpdated = (prev: Conversation[], updated: Conversation) => {
    const index = prev.findIndex((c) => c.id === updated.id);
    if (index >= 0) {
      const newList = [...prev];
      newList[index] = {
        ...prev[index],
        ...updated,
        last_inbound_message_at: mergeLastInboundAt(
          prev[index].last_inbound_message_at,
          updated.last_inbound_message_at
        ),
      };
      return newList.sort((a, b) => {
        const timeA = a.last_message_at ? new Date(a.last_message_at).getTime() : 0;
        const timeB = b.last_message_at ? new Date(b.last_message_at).getTime() : 0;
        return timeB - timeA;
      });
    } else {
      return [updated, ...prev].sort((a, b) => {
        const timeA = a.last_message_at ? new Date(a.last_message_at).getTime() : 0;
        const timeB = b.last_message_at ? new Date(b.last_message_at).getTime() : 0;
        return timeB - timeA;
      });
    }
  };

  const applyMessageCreated = (prev: Conversation[], data: any) => {
    const convId = data.conversation_id;
    const index = prev.findIndex((c) => c.id === convId);
    const incomingConversation = normalizeConversation(data.conversation);

    if (index >= 0) {
      const previousLastInboundAt = prev[index].last_inbound_message_at ?? null;
      const updated = {
        ...prev[index],
        ...(incomingConversation ?? {}),
      };
      updated.last_message_preview =
        incomingConversation?.last_message_preview ??
        data.message?.text_body ??
        data.message?.text ??
        data.message?.body ??
        'New message';
      updated.last_message_at =
        incomingConversation?.last_message_at ??
        data.message?.created_at ??
        data.message?.timestamp ??
        new Date().toISOString();
      updated.last_inbound_message_at = mergeLastInboundAt(
        previousLastInboundAt,
        incomingConversation?.last_inbound_message_at,
        data.message
      );
      const newList = [...prev];
      newList[index] = updated;
      return newList.sort((a, b) => {
        const timeA = a.last_message_at ? new Date(a.last_message_at).getTime() : 0;
        const timeB = b.last_message_at ? new Date(b.last_message_at).getTime() : 0;
        return timeB - timeA;
      });
    }

    if (incomingConversation) {
      return [incomingConversation, ...prev].sort((a, b) => {
        const timeA = a.last_message_at ? new Date(a.last_message_at).getTime() : 0;
        const timeB = b.last_message_at ? new Date(b.last_message_at).getTime() : 0;
        return timeB - timeA;
      });
    }

    return prev;
  };

  // Realtime subscription with dedup
  useEffect(() => {
    if (!account?.id) return;

    const channel = `account.${account.id}.whatsapp.inbox`;

    const unsubscribeConversationUpdated = subscribe(
      channel,
      '.whatsapp.conversation.updated',
      (data: any) => {
        const eventId = `conv-updated-${data.conversation?.id}-${data.conversation?.updated_at || data.conversation?.last_message_at || ''}`;
        if (!processedMessageIds.current.has(eventId)) {
          processedMessageIds.current.add(eventId);
          const conv = data.conversation || {};
          const incoming = normalizeConversation({
            ...conv,
            assigned_to: conv.assignee_id ?? conv.assigned_to ?? null,
            last_message_at: conv.last_message_at ?? conv.last_activity_at ?? null,
          });
          if (!incoming) return;
          if (
            account?.id != null &&
            incoming.account_id != null &&
            !isSameAccountId(incoming.account_id, account.id)
          ) {
            return;
          }
          setConversations((prev) => applyConversationUpdated(prev, incoming));

          const previousHandoff = handoffStateRef.current.get(incoming.id);
          if (incoming.handoff_status === 'manual' && previousHandoff !== 'manual') {
            addToast({
              title: 'Human takeover needed',
              description: `${incoming.contact.name || incoming.contact.wa_id} was moved to Manual mode.`,
              variant: 'warning',
              duration: 5000,
            });
            playNotificationSound();
            showBrowserNotification(
              `handoff-${incoming.id}-${Date.now()}`,
              'Chat needs human attention',
              incoming.contact.name || incoming.contact.wa_id,
              incoming.id
            );
          }
          handoffStateRef.current.set(incoming.id, incoming.handoff_status ?? null);

          if (currentUserId && notifyAssignmentEnabled && incoming.assigned_to === currentUserId) {
            const previous = assignmentStateRef.current.get(incoming.id);
            if (previous !== incoming.assigned_to) {
              addToast({
                title: 'Conversation assigned',
                description: 'A chat was assigned to you.',
                variant: 'info',
                duration: 3000,
              });
              playNotificationSound();
            }
          }
          assignmentStateRef.current.set(incoming.id, incoming.assigned_to ?? null);
          if (processedMessageIds.current.size > 100) {
            const ids = Array.from(processedMessageIds.current);
            processedMessageIds.current = new Set(ids.slice(-50));
          }
        }
      }
    );

    const unsubscribeMessageCreated = subscribe(
      channel,
      '.whatsapp.message.created',
      (data: any) => {
        const eventId = `msg-created-${data.message?.id}-${data.conversation_id}`;
        if (!processedMessageIds.current.has(eventId)) {
          processedMessageIds.current.add(eventId);
          setConversations((prev) => {
            const hadConversation = prev.some((c) => c.id === data.conversation_id);
            const hasConversationPayload = Boolean(normalizeConversation(data.conversation));
            const updated = applyMessageCreated(prev, data);
            if (!hadConversation && !hasConversationPayload && data.conversation_id) {
              fetchInboxStreamAndMerge();
            }
            return updated;
          });
          const message = normalizeChatMessage(data.message);
          const conversationId = Number(data.conversation_id);
          if (message && Number.isFinite(conversationId)) {
            mergeMessages(conversationId, [message]);
          }
          if (data.message?.direction === 'inbound') {
            notifyInboundMessage({
              id: eventId,
              conversationId: Number(data.conversation_id),
              contactName: data.contact?.name || data.contact?.wa_id || null,
              preview: data.message?.text_body || data.message?.type || null,
              activeConversationId: activeId,
            });
          }
          if (processedMessageIds.current.size > 100) {
            const ids = Array.from(processedMessageIds.current);
            processedMessageIds.current = new Set(ids.slice(-50));
          }
        }
      }
    );

    return () => {
      unsubscribeConversationUpdated();
      unsubscribeMessageCreated();
    };
  }, [
    account?.id,
    subscribe,
    addToast,
    currentUserId,
    notifyAssignmentEnabled,
    playNotificationSound,
    showBrowserNotification,
    fetchInboxStreamAndMerge,
    notifyInboundMessage,
    activeId,
    mergeMessages,
  ]);

  // Keep a fallback when connected, and recover promptly when realtime drops.
  useEffect(() => {
    if (!account?.id) return;

    const interval = setInterval(fetchInboxStreamAndMerge, connected ? 10000 : 3000);
    fetchInboxStreamAndMerge();

    return () => clearInterval(interval);
  }, [account?.id, fetchInboxStreamAndMerge, connected]);

  // Keyboard shortcuts
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
        e.preventDefault();
        const searchInput = document.querySelector('input[type="search"]') as HTMLInputElement;
        searchInput?.focus();
      }
    };

    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, []);

  return (
    <AppShell>
      <Head title="Inbox" />
      <div className="h-[calc(100vh-8rem)] w-full min-w-0 overflow-hidden lg:h-[calc(100vh-6rem)]">
        <div
          className={`grid h-full w-full min-w-0 overflow-hidden rounded-card border border-gray-100 bg-white shadow-card dark:border-ui-dark-border dark:bg-ui-dark-surface dark:shadow-none lg:grid-cols-[minmax(320px,360px)_minmax(0,1fr)] ${showContactPanel ? 'xl:grid-cols-[360px_minmax(0,1fr)_320px]' : 'xl:grid-cols-[360px_minmax(0,1fr)]'}`}
        >
          <ConversationList
            mobileShowDetail={mobileShowDetail}
            filteredConversations={filteredConversations}
            connected={connected}
            notificationPermission={notificationPermission}
            requestBrowserNotifications={requestBrowserNotifications}
            setBulkMode={setBulkMode}
            bulkMode={bulkMode}
            setSelectedIds={setSelectedIds}
            setShowNewChat={setShowNewChat}
            searchQuery={searchQuery}
            setSearchQuery={setSearchQuery}
            setStarredOnly={setStarredOnly}
            starredOnly={starredOnly}
            setAssigneeFilter={setAssigneeFilter}
            setStatusFilter={setStatusFilter}
            assigneeFilter={assigneeFilter}
            statusFilter={statusFilter}
            conversations={conversations}
            setLabelFilter={setLabelFilter}
            labelFilter={labelFilter}
            selectedIds={selectedIds}
            connections={connections}
            connectionFilter={connectionFilter}
            setConnectionFilter={setConnectionFilter}
            loading={loading}
            activeConversation={activeConversation}
            agents={agents}
            replyWindowNow={replyWindowNow}
            toggleSelectedConversation={toggleSelectedConversation}
            setActiveId={setActiveId}
            setMobileShowDetail={setMobileShowDetail}
            formatRelative={formatRelative}
          />

          <section
            className={`${mobileShowDetail ? 'flex' : 'hidden lg:flex'} min-h-0 min-w-0 w-full flex-col bg-gray-50/80 dark:bg-slate-950`}
          >
            {activeConversation ? (
              <>
                <div className="flex min-h-14 flex-shrink-0 items-center gap-2 border-b border-gray-100 bg-white px-3 py-1.5 dark:border-ui-dark-border dark:bg-slate-900">
                  <button
                    type="button"
                    onClick={() => setMobileShowDetail(false)}
                    className="flex h-8 w-8 items-center justify-center rounded-md hover:bg-gray-100 dark:hover:bg-slate-800 lg:hidden"
                    aria-label="Back"
                  >
                    <ArrowLeft className="h-5 w-5" />
                  </button>
                  <div className="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-ui-green text-sm font-semibold text-white">
                    {activeConversation.contact.name?.charAt(0).toUpperCase() || 'C'}
                  </div>
                  <div className="min-w-0 flex-1">
                    <div className="flex min-w-0 items-center gap-2">
                      <div className="truncate text-sm font-semibold text-ui-text dark:text-ui-dark-text sm:text-base">
                        {activeConversation.contact.name || activeConversation.contact.wa_id}
                      </div>
                      <Badge
                        variant={
                          activeConversation.status === 'open'
                            ? 'success'
                            : activeConversation.status === 'pending'
                              ? 'warning'
                              : 'default'
                        }
                        className="hidden px-2 py-0.5 text-[10px] sm:inline-flex"
                      >
                        {friendlyStatus(activeConversation.status)}
                      </Badge>
                    </div>
                    <div className="mt-0.5 flex min-w-0 items-center gap-1.5 overflow-hidden text-[11px] text-ui-text-muted dark:text-ui-dark-text-muted">
                      <span className="truncate">{contactDisplayPhone(activeConversation)}</span>
                      <span className="h-1 w-1 flex-shrink-0 rounded-full bg-gray-300 dark:bg-slate-600" />
                      <span className="hidden truncate md:inline">
                        {conversationSourceLabel(activeConversation)}
                      </span>
                      <span className="hidden h-1 w-1 flex-shrink-0 rounded-full bg-gray-300 md:inline-block dark:bg-slate-600" />
                      <span className="truncate">Assigned: {activeAgentName}</span>
                    </div>
                  </div>
                  <button
                    type="button"
                    onClick={() =>
                      updateConversationMeta({
                        priority: activeConversation.priority === 'urgent' ? 'normal' : 'urgent',
                      })
                    }
                    className={`flex h-8 w-8 items-center justify-center rounded-md transition ${activeConversation.priority === 'urgent' ? 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-300' : 'text-ui-text-muted hover:bg-gray-100 hover:text-ui-text dark:text-ui-dark-text-muted dark:hover:bg-slate-800 dark:hover:text-ui-dark-text'}`}
                    title="Star"
                  >
                    <Star
                      className={`h-4 w-4 ${activeConversation.priority === 'urgent' ? 'fill-amber-400' : ''}`}
                    />
                  </button>
                  {agents.length > 0 && (
                    <label className="relative hidden sm:block">
                      <User className="pointer-events-none absolute left-2 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-ui-text-muted dark:text-ui-dark-text-muted" />
                      <select
                        value={activeConversation.assigned_to ?? ''}
                        disabled={assigningId === activeConversation.id}
                        onChange={(e) => {
                          const assignedTo = e.target.value === '' ? null : Number(e.target.value);
                          setAssigningId(activeConversation.id);
                          updateConversationMeta({ assigned_to: assignedTo });
                          setAssigningId(null);
                        }}
                        className="h-8 max-w-[140px] rounded-btn border-gray-200 bg-white pl-7 pr-7 text-xs font-medium text-ui-text shadow-sm focus:border-ui-green focus:ring-ui-green/20 dark:border-ui-dark-border dark:bg-slate-900 dark:text-ui-dark-text"
                      >
                        <option value="">Unassigned</option>
                        {agents.map((agent) => (
                          <option key={agent.id} value={agent.id}>
                            {agent.id === currentUserId ? 'You' : agent.name}
                          </option>
                        ))}
                      </select>
                      <ChevronDown className="pointer-events-none absolute right-2 top-1/2 h-3 w-3 -translate-y-1/2 text-ui-text-muted dark:text-ui-dark-text-muted" />
                    </label>
                  )}
                  <div className="relative">
                    <button
                      type="button"
                      onClick={() => setActionsMenuOpen((open) => !open)}
                      className={`flex h-8 w-8 items-center justify-center rounded-md transition ${actionsMenuOpen ? 'bg-gray-100 text-ui-text dark:bg-slate-800 dark:text-ui-dark-text' : 'text-ui-text-muted hover:bg-gray-100 hover:text-ui-text dark:text-ui-dark-text-muted dark:hover:bg-slate-800 dark:hover:text-ui-dark-text'}`}
                      title="More"
                    >
                      <MoreVertical className="h-4 w-4" />
                    </button>
                    {actionsMenuOpen && (
                      <div className="absolute right-0 top-10 z-30 w-52 overflow-hidden rounded-card bg-white py-1 text-xs shadow-pop ring-1 ring-gray-100 dark:bg-slate-900 dark:ring-slate-700">
                        <button
                          type="button"
                          onClick={() => {
                            updateConversationMeta({
                              status: activeConversation.status === 'closed' ? 'open' : 'closed',
                            });
                            setActionsMenuOpen(false);
                          }}
                          className="flex w-full items-center gap-2 px-3 py-2 text-left text-ui-text hover:bg-gray-50 dark:text-ui-dark-text dark:hover:bg-slate-800"
                        >
                          {activeConversation.status === 'closed' ? (
                            <RotateCcw className="h-4 w-4" />
                          ) : (
                            <CheckCircle className="h-4 w-4" />
                          )}
                          {activeConversation.status === 'closed' ? 'Reopen' : 'Resolve'}
                        </button>
                        <button
                          type="button"
                          onClick={() => {
                            addToast({
                              title: 'Snoozed',
                              description: 'Conversation snoozed for 1 hour.',
                              variant: 'info',
                            });
                            setActionsMenuOpen(false);
                          }}
                          className="flex w-full items-center gap-2 px-3 py-2 text-left text-ui-text hover:bg-gray-50 dark:text-ui-dark-text dark:hover:bg-slate-800"
                        >
                          <Clock className="h-4 w-4" />
                          Snooze 1 hour
                        </button>
                        <button
                          type="button"
                          onClick={() => {
                            updateConversationMeta({ priority: 'urgent' });
                            setActionsMenuOpen(false);
                          }}
                          className="flex w-full items-center gap-2 px-3 py-2 text-left text-ui-text hover:bg-gray-50 dark:text-ui-dark-text dark:hover:bg-slate-800"
                        >
                          <Flag className="h-4 w-4" />
                          Mark urgent
                        </button>
                        <button
                          type="button"
                          onClick={() => {
                            updateConversationMeta({ assigned_to: null });
                            setActionsMenuOpen(false);
                          }}
                          className="flex w-full items-center gap-2 px-3 py-2 text-left text-ui-text hover:bg-gray-50 dark:text-ui-dark-text dark:hover:bg-slate-800"
                        >
                          <UserX className="h-4 w-4" />
                          Unassign
                        </button>
                        {canDeleteChats && (
                          <button
                            type="button"
                            onClick={() => {
                              setActionsMenuOpen(false);
                              deleteConversation();
                            }}
                            disabled={deletingConversationId === activeConversation.id}
                            className="flex w-full items-center gap-2 px-3 py-2 text-left text-red-600 hover:bg-red-50 disabled:opacity-60 dark:text-red-300 dark:hover:bg-red-500/10"
                          >
                            <Trash2 className="h-4 w-4" />
                            Delete chat
                          </button>
                        )}
                      </div>
                    )}
                  </div>
                  <button
                    type="button"
                    onClick={() => setMobileContactPanelOpen(true)}
                    className={`flex h-8 w-8 items-center justify-center rounded-md transition xl:hidden ${mobileContactPanelOpen ? 'bg-ui-green-soft text-ui-green-dark dark:bg-ui-green/10 dark:text-ui-green' : 'text-ui-text-muted hover:bg-gray-100 dark:text-ui-dark-text-muted dark:hover:bg-slate-800'}`}
                    title="Contact details"
                  >
                    <PanelRight className="h-4 w-4" />
                  </button>
                  <button
                    type="button"
                    onClick={() => setShowContactPanel(!showContactPanel)}
                    className={`hidden h-8 w-8 items-center justify-center rounded-md transition xl:flex ${showContactPanel ? 'bg-ui-green-soft text-ui-green-dark dark:bg-ui-green/10 dark:text-ui-green' : 'text-ui-text-muted hover:bg-gray-100 dark:text-ui-dark-text-muted dark:hover:bg-slate-800'}`}
                    title="Contact details"
                  >
                    <PanelRight className="h-4 w-4" />
                  </button>
                </div>

                <div className="flex-shrink-0 border-b border-gray-100 bg-gray-50/90 px-3 py-1.5 dark:border-ui-dark-border dark:bg-slate-800/95">
                  <div className="flex min-w-0 items-center gap-1.5 overflow-x-auto ui-scrollbar">
                    <span
                      className={`inline-flex h-7 flex-shrink-0 items-center gap-1 rounded-full px-2.5 text-[11px] font-semibold ${activeBotIsReplying ? 'bg-purple-50 text-purple-700 dark:bg-purple-500/10 dark:text-purple-200' : activeManualMode ? 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300' : activeAutomationState ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' : 'bg-gray-100 text-gray-700 dark:bg-slate-700 dark:text-slate-200'}`}
                    >
                      {activeManualMode ? (
                        <User className="h-3 w-3" />
                      ) : (
                        <Zap className="h-3 w-3" />
                      )}
                      {conversationModeLabel(activeConversation)}
                    </span>
                    <span
                      className={`inline-flex h-7 flex-shrink-0 items-center gap-1 rounded-full px-2.5 text-[11px] font-medium ${slaClasses(conversationSlaLabel(activeConversation).tone)}`}
                    >
                      <Clock className="h-3 w-3" />
                      SLA {conversationSlaLabel(activeConversation).label}
                    </span>
                    <span
                      className={`inline-flex h-7 flex-shrink-0 items-center gap-1 rounded-full px-2.5 text-[11px] font-semibold ${slaClasses(activeReplyWindow.tone)}`}
                      title={activeReplyWindow.detail}
                    >
                      <MessageSquare className="h-3 w-3" />
                      {activeReplyWindow.label}
                    </span>
                    <span className="inline-flex h-7 flex-shrink-0 items-center gap-1 rounded-full bg-white px-2.5 text-[11px] font-medium text-ui-text-muted ring-1 ring-gray-100 dark:bg-slate-900 dark:text-ui-dark-text-muted dark:ring-slate-700">
                      <User className="h-3 w-3" />
                      {activeAgentName}
                    </span>
                    {activeConversation.priority && (
                      <span className="inline-flex h-7 flex-shrink-0 items-center gap-1 rounded-full bg-red-50 px-2.5 text-[11px] text-red-700 dark:bg-red-500/10 dark:text-red-300">
                        <Flag className="h-3 w-3" /> {activeConversation.priority}
                      </span>
                    )}
                    <span className="h-5 w-px flex-shrink-0 bg-gray-200 dark:bg-slate-700" />
                    <button
                      type="button"
                      onClick={() =>
                        updateConversationMeta({
                          status: activeConversation.status === 'closed' ? 'open' : 'closed',
                        })
                      }
                      className={`inbox-resolve-btn inline-flex h-7 flex-shrink-0 items-center gap-1 rounded-md px-2.5 text-[11px] font-medium transition ${activeConversation.status === 'closed' ? 'is-resolved bg-gray-200 text-gray-700 dark:bg-slate-600 dark:text-slate-200' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-500/10 dark:text-emerald-300'}`}
                    >
                      {activeConversation.status === 'closed' ? (
                        <RotateCcw className="h-3 w-3" />
                      ) : (
                        <CheckCircle className="h-3 w-3" />
                      )}
                      {activeConversation.status === 'closed' ? 'Reopen' : 'Resolve'}
                    </button>
                    <button
                      type="button"
                      onClick={() => toggleBotPaused(!activeManualMode)}
                      className={`inline-flex h-7 flex-shrink-0 items-center gap-1 rounded-md px-2.5 text-[11px] font-medium transition ${activeManualMode ? 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-100' : 'bg-amber-50 text-amber-700 hover:bg-amber-100 dark:bg-amber-500/10 dark:text-amber-300'}`}
                    >
                      {activeManualMode ? (
                        <Play className="h-3 w-3" />
                      ) : (
                        <User className="h-3 w-3" />
                      )}
                      {activeManualMode ? 'Resume bot' : 'Manual'}
                    </button>
                    {INBOX_LABELS.filter(
                      (label) => !getConversationLabels(activeConversation).includes(label.id)
                    )
                      .slice(0, 2)
                      .map((label) => (
                        <button
                          key={label.id}
                          type="button"
                          onClick={() => addConversationLabel(label.id)}
                          className="inbox-label-add hidden flex-shrink-0 px-1.5 text-[10px] text-ui-text-muted hover:text-ui-green-dark dark:text-ui-dark-text-muted lg:inline-flex"
                        >
                          + {label.label}
                        </button>
                      ))}
                  </div>
                </div>
                {activeBotIsReplying && (
                  <div className="flex flex-shrink-0 flex-wrap items-center gap-2 border-b border-purple-100 bg-purple-50 px-3 py-2 text-xs text-purple-800 dark:border-purple-500/20 dark:bg-purple-500/10 dark:text-purple-200">
                    <span className="inline-flex items-center gap-1.5 font-semibold">
                      <Zap className="h-3.5 w-3.5" />
                      {activeConversation.automation_processing_mode === 'ai'
                        ? 'AI is replying'
                        : 'Bot is replying'}
                    </span>
                    <span className="min-w-0 flex-1 truncate">
                      Human sending is blocked for a few seconds to avoid duplicate replies.
                    </span>
                    <button
                      type="button"
                      onClick={() => toggleBotPaused(true)}
                      className="inline-flex h-7 items-center gap-1 rounded-md bg-white px-2.5 text-[11px] font-medium text-purple-700 shadow-sm ring-1 ring-purple-100 transition hover:bg-purple-100 dark:bg-slate-900 dark:text-purple-200 dark:ring-purple-500/20"
                    >
                      <User className="h-3 w-3" />
                      Take over
                    </button>
                  </div>
                )}
                {activeManualMode && (
                  <div className="flex flex-shrink-0 flex-wrap items-center gap-2 border-b border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200">
                    <span className="inline-flex items-center gap-1.5 font-semibold">
                      <User className="h-3.5 w-3.5" />
                      Manual mode
                    </span>
                    <span className="min-w-0 flex-1 truncate">
                      {activeConversation.handoff_reason ||
                        activeConversation.bot_paused_reason ||
                        'Automation and AI autopilot will not reply in this chat.'}
                    </span>
                    <button
                      type="button"
                      onClick={() => toggleBotPaused(false)}
                      className="inline-flex h-7 items-center gap-1 rounded-md bg-white px-2.5 text-[11px] font-medium text-slate-700 shadow-sm ring-1 ring-slate-200 transition hover:bg-slate-100 dark:bg-slate-900 dark:text-slate-100 dark:ring-slate-700"
                    >
                      <Play className="h-3 w-3" />
                      Resume
                    </button>
                  </div>
                )}
                {!activeReplyWindow.isOpen && (
                  <div className="flex flex-shrink-0 flex-wrap items-center gap-2 border-b border-red-100 bg-red-50 px-3 py-2 text-xs text-red-800 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-200">
                    <span className="inline-flex items-center gap-1.5 font-semibold">
                      <AlertCircle className="h-3.5 w-3.5" />
                      {activeReplyWindow.state === 'closed'
                        ? '24-hour window closed'
                        : 'Template required'}
                    </span>
                    <span className="min-w-0 flex-1 truncate">{activeReplyWindow.detail}</span>
                    <button
                      type="button"
                      onClick={() => {
                        setShowTemplates(true);
                        setShowQuickReplies(false);
                        setShowInteractiveComposer(false);
                      }}
                      className="inline-flex h-7 items-center gap-1 rounded-md bg-white px-2.5 text-[11px] font-medium text-red-700 shadow-sm ring-1 ring-red-100 transition hover:bg-red-100 dark:bg-slate-900 dark:text-red-200 dark:ring-red-500/20"
                    >
                      <FileText className="h-3 w-3" />
                      Templates
                    </button>
                  </div>
                )}
                {activeAutomationState && (
                  <div className="flex flex-shrink-0 flex-wrap items-center gap-2 border-b border-emerald-100 bg-emerald-50 px-3 py-2 text-xs text-emerald-800 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-200">
                    <span className="inline-flex items-center gap-1.5 font-semibold">
                      <Zap className="h-3.5 w-3.5" />
                      Automation waiting
                    </span>
                    <span className="min-w-0 flex-1 truncate">
                      {activeAutomationState.flow_name}
                      {activeAutomationState.waiting_node_label
                        ? ` · ${activeAutomationState.waiting_node_label}`
                        : ''}
                      {(activeAutomationState.invalid_replies ?? 0) > 0
                        ? ` · ${activeAutomationState.invalid_replies} unmatched reply`
                        : ''}
                    </span>
                    <button
                      type="button"
                      onClick={stopAutomation}
                      className="inline-flex h-7 items-center gap-1 rounded-md bg-white px-2.5 text-[11px] font-medium text-red-600 shadow-sm ring-1 ring-emerald-100 transition hover:bg-red-50 dark:bg-slate-900 dark:text-red-300 dark:ring-emerald-500/20 dark:hover:bg-red-500/10"
                    >
                      <Pause className="h-3 w-3" />
                      Stop
                    </button>
                  </div>
                )}

                <MessageTimeline
                  activeDisplayMessages={activeDisplayMessages}
                  activeConversationLoading={activeConversationLoading}
                  formatDateKey={formatDateKey}
                  formatDateLabel={formatDateLabel}
                  formatExactDateTime={formatExactDateTime}
                  formatTime={formatTime}
                  setReaction={setReaction}
                  setReplyTo={setReplyTo}
                  messageReactions={messageReactions}
                  activeMessageReactions={activeMessageReactions}
                  setMediaPreview={setMediaPreview}
                  retryMessage={retryMessage}
                  messagesEndRef={messagesEndRef}
                />

                <div
                  ref={composerRef}
                  className="relative z-20 flex-shrink-0 border-t border-gray-100 bg-white p-3 dark:border-ui-dark-border dark:bg-slate-900"
                >
                  {replyTo && (
                    <div className="mb-2 flex items-center gap-2 rounded-md border-l-2 border-ui-green bg-gray-50 px-3 py-2 dark:bg-slate-800">
                      <Reply className="h-3.5 w-3.5 flex-shrink-0 text-ui-text-muted dark:text-ui-dark-text-muted" />
                      <p className="min-w-0 flex-1 truncate text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
                        {messageReplyPreview(replyTo)}
                      </p>
                      <button
                        type="button"
                        onClick={() => setReplyTo(null)}
                        className="rounded p-1 text-ui-text-muted hover:bg-gray-100 dark:text-ui-dark-text-muted dark:hover:bg-slate-700"
                      >
                        <X className="h-3.5 w-3.5" />
                      </button>
                    </div>
                  )}
                  {showQuickReplies && (
                    <div className="absolute bottom-full left-0 right-0 z-20 mx-3 mb-1 max-h-48 overflow-y-auto rounded-card bg-white shadow-pop ring-1 ring-gray-100 dark:bg-slate-900 dark:ring-slate-700">
                      <div className="border-b border-gray-100 px-3 py-2 text-xs font-semibold text-ui-text-muted dark:border-ui-dark-border dark:text-ui-dark-text-muted">
                        Quick replies
                      </div>
                      {QUICK_REPLIES.map((reply) => (
                        <button
                          key={reply.id}
                          type="button"
                          onClick={() => insertQuickReply(reply.text)}
                          className="w-full border-b border-gray-50 px-3 py-2.5 text-left transition last:border-0 hover:bg-gray-50 dark:border-ui-dark-border dark:hover:bg-slate-800"
                        >
                          <div className="text-xs font-medium text-ui-text dark:text-ui-dark-text">
                            {reply.label}
                          </div>
                          <p className="mt-0.5 truncate text-[11px] text-ui-text-muted dark:text-ui-dark-text-muted">
                            {reply.text}
                          </p>
                        </button>
                      ))}
                    </div>
                  )}
                  {showTemplates && (
                    <div className="absolute bottom-full left-0 right-0 z-20 mx-3 mb-1 max-h-56 overflow-y-auto rounded-card bg-white shadow-pop ring-1 ring-gray-100 dark:bg-slate-900 dark:ring-slate-700">
                      <div className="border-b border-gray-100 px-3 py-2 text-xs font-semibold text-ui-text-muted dark:border-ui-dark-border dark:text-ui-dark-text-muted">
                        Approved templates
                      </div>
                      {templates.length === 0 ? (
                        <div className="px-3 py-6 text-center text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
                          No approved templates found.
                        </div>
                      ) : (
                        templates.slice(0, 8).map((template) => (
                          <button
                            key={template.id}
                            type="button"
                            onClick={() => applyTemplate(template)}
                            className="w-full border-b border-gray-50 px-3 py-2.5 text-left transition last:border-0 hover:bg-gray-50 dark:border-ui-dark-border dark:hover:bg-slate-800"
                          >
                            <div className="flex items-center justify-between gap-2">
                              <div className="truncate text-xs font-medium text-ui-text dark:text-ui-dark-text">
                                {template.name}
                              </div>
                              <span className="text-[10px] text-ui-text-muted dark:text-ui-dark-text-muted">
                                {template.language}
                              </span>
                            </div>
                            <p className="mt-0.5 line-clamp-1 text-[11px] text-ui-text-muted dark:text-ui-dark-text-muted">
                              {template.body_text || 'Template message'}
                            </p>
                          </button>
                        ))
                      )}
                    </div>
                  )}
                  {showInteractiveComposer && (
                    <div className="fixed bottom-20 left-3 right-3 z-50 flex max-h-[min(420px,calc(100dvh-7rem))] flex-col overflow-hidden rounded-card bg-white shadow-pop ring-1 ring-gray-100 dark:bg-slate-900 dark:ring-slate-700 sm:absolute sm:bottom-full sm:left-12 sm:right-auto sm:mb-2 sm:w-[420px] sm:max-h-[430px]">
                      <div className="flex flex-shrink-0 items-center justify-between gap-2 border-b border-gray-100 px-3 py-2 dark:border-ui-dark-border">
                        <div className="min-w-0">
                          <div className="text-xs font-semibold text-ui-text dark:text-ui-dark-text">
                            Interactive message
                          </div>
                          <p className="mt-0.5 hidden truncate text-[11px] text-ui-text-muted dark:text-ui-dark-text-muted sm:block">
                            Saved buttons, lists, forms, products, links, payments, or contact
                            cards.
                          </p>
                        </div>
                        <button
                          type="button"
                          onClick={() => setShowInteractiveComposer(false)}
                          className="rounded-md p-1 text-ui-text-muted hover:bg-gray-100 dark:text-ui-dark-text-muted dark:hover:bg-slate-800"
                        >
                          <X className="h-3.5 w-3.5" />
                        </button>
                      </div>
                      <div className="flex flex-shrink-0 gap-1 overflow-x-auto border-b border-gray-100 bg-gray-50 px-2 py-1.5 dark:border-ui-dark-border dark:bg-slate-800/70 ui-scrollbar">
                        {(
                          [
                            ['buttons', 'Buttons'],
                            ['list', 'List'],
                            ['form', 'Form'],
                            ['product', 'Product'],
                            ['link', 'Link'],
                            ['contact', 'Contact'],
                          ] as Array<[InteractiveMode, string]>
                        ).map(([mode, label]) => (
                          <button
                            key={mode}
                            type="button"
                            onClick={() => setInteractiveMode(mode)}
                            className={`h-7 flex-shrink-0 rounded-md px-2.5 text-[11px] font-medium transition ${interactiveMode === mode ? 'bg-white text-ui-green-dark shadow-sm dark:bg-slate-950 dark:text-ui-green' : 'text-ui-text-muted hover:bg-white/70 hover:text-ui-text dark:text-ui-dark-text-muted dark:hover:bg-slate-950/70 dark:hover:text-ui-dark-text'}`}
                          >
                            {label}
                          </button>
                        ))}
                      </div>
                      <div className="grid min-h-0 flex-1 gap-1.5 overflow-y-auto p-2.5 ui-scrollbar">
                        {!['payment', 'contact', 'product'].includes(interactiveMode) && (
                          <>
                            <input
                              value={interactiveDraft.header_text}
                              onChange={(event) =>
                                setInteractiveDraft((current) => ({
                                  ...current,
                                  header_text: event.target.value,
                                }))
                              }
                              placeholder="Header (optional)"
                              className="rounded-btn border-gray-200 px-3 py-1.5 text-xs dark:border-ui-dark-border dark:bg-slate-800 dark:text-ui-dark-text"
                            />
                            <textarea
                              value={interactiveDraft.body_text}
                              onChange={(event) =>
                                setInteractiveDraft((current) => ({
                                  ...current,
                                  body_text: event.target.value,
                                }))
                              }
                              placeholder="Message body"
                              rows={2}
                              className="resize-none rounded-btn border-gray-200 px-3 py-1.5 text-xs dark:border-ui-dark-border dark:bg-slate-800 dark:text-ui-dark-text"
                            />
                          </>
                        )}
                        {interactiveMode === 'buttons' && (
                          <>
                            <div className="flex items-center justify-between gap-2 rounded-btn border border-gray-100 bg-gray-50 px-2.5 py-1.5 text-[11px] dark:border-ui-dark-border dark:bg-slate-800">
                              <span className="min-w-0 truncate text-ui-text-muted dark:text-ui-dark-text-muted">
                                Create quick button labels here, or manage the full saved list.
                              </span>
                              <Link
                                href={route('app.quick-replies.index')}
                                className="inline-flex flex-shrink-0 items-center gap-1 rounded-md px-2 py-1 font-medium text-ui-green-dark hover:bg-white dark:text-ui-green dark:hover:bg-slate-950"
                              >
                                Manage
                                <ExternalLink className="h-3 w-3" />
                              </Link>
                            </div>
                            <div className="flex gap-2">
                              <input
                                value={newInteractiveButtonLabel}
                                onChange={(event) =>
                                  setNewInteractiveButtonLabel(event.target.value.slice(0, 20))
                                }
                                onKeyDown={(event) => {
                                  if (event.key === 'Enter') {
                                    event.preventDefault();
                                    saveInteractiveButton();
                                  }
                                }}
                                placeholder="Save new button text"
                                maxLength={20}
                                className="min-w-0 flex-1 rounded-btn border-gray-200 px-3 py-1.5 text-xs dark:border-ui-dark-border dark:bg-slate-800 dark:text-ui-dark-text"
                              />
                              <Button
                                type="button"
                                size="sm"
                                variant="secondary"
                                onClick={saveInteractiveButton}
                                disabled={
                                  savingInteractiveButton || !newInteractiveButtonLabel.trim()
                                }
                              >
                                <Plus className="h-3.5 w-3.5" />
                                Save
                              </Button>
                            </div>
                            {savedButtonOptions.length > 0 && (
                              <div className="max-h-32 overflow-y-auto rounded-btn border border-gray-100 bg-gray-50 p-1.5 dark:border-ui-dark-border dark:bg-slate-800 ui-scrollbar">
                                <div className="mb-1 flex items-center justify-between gap-2 px-1 text-[10px] font-semibold uppercase tracking-wide text-ui-text-muted dark:text-ui-dark-text-muted">
                                  <span>Saved buttons</span>
                                  <span>{interactiveDraft.saved_button_ids.length}/3 selected</span>
                                </div>
                                <div className="grid gap-1 sm:grid-cols-2">
                                  {savedButtonOptions.slice(0, 24).map((button) => {
                                    const active = interactiveDraft.saved_button_ids.includes(
                                      Number(button.id)
                                    );
                                    return (
                                      <div
                                        key={button.id}
                                        className={`flex min-w-0 items-center gap-1 rounded-md px-1.5 py-1 text-[11px] ring-1 ${active ? 'bg-ui-green text-white ring-ui-green' : 'bg-white text-ui-text-muted ring-gray-100 dark:bg-slate-950 dark:text-ui-dark-text-muted dark:ring-slate-700'}`}
                                      >
                                        <button
                                          type="button"
                                          onClick={() =>
                                            setInteractiveDraft((current) => {
                                              const exists = current.saved_button_ids.includes(
                                                Number(button.id)
                                              );
                                              const next = exists
                                                ? current.saved_button_ids.filter(
                                                    (id) => id !== Number(button.id)
                                                  )
                                                : [
                                                    ...current.saved_button_ids,
                                                    Number(button.id),
                                                  ].slice(0, 3);
                                              return { ...current, saved_button_ids: next };
                                            })
                                          }
                                          className="min-w-0 flex-1 truncate text-left font-medium"
                                          title={
                                            active
                                              ? 'Remove from this message'
                                              : 'Add to this message'
                                          }
                                        >
                                          {button.button_text || button.label}
                                        </button>
                                        <button
                                          type="button"
                                          onClick={() => void deleteSavedInteractiveButton(button)}
                                          className={`flex h-5 w-5 flex-shrink-0 items-center justify-center rounded transition ${active ? 'text-white/80 hover:bg-white/15 hover:text-white' : 'text-ui-text-muted hover:bg-red-50 hover:text-red-600 dark:text-ui-dark-text-muted dark:hover:bg-red-500/10 dark:hover:text-red-300'}`}
                                          title="Delete saved button"
                                          aria-label={`Delete ${button.button_text || button.label}`}
                                        >
                                          <Trash2 className="h-3 w-3" />
                                        </button>
                                      </div>
                                    );
                                  })}
                                </div>
                              </div>
                            )}
                            <p className="text-[11px] leading-relaxed text-ui-text-muted dark:text-ui-dark-text-muted">
                              Select up to 3 buttons for this message. Add/edit/delete the full
                              library from Quick Replies.
                            </p>
                          </>
                        )}
                        {interactiveMode === 'list' && (
                          <>
                            <select
                              value={interactiveDraft.list_id}
                              onChange={(event) =>
                                setInteractiveDraft((current) => ({
                                  ...current,
                                  list_id: event.target.value,
                                }))
                              }
                              className="rounded-btn border-gray-200 px-3 py-1.5 text-xs dark:border-ui-dark-border dark:bg-slate-800 dark:text-ui-dark-text"
                            >
                              <option value="">Choose saved list</option>
                              {savedLists
                                .filter(
                                  (list) =>
                                    !list.connection_id ||
                                    list.connection_id === activeConversation?.connection.id
                                )
                                .map((list) => (
                                  <option key={list.id} value={list.id}>
                                    {list.name}
                                  </option>
                                ))}
                            </select>
                            <p className="text-[11px] leading-relaxed text-ui-text-muted dark:text-ui-dark-text-muted">
                              Lists are sent from saved WhatsApp list templates only.
                            </p>
                          </>
                        )}
                        {interactiveMode === 'form' && (
                          <>
                            <select
                              value={interactiveDraft.form_id}
                              onChange={(event) =>
                                setInteractiveDraft((current) => ({
                                  ...current,
                                  form_id: event.target.value,
                                }))
                              }
                              className="rounded-btn border-gray-200 px-3 py-1.5 text-xs dark:border-ui-dark-border dark:bg-slate-800 dark:text-ui-dark-text"
                            >
                              <option value="">Choose saved form</option>
                              {savedForms
                                .filter(
                                  (form) =>
                                    !form.connection_id ||
                                    form.connection_id === activeConversation?.connection.id
                                )
                                .map((form) => (
                                  <option key={form.id} value={form.id}>
                                    {form.name}
                                    {form.status && form.status !== 'published'
                                      ? ` (${form.status})`
                                      : ''}
                                  </option>
                                ))}
                            </select>
                            <input
                              value={interactiveDraft.form_button_text}
                              onChange={(event) =>
                                setInteractiveDraft((current) => ({
                                  ...current,
                                  form_button_text: event.target.value,
                                }))
                              }
                              placeholder="Button label"
                              maxLength={30}
                              className="rounded-btn border-gray-200 px-3 py-1.5 text-xs dark:border-ui-dark-border dark:bg-slate-800 dark:text-ui-dark-text"
                            />
                            <p className="text-[11px] leading-relaxed text-ui-text-muted dark:text-ui-dark-text-muted">
                              Sends an official WhatsApp Flow form inside WhatsApp. Custom surveys
                              and Meta lead forms are not shown here.
                            </p>
                          </>
                        )}
                        {interactiveMode === 'product' && (
                          <>
                            {catalogProducts.length > 0 ? (
                              <div className="max-h-44 overflow-y-auto rounded-btn border border-gray-100 bg-gray-50 p-1 dark:border-ui-dark-border dark:bg-slate-800">
                                {catalogProducts
                                  .filter((product) => product.catalog_id && product.retailer_id)
                                  .slice(0, 20)
                                  .map((product) => (
                                    <button
                                      key={product.id}
                                      type="button"
                                      onClick={() =>
                                        setInteractiveDraft((current) => ({
                                          ...current,
                                          product_id: String(product.id),
                                        }))
                                      }
                                      className={`flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-xs ${String(product.id) === String(interactiveDraft.product_id) ? 'bg-ui-green/10 ring-1 ring-ui-green/30' : 'hover:bg-white dark:hover:bg-slate-950'}`}
                                    >
                                      {product.image_url ? (
                                        <img
                                          src={product.image_url}
                                          alt=""
                                          className="h-7 w-7 rounded object-cover"
                                        />
                                      ) : (
                                        <span className="flex h-7 w-7 items-center justify-center rounded bg-white text-ui-text-muted dark:bg-slate-950 dark:text-ui-dark-text-muted">
                                          <ShoppingBag className="h-3.5 w-3.5" />
                                        </span>
                                      )}
                                      <span className="min-w-0 flex-1">
                                        <span className="block truncate font-medium text-ui-text dark:text-ui-dark-text">
                                          {product.name}
                                        </span>
                                        <span className="block truncate text-[10px] text-ui-text-muted dark:text-ui-dark-text-muted">
                                          {product.retailer_id || product.sku} ·{' '}
                                          {product.source || 'catalog'}
                                        </span>
                                      </span>
                                    </button>
                                  ))}
                              </div>
                            ) : (
                              <p className="rounded-btn border border-amber-200 bg-amber-50 px-3 py-2 text-[11px] text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
                                No synced products yet. Connect and sync Meta Catalog from
                                Integrations first.
                              </p>
                            )}
                          </>
                        )}
                        {interactiveMode === 'link' && (
                          <>
                            <input
                              value={interactiveDraft.cta_display_text}
                              onChange={(event) =>
                                setInteractiveDraft((current) => ({
                                  ...current,
                                  cta_display_text: event.target.value,
                                }))
                              }
                              placeholder="Button label"
                              maxLength={20}
                              className="rounded-btn border-gray-200 px-3 py-1.5 text-xs dark:border-ui-dark-border dark:bg-slate-800 dark:text-ui-dark-text"
                            />
                            <input
                              value={interactiveDraft.cta_url}
                              onChange={(event) =>
                                setInteractiveDraft((current) => ({
                                  ...current,
                                  cta_url: event.target.value,
                                }))
                              }
                              placeholder="https://example.com"
                              inputMode="url"
                              className="rounded-btn border-gray-200 px-3 py-1.5 text-xs dark:border-ui-dark-border dark:bg-slate-800 dark:text-ui-dark-text"
                            />
                          </>
                        )}
                        {interactiveMode === 'contact' && (
                          <>
                            <input
                              value={interactiveDraft.contact_name}
                              onChange={(event) =>
                                setInteractiveDraft((current) => ({
                                  ...current,
                                  contact_name: event.target.value,
                                }))
                              }
                              placeholder="Contact name"
                              className="rounded-btn border-gray-200 px-3 py-1.5 text-xs dark:border-ui-dark-border dark:bg-slate-800 dark:text-ui-dark-text"
                            />
                            <input
                              value={interactiveDraft.contact_phone}
                              onChange={(event) =>
                                setInteractiveDraft((current) => ({
                                  ...current,
                                  contact_phone: event.target.value,
                                }))
                              }
                              placeholder="Contact phone with country code"
                              inputMode="tel"
                              className="rounded-btn border-gray-200 px-3 py-1.5 text-xs dark:border-ui-dark-border dark:bg-slate-800 dark:text-ui-dark-text"
                            />
                            <input
                              value={interactiveDraft.contact_email}
                              onChange={(event) =>
                                setInteractiveDraft((current) => ({
                                  ...current,
                                  contact_email: event.target.value,
                                }))
                              }
                              placeholder="Email (optional)"
                              inputMode="email"
                              className="rounded-btn border-gray-200 px-3 py-1.5 text-xs dark:border-ui-dark-border dark:bg-slate-800 dark:text-ui-dark-text"
                            />
                          </>
                        )}
                        {!['payment', 'contact', 'product'].includes(interactiveMode) && (
                          <input
                            value={interactiveDraft.footer_text}
                            onChange={(event) =>
                              setInteractiveDraft((current) => ({
                                ...current,
                                footer_text: event.target.value,
                              }))
                            }
                            placeholder="Footer (optional)"
                            className="rounded-btn border-gray-200 px-3 py-1.5 text-xs dark:border-ui-dark-border dark:bg-slate-800 dark:text-ui-dark-text"
                          />
                        )}
                      </div>
                      <div className="flex flex-shrink-0 items-center justify-end gap-2 border-t border-gray-100 bg-white px-3 py-2 dark:border-ui-dark-border dark:bg-slate-900">
                        <Button
                          type="button"
                          size="sm"
                          variant="secondary"
                          onClick={() => setShowInteractiveComposer(false)}
                        >
                          Cancel
                        </Button>
                        <Button
                          type="button"
                          size="sm"
                          onClick={sendInteractiveMessage}
                          disabled={
                            sendingMessage ||
                            (!['contact', 'payment', 'form'].includes(interactiveMode) &&
                              !interactiveDraft.body_text.trim() &&
                              interactiveMode !== 'list') ||
                            (interactiveMode === 'buttons' &&
                              interactiveDraft.saved_button_ids.length === 0) ||
                            (interactiveMode === 'list' && !interactiveDraft.list_id) ||
                            (interactiveMode === 'form' && !interactiveDraft.form_id) ||
                            (interactiveMode === 'product' && !interactiveDraft.product_id) ||
                            (interactiveMode === 'link' && !interactiveDraft.cta_url.trim()) ||
                            (interactiveMode === 'payment' &&
                              !interactiveDraft.payment_amount.trim()) ||
                            (interactiveMode === 'contact' &&
                              (!interactiveDraft.contact_name.trim() ||
                                !interactiveDraft.contact_phone.trim()))
                          }
                        >
                          <Send className="h-3.5 w-3.5" />
                          Send{' '}
                          {interactiveMode === 'form'
                            ? 'form'
                            : interactiveMode === 'payment'
                              ? 'payment link'
                              : interactiveMode === 'link'
                                ? 'link'
                                : interactiveMode === 'contact'
                                  ? 'contact'
                                  : interactiveMode}
                        </Button>
                      </div>
                    </div>
                  )}
                  {showEmoji && (
                    <div className="absolute bottom-full left-3 z-20 mb-1 flex gap-1 rounded-card bg-white p-2 shadow-pop ring-1 ring-gray-100 dark:bg-slate-900 dark:ring-slate-700">
                      {EMOJI_QUICK.map((emoji) => (
                        <button
                          key={emoji}
                          type="button"
                          onClick={() => {
                            setMessageDraft((draft) => draft + emoji);
                            setShowEmoji(false);
                          }}
                          className="h-8 w-8 rounded-md text-lg transition hover:bg-gray-100 dark:hover:bg-slate-800"
                        >
                          {emoji}
                        </button>
                      ))}
                    </div>
                  )}
                  {showAttachments && (
                    <div className="absolute bottom-full left-12 z-20 mb-1 w-48 overflow-hidden rounded-card bg-white py-1 shadow-pop ring-1 ring-gray-100 dark:bg-slate-900 dark:ring-slate-700">
                      {[
                        ['Photo or video', 'image/*,video/*', ImageIcon],
                        [
                          'Audio or voice',
                          'audio/aac,audio/mp4,audio/mpeg,audio/amr,audio/ogg,audio/wav,.aac,.m4a,.mp3,.amr,.ogg,.wav',
                          Mic,
                        ],
                        [
                          'Document',
                          '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip',
                          FileIcon,
                        ],
                      ].map(([label, accept, Icon]: any) => (
                        <button
                          key={label}
                          type="button"
                          onClick={() => openFilePicker(accept)}
                          className="flex w-full items-center gap-2 px-3 py-2 text-left text-xs text-ui-text transition hover:bg-gray-50 dark:text-ui-dark-text dark:hover:bg-slate-800"
                        >
                          <Icon className="h-4 w-4 text-ui-text-muted dark:text-ui-dark-text-muted" />
                          {label}
                        </button>
                      ))}
                      <button
                        type="button"
                        onClick={() => {
                          setShowAttachments(false);
                          setShowLocationPicker(true);
                        }}
                        className="flex w-full items-center gap-2 px-3 py-2 text-left text-xs text-ui-text transition hover:bg-gray-50 dark:text-ui-dark-text dark:hover:bg-slate-800"
                      >
                        <MapPin className="h-4 w-4 text-ui-text-muted dark:text-ui-dark-text-muted" />
                        Location
                      </button>
                    </div>
                  )}
                  {showLocationPicker && (
                    <div className="absolute bottom-full left-0 right-0 z-20 mx-3 mb-1 max-h-[78vh] overflow-y-auto rounded-card bg-white p-3 shadow-pop ring-1 ring-gray-100 dark:bg-slate-900 dark:ring-slate-700 sm:left-12 sm:right-auto sm:w-[380px]">
                      <div className="mb-2 flex items-center justify-between">
                        <div>
                          <div className="text-xs font-semibold text-ui-text dark:text-ui-dark-text">
                            Send location
                          </div>
                          <div className="text-[10px] text-ui-text-muted dark:text-ui-dark-text-muted">
                            Search, use current, or click the map.
                          </div>
                        </div>
                        <button
                          type="button"
                          onClick={() => setShowLocationPicker(false)}
                          className="rounded-md p-1 text-ui-text-muted hover:bg-gray-100 dark:text-ui-dark-text-muted dark:hover:bg-slate-800"
                        >
                          <X className="h-3.5 w-3.5" />
                        </button>
                      </div>
                      <div className="grid gap-2">
                        <div className="flex gap-2">
                          <input
                            value={locationSearch}
                            onChange={(event) => setLocationSearch(event.target.value)}
                            onKeyDown={(event) => {
                              if (event.key === 'Enter') {
                                event.preventDefault();
                                void searchLocations();
                              }
                            }}
                            placeholder="Search address or landmark"
                            className="min-w-0 flex-1 rounded-btn border-gray-200 px-3 py-2 text-xs dark:border-ui-dark-border dark:bg-slate-800 dark:text-ui-dark-text"
                          />
                          <Button
                            type="button"
                            size="sm"
                            variant="secondary"
                            onClick={() => void searchLocations()}
                            disabled={locationSearching}
                          >
                            {locationSearching ? (
                              <span className="h-3.5 w-3.5 animate-spin rounded-full border-2 border-ui-text-muted border-t-transparent" />
                            ) : (
                              <Search className="h-3.5 w-3.5" />
                            )}
                          </Button>
                        </div>
                        {locationResults.length > 0 && (
                          <div className="max-h-28 overflow-y-auto rounded-lg border border-gray-100 bg-gray-50 p-1 dark:border-ui-dark-border dark:bg-slate-800/70">
                            {locationResults.map((result) => (
                              <button
                                key={`${result.lat}-${result.lng}-${result.label}`}
                                type="button"
                                onClick={() => {
                                  selectLocation(
                                    result.lat,
                                    result.lng,
                                    result.label.split(',').slice(0, 2).join(', ')
                                  );
                                  setLocationResults([]);
                                }}
                                className="flex w-full items-start gap-2 rounded-md px-2 py-1.5 text-left text-[11px] text-ui-text transition hover:bg-white dark:text-ui-dark-text dark:hover:bg-slate-900"
                              >
                                <MapPin className="mt-0.5 h-3.5 w-3.5 flex-shrink-0 text-ui-green" />
                                <span className="line-clamp-2">{result.label}</span>
                              </button>
                            ))}
                          </div>
                        )}
                        <div
                          role="button"
                          tabIndex={0}
                          onClick={handleLocationMapClick}
                          onKeyDown={(event) => {
                            if (event.key === 'Enter' || event.key === ' ') {
                              event.preventDefault();
                              selectLocation(locationMap.lat, locationMap.lng);
                            }
                          }}
                          className="relative h-48 cursor-crosshair overflow-hidden rounded-xl border border-gray-200 bg-slate-100 dark:border-ui-dark-border dark:bg-slate-800"
                          aria-label="Click map to choose a location"
                        >
                          {locationTiles.map((tile) => (
                            <img
                              key={tile.key}
                              src={tile.url}
                              alt=""
                              draggable={false}
                              className="absolute h-64 w-64 select-none"
                              style={{ left: tile.left, top: tile.top }}
                            />
                          ))}
                          <div className="pointer-events-none absolute left-1/2 top-1/2 flex -translate-x-1/2 -translate-y-full flex-col items-center">
                            <MapPin className="h-8 w-8 fill-ui-green text-ui-green drop-shadow" />
                            <span className="h-2 w-2 rounded-full bg-ui-green shadow" />
                          </div>
                          <div className="absolute right-2 top-2 flex overflow-hidden rounded-md bg-white shadow-sm ring-1 ring-gray-200 dark:bg-slate-900 dark:ring-ui-dark-border">
                            <button
                              type="button"
                              onClick={(event) => {
                                event.stopPropagation();
                                setLocationMap((current) => ({
                                  ...current,
                                  zoom: Math.min(18, current.zoom + 1),
                                }));
                              }}
                              className="flex h-7 w-7 items-center justify-center text-sm font-semibold text-ui-text hover:bg-gray-50 dark:text-ui-dark-text dark:hover:bg-slate-800"
                            >
                              +
                            </button>
                            <button
                              type="button"
                              onClick={(event) => {
                                event.stopPropagation();
                                setLocationMap((current) => ({
                                  ...current,
                                  zoom: Math.max(3, current.zoom - 1),
                                }));
                              }}
                              className="flex h-7 w-7 items-center justify-center border-l border-gray-100 text-sm font-semibold text-ui-text hover:bg-gray-50 dark:border-ui-dark-border dark:text-ui-dark-text dark:hover:bg-slate-800"
                            >
                              -
                            </button>
                          </div>
                          <div className="absolute bottom-1 right-2 rounded bg-white/85 px-1.5 py-0.5 text-[9px] text-ui-text-muted dark:bg-slate-950/80 dark:text-ui-dark-text-muted">
                            OpenStreetMap
                          </div>
                        </div>
                        <input
                          value={locationInput.label}
                          onChange={(event) =>
                            setLocationInput((current) => ({
                              ...current,
                              label: event.target.value,
                            }))
                          }
                          placeholder="Location name"
                          className="rounded-btn border-gray-200 px-3 py-2 text-xs dark:border-ui-dark-border dark:bg-slate-800 dark:text-ui-dark-text"
                        />
                        <div className="grid grid-cols-2 gap-2">
                          <input
                            value={locationInput.latitude}
                            onChange={(event) =>
                              setLocationInput((current) => ({
                                ...current,
                                latitude: event.target.value,
                              }))
                            }
                            placeholder="Latitude"
                            inputMode="decimal"
                            className="rounded-btn border-gray-200 px-3 py-2 text-xs dark:border-ui-dark-border dark:bg-slate-800 dark:text-ui-dark-text"
                          />
                          <input
                            value={locationInput.longitude}
                            onChange={(event) =>
                              setLocationInput((current) => ({
                                ...current,
                                longitude: event.target.value,
                              }))
                            }
                            placeholder="Longitude"
                            inputMode="decimal"
                            className="rounded-btn border-gray-200 px-3 py-2 text-xs dark:border-ui-dark-border dark:bg-slate-800 dark:text-ui-dark-text"
                          />
                        </div>
                        {pickedLocationMapUrl && (
                          <a
                            href={pickedLocationMapUrl}
                            target="_blank"
                            rel="noreferrer"
                            className="inline-flex items-center gap-1 text-[11px] font-medium text-ui-green-dark hover:underline dark:text-ui-green"
                          >
                            <ExternalLink className="h-3 w-3" />
                            Preview in Google Maps
                          </a>
                        )}
                        <div className="flex flex-wrap items-center gap-2">
                          <Button
                            type="button"
                            size="sm"
                            variant="secondary"
                            onClick={useCurrentLocationForPicker}
                            disabled={sendingMessage}
                          >
                            <MapPin className="h-3.5 w-3.5" />
                            Use current
                          </Button>
                          <Button
                            type="button"
                            size="sm"
                            onClick={sendManualLocation}
                            disabled={sendingMessage}
                          >
                            Send location
                          </Button>
                        </div>
                      </div>
                    </div>
                  )}
                  <input
                    ref={fileInputRef}
                    type="file"
                    multiple
                    accept={filePickerAccept}
                    className="sr-only"
                    tabIndex={-1}
                    onChange={(event) => addPickedFiles(event.target.files)}
                  />
                  <div className="mb-1 flex flex-wrap items-center gap-1.5">
                    <button
                      type="button"
                      onClick={() => {
                        closeComposerPopovers();
                        setNoteMode(!noteMode);
                      }}
                      className={`whitespace-nowrap rounded-md px-2 py-0.5 text-[11px] font-medium transition ${noteMode ? 'bg-amber-100 text-amber-800 dark:bg-amber-500/10 dark:text-amber-200' : 'text-ui-text-muted hover:bg-gray-100 dark:text-ui-dark-text-muted dark:hover:bg-slate-800'}`}
                    >
                      <Lock className="mr-1 inline h-3 w-3" />
                      {noteMode ? 'Note mode' : 'Add note'}
                    </button>
                  </div>
                  {attachments.length > 0 && (
                    <div
                      ref={attachmentPreviewRef}
                      className="mb-2 rounded-card border border-ui-green/30 bg-ui-green-soft/70 p-2 shadow-sm dark:border-ui-green/30 dark:bg-ui-green/10"
                    >
                      <div className="mb-2 flex items-center justify-between gap-2">
                        <div className="text-xs font-semibold text-ui-green-dark dark:text-ui-green">
                          {attachments.length} file{attachments.length === 1 ? '' : 's'} ready to
                          send
                        </div>
                        <button
                          type="button"
                          onClick={() => setAttachments([])}
                          className="text-[11px] font-medium text-ui-text-muted hover:text-ui-text dark:text-ui-dark-text-muted dark:hover:text-ui-dark-text"
                        >
                          Clear
                        </button>
                      </div>
                      <div className="grid gap-2 sm:grid-cols-2">
                        {attachments.map((file, index) => (
                          <div
                            key={`${file.name}-${index}`}
                            className="flex min-w-0 items-center gap-2 rounded-btn bg-white px-2.5 py-2 text-xs shadow-sm ring-1 ring-gray-100 dark:bg-slate-800 dark:ring-slate-700"
                          >
                            <span className="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-md bg-gray-50 text-ui-text-muted dark:bg-slate-900 dark:text-ui-dark-text-muted">
                              {file.type.startsWith('image/') ? (
                                <ImageIcon className="h-4 w-4" />
                              ) : file.type.startsWith('audio/') ? (
                                <Mic className="h-4 w-4" />
                              ) : (
                                <FileIcon className="h-4 w-4" />
                              )}
                            </span>
                            <span className="min-w-0 flex-1">
                              <span className="block truncate font-medium text-ui-text dark:text-ui-dark-text">
                                {file.name}
                              </span>
                              <span className="block text-[10px] text-ui-text-muted dark:text-ui-dark-text-muted">
                                {file.type || 'file'} · {formatFileSize(file.size)}
                              </span>
                            </span>
                            <button
                              type="button"
                              onClick={() => removeAttachment(index)}
                              className="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-md text-gray-400 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-slate-700 dark:hover:text-gray-200"
                              aria-label={`Remove ${file.name}`}
                            >
                              <X className="h-3.5 w-3.5" />
                            </button>
                          </div>
                        ))}
                      </div>
                    </div>
                  )}
                  <div
                    className={`flex items-end gap-1.5 rounded-btn px-2.5 py-1.5 ${noteMode ? 'bg-white ring-1 ring-ui-border dark:bg-slate-800 dark:ring-ui-dark-border' : 'bg-gray-50 dark:bg-slate-800'}`}
                  >
                    {!noteMode && (
                      <button
                        type="button"
                        onClick={() => {
                          setShowEmoji(!showEmoji);
                          setShowAttachments(false);
                          setShowLocationPicker(false);
                          setShowTemplates(false);
                          setShowQuickReplies(false);
                        }}
                        className="flex h-8 w-8 items-center justify-center rounded-md text-ui-text-muted hover:bg-gray-100 dark:text-ui-dark-text-muted dark:hover:bg-slate-700"
                      >
                        <Smile className="h-4 w-4" />
                      </button>
                    )}
                    {!noteMode && (
                      <button
                        type="button"
                        onClick={() => {
                          setShowAttachments(!showAttachments);
                          setShowLocationPicker(false);
                          setShowEmoji(false);
                          setShowTemplates(false);
                          setShowQuickReplies(false);
                        }}
                        className={`relative flex h-8 w-8 items-center justify-center rounded-md transition ${attachments.length > 0 ? 'bg-ui-green text-white' : 'text-ui-text-muted hover:bg-gray-100 dark:text-ui-dark-text-muted dark:hover:bg-slate-700'}`}
                        title={
                          attachments.length > 0
                            ? `${attachments.length} file${attachments.length === 1 ? '' : 's'} attached`
                            : 'Attach file'
                        }
                      >
                        <Paperclip className="h-4 w-4" />
                        {attachments.length > 0 && (
                          <span className="absolute -right-1 -top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-white px-1 text-[9px] font-bold text-ui-green-dark shadow-sm ring-1 ring-ui-green/20">
                            {attachments.length}
                          </span>
                        )}
                      </button>
                    )}
                    {attachmentSummary && (
                      <button
                        type="button"
                        onClick={() =>
                          attachmentPreviewRef.current?.scrollIntoView({
                            block: 'nearest',
                            behavior: 'smooth',
                          })
                        }
                        className="hidden min-w-0 max-w-[180px] items-center gap-1 rounded-md bg-ui-green-soft px-2 py-1 text-[11px] font-medium text-ui-green-dark ring-1 ring-ui-green/20 dark:bg-ui-green/10 dark:text-ui-green sm:flex"
                        title={attachmentSummary}
                      >
                        <FileIcon className="h-3 w-3 flex-shrink-0" />
                        <span className="truncate">{attachmentSummary}</span>
                      </button>
                    )}
                    <textarea
                      rows={1}
                      value={messageDraft}
                      onChange={(event) => setMessageDraft(event.target.value)}
                      onKeyDown={(event) => {
                        if (event.key === 'Enter' && !event.shiftKey) {
                          event.preventDefault();
                          sendInlineMessage();
                        }
                      }}
                      placeholder={
                        noteMode
                          ? 'Write an internal note...'
                          : activeBotIsReplying
                            ? 'Bot is replying - take over to send manually'
                            : !activeReplyWindow.isOpen
                              ? '24h window closed - use an approved template'
                              : 'Type a message...'
                      }
                      disabled={!noteMode && (activeBotIsReplying || !activeReplyWindow.isOpen)}
                      className="max-h-24 flex-1 resize-none border-0 bg-transparent py-1 text-sm text-ui-text outline-none ring-0 placeholder:text-gray-400 disabled:cursor-not-allowed disabled:opacity-60 dark:text-ui-dark-text"
                    />
                    {!noteMode && (
                      <button
                        type="button"
                        onClick={toggleVoiceRecording}
                        className={`flex h-8 w-8 items-center justify-center rounded-md transition ${recordingVoice ? 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-300' : 'text-ui-text-muted hover:bg-gray-100 dark:text-ui-dark-text-muted dark:hover:bg-slate-700'}`}
                        title={recordingVoice ? 'Stop recording' : 'Record voice message'}
                      >
                        <Mic className="h-4 w-4" />
                      </button>
                    )}
                    <Button
                      type="button"
                      size="sm"
                      onClick={sendInlineMessage}
                      disabled={
                        sendingMessage ||
                        (!noteMode && activeBotIsReplying) ||
                        (!messageDraft.trim() && attachments.length === 0 && !freeFormReplyBlocked)
                      }
                    >
                      {noteMode ? <Lock className="h-4 w-4" /> : <Send className="h-4 w-4" />}
                      {sendingMessage
                        ? 'Sending'
                        : activeBotIsReplying && !noteMode
                          ? 'Bot replying'
                          : freeFormReplyBlocked
                            ? 'Template'
                            : noteMode
                              ? 'Save'
                              : 'Send'}
                    </Button>
                  </div>
                  {!noteMode && (
                    <div className="mt-1 flex items-center gap-1 overflow-x-auto px-1 pb-0.5 text-[11px] text-ui-text-muted dark:text-ui-dark-text-muted ui-scrollbar">
                      {[
                        {
                          label: 'Template',
                          icon: FileText,
                          active: showTemplates,
                          onClick: () => {
                            setShowTemplates(!showTemplates);
                            setShowQuickReplies(false);
                            setShowInteractiveComposer(false);
                            setShowEmoji(false);
                            setShowAttachments(false);
                            setShowLocationPicker(false);
                          },
                        },
                        {
                          label: 'Quick replies',
                          icon: Zap,
                          active: showQuickReplies,
                          onClick: () => {
                            setShowQuickReplies(!showQuickReplies);
                            setShowTemplates(false);
                            setShowInteractiveComposer(false);
                            setShowEmoji(false);
                            setShowAttachments(false);
                            setShowLocationPicker(false);
                          },
                        },
                        {
                          label: 'Interactive',
                          icon: ClipboardList,
                          active:
                            showInteractiveComposer &&
                            !['payment', 'product'].includes(interactiveMode),
                          onClick: () => {
                            setInteractiveMode(
                              interactiveMode === 'payment' || interactiveMode === 'product'
                                ? 'buttons'
                                : interactiveMode
                            );
                            setShowInteractiveComposer(
                              !showInteractiveComposer ||
                                ['payment', 'product'].includes(interactiveMode)
                            );
                            setShowTemplates(false);
                            setShowQuickReplies(false);
                            setShowEmoji(false);
                            setShowAttachments(false);
                            setShowLocationPicker(false);
                          },
                        },
                      ].map((item) => {
                        const Icon = item.icon;
                        return (
                          <button
                            key={item.label}
                            type="button"
                            onClick={item.onClick}
                            className={`inline-flex h-6 flex-shrink-0 items-center gap-1 rounded-md px-1.5 text-[10px] font-medium transition ${item.active ? 'bg-ui-green-soft text-ui-green-dark ring-1 ring-ui-green/15 dark:bg-ui-green/10 dark:text-ui-green' : 'hover:bg-gray-100 hover:text-ui-text dark:hover:bg-slate-800 dark:hover:text-ui-dark-text'}`}
                          >
                            <Icon className="h-3 w-3" />
                            {item.label}
                          </button>
                        );
                      })}
                    </div>
                  )}
                </div>
              </>
            ) : (
              <div className="flex flex-1 items-center justify-center">
                <EmptyState
                  icon={MessageSquare}
                  title="Select a conversation"
                  description="Choose a chat from the list or start a new one."
                />
              </div>
            )}
          </section>

          {showContactPanel && activeConversation && (
            <aside className="hidden min-h-0 flex-col border-l border-gray-100 bg-white dark:border-ui-dark-border dark:bg-slate-900 xl:flex">
              <div className="flex h-10 flex-shrink-0 items-center justify-between border-b border-gray-100 px-3 dark:border-ui-dark-border">
                <span className="text-xs font-semibold uppercase tracking-wide text-ui-text-muted dark:text-ui-dark-text-muted">
                  Contact details
                </span>
                <button
                  type="button"
                  onClick={() => setShowContactPanel(false)}
                  className="flex h-8 w-8 items-center justify-center rounded-md text-ui-text-muted hover:bg-gray-100 dark:text-ui-dark-text-muted dark:hover:bg-slate-800"
                >
                  <X className="h-4 w-4" />
                </button>
              </div>
              <div className="border-b border-gray-100 p-4 text-center dark:border-ui-dark-border">
                <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-ui-green text-2xl font-semibold text-white">
                  {activeConversation.contact.name?.charAt(0).toUpperCase() || 'C'}
                </div>
                <div className="mt-2 font-semibold text-ui-text dark:text-ui-dark-text">
                  {activeConversation.contact.name || 'Unknown contact'}
                </div>
                <div className="text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
                  {contactDisplayPhone(activeConversation)}
                </div>
                <div className="mt-2 flex items-center justify-center gap-1">
                  <button
                    type="button"
                    onClick={() => {
                      setContactTab('notes');
                      setNoteMode(true);
                    }}
                    className="flex h-8 w-8 items-center justify-center rounded-md bg-gray-50 text-ui-text-muted transition hover:text-ui-text dark:bg-slate-800 dark:text-ui-dark-text-muted dark:hover:text-ui-dark-text"
                  >
                    <StickyNote className="h-4 w-4" />
                  </button>
                  <button
                    type="button"
                    onClick={openContactEditor}
                    className="flex h-8 w-8 items-center justify-center rounded-md bg-gray-50 text-ui-text-muted transition hover:text-ui-text dark:bg-slate-800 dark:text-ui-dark-text-muted dark:hover:text-ui-dark-text"
                    title="Edit contact"
                  >
                    <Edit3 className="h-4 w-4" />
                  </button>
                  {contactHref ? (
                    <Link
                      href={contactHref}
                      className="flex h-8 w-8 items-center justify-center rounded-md bg-gray-50 text-ui-text-muted transition hover:text-ui-text dark:bg-slate-800 dark:text-ui-dark-text-muted dark:hover:text-ui-dark-text"
                    >
                      <ExternalLink className="h-4 w-4" />
                    </Link>
                  ) : (
                    <span className="flex h-8 w-8 items-center justify-center rounded-md bg-gray-50 text-ui-text-muted dark:bg-slate-800 dark:text-ui-dark-text-muted">
                      <ExternalLink className="h-4 w-4" />
                    </span>
                  )}
                </div>
              </div>
              <div className="border-b border-gray-100 px-2 pt-2 dark:border-ui-dark-border">
                <div className="grid grid-cols-4 gap-1">
                  {[
                    ['profile', User, 'Profile'],
                    ['media', Paperclip, 'Media'],
                    ['notes', StickyNote, 'Notes'],
                    ['activity', RotateCcw, 'Activity'],
                  ].map(([id, Icon, label]: any) => (
                    <button
                      key={id}
                      type="button"
                      onClick={() => setContactTab(id)}
                      className={`flex flex-col items-center gap-1 rounded-md px-1.5 py-2 text-[10px] font-medium transition ${contactTab === id ? 'bg-ui-green-soft text-ui-green-dark dark:bg-ui-green/10 dark:text-ui-green' : 'text-ui-text-muted hover:bg-gray-100 dark:text-ui-dark-text-muted dark:hover:bg-slate-800'}`}
                    >
                      <Icon className="h-3.5 w-3.5" />
                      {label}
                    </button>
                  ))}
                </div>
              </div>
              <ContactDetailsBody
                contactTab={contactTab}
                activeLabels={activeLabels}
                activeConversation={activeConversation}
                activeAgentName={activeAgentName}
                activeMediaCount={activeMediaCount}
                noteMode={noteMode}
                messageDraft={messageDraft}
                setNoteMode={setNoteMode}
                setMessageDraft={setMessageDraft}
                sendInlineMessage={sendInlineMessage}
                activeNotes={activeNotes}
                activeGallery={activeGallery}
                galleryLoadingId={galleryLoadingId}
                setMediaPreview={setMediaPreview}
                activeInboundCount={activeInboundCount}
                lastInboundAt={lastInboundAt}
                activeOutboundCount={activeOutboundCount}
                lastOutboundAt={lastOutboundAt}
              />
            </aside>
          )}

          {mobileContactPanelOpen && activeConversation && (
            <div className="fixed inset-0 z-[250] flex justify-end xl:hidden">
              <button
                type="button"
                aria-label="Close contact details"
                className="absolute inset-0 bg-black/40"
                onClick={() => setMobileContactPanelOpen(false)}
              />
              <aside className="relative flex h-full w-full max-w-sm flex-col bg-white shadow-pop dark:bg-slate-900">
                <div className="flex h-12 flex-shrink-0 items-center justify-between border-b border-gray-100 px-4 dark:border-ui-dark-border">
                  <span className="text-sm font-semibold text-ui-text dark:text-ui-dark-text">
                    Contact details
                  </span>
                  <button
                    type="button"
                    onClick={() => setMobileContactPanelOpen(false)}
                    className="flex h-8 w-8 items-center justify-center rounded-md text-ui-text-muted hover:bg-gray-100 dark:text-ui-dark-text-muted dark:hover:bg-slate-800"
                  >
                    <X className="h-4 w-4" />
                  </button>
                </div>
                <div className="border-b border-gray-100 p-4 text-center dark:border-ui-dark-border">
                  <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-ui-green text-2xl font-semibold text-white">
                    {activeConversation.contact.name?.charAt(0).toUpperCase() || 'C'}
                  </div>
                  <div className="mt-2 font-semibold text-ui-text dark:text-ui-dark-text">
                    {activeConversation.contact.name || 'Unknown contact'}
                  </div>
                  <div className="text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
                    {contactDisplayPhone(activeConversation)}
                  </div>
                  <div className="mt-2 flex items-center justify-center gap-1">
                    <button
                      type="button"
                      onClick={() => {
                        setContactTab('notes');
                        setNoteMode(true);
                      }}
                      className="flex h-8 w-8 items-center justify-center rounded-md bg-gray-50 text-ui-text-muted transition hover:text-ui-text dark:bg-slate-800 dark:text-ui-dark-text-muted dark:hover:text-ui-dark-text"
                    >
                      <StickyNote className="h-4 w-4" />
                    </button>
                    <button
                      type="button"
                      onClick={openContactEditor}
                      className="flex h-8 w-8 items-center justify-center rounded-md bg-gray-50 text-ui-text-muted transition hover:text-ui-text dark:bg-slate-800 dark:text-ui-dark-text-muted dark:hover:text-ui-dark-text"
                      title="Edit contact"
                    >
                      <Edit3 className="h-4 w-4" />
                    </button>
                    {contactHref ? (
                      <Link
                        href={contactHref}
                        className="flex h-8 w-8 items-center justify-center rounded-md bg-gray-50 text-ui-text-muted transition hover:text-ui-text dark:bg-slate-800 dark:text-ui-dark-text-muted dark:hover:text-ui-dark-text"
                      >
                        <ExternalLink className="h-4 w-4" />
                      </Link>
                    ) : (
                      <span className="flex h-8 w-8 items-center justify-center rounded-md bg-gray-50 text-ui-text-muted dark:bg-slate-800 dark:text-ui-dark-text-muted">
                        <ExternalLink className="h-4 w-4" />
                      </span>
                    )}
                  </div>
                </div>
                <div className="border-b border-gray-100 px-2 pt-2 dark:border-ui-dark-border">
                  <div className="grid grid-cols-4 gap-1">
                    {[
                      ['profile', User, 'Profile'],
                      ['media', Paperclip, 'Media'],
                      ['notes', StickyNote, 'Notes'],
                      ['activity', RotateCcw, 'Activity'],
                    ].map(([id, Icon, label]: any) => (
                      <button
                        key={id}
                        type="button"
                        onClick={() => setContactTab(id)}
                        className={`flex flex-col items-center gap-1 rounded-md px-1.5 py-2 text-[10px] font-medium transition ${contactTab === id ? 'bg-ui-green-soft text-ui-green-dark dark:bg-ui-green/10 dark:text-ui-green' : 'text-ui-text-muted hover:bg-gray-100 dark:text-ui-dark-text-muted dark:hover:bg-slate-800'}`}
                      >
                        <Icon className="h-3.5 w-3.5" />
                        {label}
                      </button>
                    ))}
                  </div>
                </div>
                <ContactDetailsBody
                  contactTab={contactTab}
                  activeLabels={activeLabels}
                  activeConversation={activeConversation}
                  activeAgentName={activeAgentName}
                  activeMediaCount={activeMediaCount}
                  noteMode={noteMode}
                  messageDraft={messageDraft}
                  setNoteMode={setNoteMode}
                  setMessageDraft={setMessageDraft}
                  sendInlineMessage={sendInlineMessage}
                  activeNotes={activeNotes}
                  activeGallery={activeGallery}
                  galleryLoadingId={galleryLoadingId}
                  setMediaPreview={setMediaPreview}
                  activeInboundCount={activeInboundCount}
                  lastInboundAt={lastInboundAt}
                  activeOutboundCount={activeOutboundCount}
                  lastOutboundAt={lastOutboundAt}
                />
              </aside>
            </div>
          )}
        </div>
      </div>
      {editingContact && activeConversation && (
        <div
          className="fixed inset-0 z-[260] flex items-center justify-center bg-slate-950/50 p-4 backdrop-blur-sm"
          role="dialog"
          aria-modal="true"
        >
          <div className="w-full max-w-lg overflow-hidden rounded-xl border border-gray-100 bg-white shadow-2xl dark:border-ui-dark-border dark:bg-slate-900">
            <div className="flex items-center justify-between gap-3 border-b border-gray-100 px-5 py-4 dark:border-ui-dark-border">
              <div>
                <h2 className="text-base font-semibold text-ui-text dark:text-ui-dark-text">
                  Edit contact
                </h2>
                <p className="text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
                  {contactDisplayPhone(activeConversation)}
                </p>
              </div>
              <button
                type="button"
                onClick={() => setEditingContact(false)}
                className="flex h-8 w-8 items-center justify-center rounded-md text-ui-text-muted hover:bg-gray-100 dark:text-ui-dark-text-muted dark:hover:bg-slate-800"
                aria-label="Close"
              >
                <X className="h-4 w-4" />
              </button>
            </div>
            <div className="grid gap-4 px-5 py-4 sm:grid-cols-2">
              <label className="block space-y-1.5">
                <span className="text-xs font-semibold text-ui-text dark:text-ui-dark-text">
                  Name
                </span>
                <input
                  value={contactForm.name}
                  onChange={(event) =>
                    setContactForm((current) => ({ ...current, name: event.target.value }))
                  }
                  className="h-10 w-full rounded-btn border border-ui-border bg-white px-3 text-sm outline-none focus:ring-2 focus:ring-ui-green/20 dark:border-ui-dark-border dark:bg-slate-800"
                />
              </label>
              <label className="block space-y-1.5">
                <span className="text-xs font-semibold text-ui-text dark:text-ui-dark-text">
                  Phone
                </span>
                <input
                  value={contactForm.phone}
                  onChange={(event) =>
                    setContactForm((current) => ({ ...current, phone: event.target.value }))
                  }
                  inputMode="tel"
                  className="h-10 w-full rounded-btn border border-ui-border bg-white px-3 text-sm outline-none focus:ring-2 focus:ring-ui-green/20 dark:border-ui-dark-border dark:bg-slate-800"
                />
              </label>
              <label className="block space-y-1.5">
                <span className="text-xs font-semibold text-ui-text dark:text-ui-dark-text">
                  Email
                </span>
                <input
                  value={contactForm.email}
                  onChange={(event) =>
                    setContactForm((current) => ({ ...current, email: event.target.value }))
                  }
                  type="email"
                  className="h-10 w-full rounded-btn border border-ui-border bg-white px-3 text-sm outline-none focus:ring-2 focus:ring-ui-green/20 dark:border-ui-dark-border dark:bg-slate-800"
                />
              </label>
              <label className="block space-y-1.5">
                <span className="text-xs font-semibold text-ui-text dark:text-ui-dark-text">
                  Company
                </span>
                <input
                  value={contactForm.company}
                  onChange={(event) =>
                    setContactForm((current) => ({ ...current, company: event.target.value }))
                  }
                  className="h-10 w-full rounded-btn border border-ui-border bg-white px-3 text-sm outline-none focus:ring-2 focus:ring-ui-green/20 dark:border-ui-dark-border dark:bg-slate-800"
                />
              </label>
              <label className="block space-y-1.5">
                <span className="text-xs font-semibold text-ui-text dark:text-ui-dark-text">
                  Status
                </span>
                <select
                  value={contactForm.status}
                  onChange={(event) =>
                    setContactForm((current) => ({ ...current, status: event.target.value }))
                  }
                  className="h-10 w-full rounded-btn border border-ui-border bg-white px-3 text-sm outline-none focus:ring-2 focus:ring-ui-green/20 dark:border-ui-dark-border dark:bg-slate-800"
                >
                  <option value="active">Active</option>
                  <option value="inactive">Inactive</option>
                  <option value="blocked">Blocked</option>
                  <option value="opt_out">Opted out</option>
                </select>
              </label>
              <label className="block space-y-1.5 sm:col-span-2">
                <span className="text-xs font-semibold text-ui-text dark:text-ui-dark-text">
                  Notes
                </span>
                <textarea
                  value={contactForm.notes}
                  onChange={(event) =>
                    setContactForm((current) => ({ ...current, notes: event.target.value }))
                  }
                  rows={4}
                  className="w-full resize-none rounded-btn border border-ui-border bg-white px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ui-green/20 dark:border-ui-dark-border dark:bg-slate-800"
                />
              </label>
            </div>
            <div className="flex justify-end gap-2 border-t border-gray-100 bg-gray-50 px-5 py-4 dark:border-ui-dark-border dark:bg-slate-800/60">
              <Button type="button" variant="secondary" onClick={() => setEditingContact(false)}>
                Cancel
              </Button>
              <Button
                type="button"
                onClick={saveContactDetails}
                disabled={savingContact || !contactForm.name.trim()}
              >
                <Check className="h-4 w-4" />
                {savingContact ? 'Saving' : 'Save contact'}
              </Button>
            </div>
          </div>
        </div>
      )}
      {showNewChat && (
        <div
          className="fixed inset-0 z-[240] flex items-center justify-center bg-slate-950/50 p-4 backdrop-blur-sm"
          role="dialog"
          aria-modal="true"
        >
          <div className="w-full max-w-md overflow-hidden rounded-xl border border-gray-100 bg-white shadow-2xl dark:border-ui-dark-border dark:bg-slate-900">
            <div className="flex items-center justify-between gap-3 border-b border-gray-100 px-5 py-4 dark:border-ui-dark-border">
              <div>
                <h2 className="text-base font-semibold text-ui-text dark:text-ui-dark-text">
                  New chat
                </h2>
                <p className="text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
                  The contact is saved automatically.
                </p>
              </div>
              <button
                type="button"
                onClick={() => setShowNewChat(false)}
                className="flex h-8 w-8 items-center justify-center rounded-md text-ui-text-muted hover:bg-gray-100 dark:text-ui-dark-text-muted dark:hover:bg-slate-800"
                aria-label="Close"
              >
                <X className="h-4 w-4" />
              </button>
            </div>
            <div className="space-y-4 px-5 py-4">
              <label className="block space-y-1.5">
                <span className="text-xs font-semibold text-ui-text dark:text-ui-dark-text">
                  Contact name
                </span>
                <input
                  value={newChatForm.name}
                  onChange={(event) =>
                    setNewChatForm((current) => ({ ...current, name: event.target.value }))
                  }
                  placeholder="Customer name"
                  className="h-10 w-full rounded-btn border border-ui-border bg-white px-3 text-sm outline-none focus:ring-2 focus:ring-ui-green/20 dark:border-ui-dark-border dark:bg-slate-800"
                />
              </label>
              <label className="block space-y-1.5">
                <span className="text-xs font-semibold text-ui-text dark:text-ui-dark-text">
                  WhatsApp number
                </span>
                <input
                  value={newChatForm.wa_id}
                  onChange={(event) =>
                    setNewChatForm((current) => ({ ...current, wa_id: event.target.value }))
                  }
                  placeholder="919999999999"
                  inputMode="tel"
                  className="h-10 w-full rounded-btn border border-ui-border bg-white px-3 text-sm outline-none focus:ring-2 focus:ring-ui-green/20 dark:border-ui-dark-border dark:bg-slate-800"
                />
              </label>
              <label className="block space-y-1.5">
                <span className="text-xs font-semibold text-ui-text dark:text-ui-dark-text">
                  WhatsApp connection
                </span>
                <select
                  value={newChatForm.connection_id}
                  onChange={(event) =>
                    setNewChatForm((current) => ({ ...current, connection_id: event.target.value }))
                  }
                  className="h-10 w-full rounded-btn border border-ui-border bg-white px-3 text-sm outline-none focus:ring-2 focus:ring-ui-green/20 dark:border-ui-dark-border dark:bg-slate-800"
                >
                  <option value="">Select connection</option>
                  {(connections || []).map((connection) => (
                    <option key={connection.id} value={connection.id}>
                      {connection.name}
                    </option>
                  ))}
                </select>
              </label>
            </div>
            <div className="flex justify-end gap-2 border-t border-gray-100 bg-gray-50 px-5 py-4 dark:border-ui-dark-border dark:bg-slate-800/60">
              <Button type="button" variant="secondary" onClick={() => setShowNewChat(false)}>
                Cancel
              </Button>
              <Button
                type="button"
                onClick={createNewChat}
                disabled={creatingChat || !newChatForm.wa_id.trim() || !newChatForm.connection_id}
              >
                <Edit3 className="h-4 w-4" />
                {creatingChat ? 'Creating' : 'Create chat'}
              </Button>
            </div>
          </div>
        </div>
      )}
      {mediaPreview && (
        <div
          className="fixed inset-0 z-[250] flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm"
          role="dialog"
          aria-modal="true"
        >
          <div className="flex max-h-[92vh] w-full max-w-4xl flex-col overflow-hidden rounded-xl border border-white/10 bg-white shadow-2xl dark:border-ui-dark-border dark:bg-slate-900">
            <div className="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3 dark:border-ui-dark-border">
              <div className="min-w-0">
                <div className="truncate text-sm font-semibold text-ui-text dark:text-ui-dark-text">
                  {mediaPreview.title}
                </div>
                <div className="text-xs capitalize text-ui-text-muted dark:text-ui-dark-text-muted">
                  {mediaPreview.type}
                </div>
              </div>
              <div className="flex items-center gap-2">
                <a
                  href={mediaPreview.url}
                  target="_blank"
                  rel="noreferrer"
                  className="flex h-9 w-9 items-center justify-center rounded-md text-ui-text-muted transition hover:bg-gray-100 hover:text-ui-text dark:text-ui-dark-text-muted dark:hover:bg-slate-800 dark:hover:text-ui-dark-text"
                  aria-label="Open media in new tab"
                >
                  <ExternalLink className="h-4 w-4" />
                </a>
                <button
                  type="button"
                  onClick={() => setMediaPreview(null)}
                  className="flex h-9 w-9 items-center justify-center rounded-md text-ui-text-muted transition hover:bg-gray-100 hover:text-ui-text dark:text-ui-dark-text-muted dark:hover:bg-slate-800 dark:hover:text-ui-dark-text"
                  aria-label="Close media preview"
                >
                  <X className="h-4 w-4" />
                </button>
              </div>
            </div>
            <div className="min-h-0 flex-1 overflow-auto bg-gray-50 p-4 dark:bg-slate-950">
              {['image', 'sticker'].includes(mediaPreview.type) && (
                <img
                  src={mediaPreview.url}
                  alt={mediaPreview.filename || mediaPreview.title}
                  className="mx-auto max-h-[72vh] max-w-full rounded-lg object-contain"
                />
              )}
              {mediaPreview.type === 'video' && (
                <video controls autoPlay className="mx-auto max-h-[72vh] max-w-full rounded-lg">
                  <source src={mediaPreview.url} />
                </video>
              )}
              {mediaPreview.type === 'audio' && (
                <div className="mx-auto flex min-h-48 max-w-xl items-center justify-center rounded-lg bg-white p-6 shadow-sm dark:bg-slate-900">
                  <audio controls autoPlay className="w-full">
                    <source src={mediaPreview.url} />
                  </audio>
                </div>
              )}
              {mediaPreview.type === 'document' && (
                <iframe
                  src={mediaPreview.url}
                  title={mediaPreview.title}
                  className="h-[72vh] w-full rounded-lg border border-gray-200 bg-white dark:border-ui-dark-border"
                />
              )}
            </div>
          </div>
        </div>
      )}
    </AppShell>
  );
}
