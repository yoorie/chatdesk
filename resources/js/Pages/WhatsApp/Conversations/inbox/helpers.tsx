import {
  AlertCircle,
  Check,
  CheckCheck,
  ClipboardList,
  Clock,
  CreditCard,
  FileText,
  ShoppingBag,
  type LucideIcon,
} from 'lucide-react';
import {
  type ChatMessage,
  type ContactGallery,
  type Conversation,
  type ConversationAuditEvent,
  type ConversationNote,
  type CustomerServiceWindow,
  type InboxCallRecord,
} from './types';

export const inboxDebug = import.meta.env.VITE_INBOX_DEBUG === 'true';

export function shortcutFromButtonLabel(label: string) {
  return (
    label
      .toLowerCase()
      .replace(/[^a-z0-9\s_-]/g, '')
      .replace(/[\s-]+/g, '_')
      .replace(/^_+|_+$/g, '')
      .slice(0, 60) || 'button'
  );
}

export const callSessionSdpFrom = (value: any): string | null => {
  const candidates = [
    value?.session?.sdp,
    value?.call?.metadata?.call?.session?.sdp,
    value?.call?.metadata?.connect_response?.session?.sdp,
    value?.call?.metadata?.connect_response?.calls?.[0]?.session?.sdp,
    value?.metadata?.call?.session?.sdp,
    value?.metadata?.connect_response?.session?.sdp,
    value?.metadata?.connect_response?.calls?.[0]?.session?.sdp,
    value?.metadata?.first_sdp,
    value?.metadata?.latest_sdp,
  ];

  return (
    candidates.find((candidate) => typeof candidate === 'string' && candidate.trim().length > 0) ||
    null
  );
};

export const isIncomingActionableCall = (
  call?: InboxCallRecord | null
): call is InboxCallRecord => {
  if (!call) return false;

  return call.direction === 'inbound' && ['received', 'ringing', 'queued'].includes(call.status);
};

export const isIncomingVisibleCall = (call?: InboxCallRecord | null): call is InboxCallRecord => {
  if (!call) return false;

  return (
    call.direction === 'inbound' &&
    ['received', 'ringing', 'queued', 'initiated', 'answered'].includes(call.status)
  );
};

export const mergeCallRecords = (
  current: InboxCallRecord[],
  incoming: InboxCallRecord[]
): InboxCallRecord[] => {
  if (!incoming.length) return current;

  const byId = new Map(current.map((call) => [Number(call.id), call]));
  incoming.forEach((call) => {
    if (!call?.id) return;
    byId.set(Number(call.id), { ...(byId.get(Number(call.id)) ?? {}), ...call });
  });

  return Array.from(byId.values())
    .sort(
      (a, b) =>
        new Date(b.updated_at || b.created_at || 0).getTime() -
        new Date(a.updated_at || a.created_at || 0).getTime()
    )
    .slice(0, 100);
};

export const normalizeConversation = (value: any): Conversation | null => {
  if (!value || value.id == null) return null;

  const id = Number(value.id);
  const connectionId = Number(value?.connection?.id);
  if (!Number.isInteger(id) || id < 1 || !Number.isInteger(connectionId) || connectionId < 1) {
    return null;
  }

  const waId = String(value?.contact?.wa_id ?? '').trim();
  const name = String(value?.contact?.name ?? waId ?? '').trim();

  return {
    id,
    account_id: value.account_id,
    contact: {
      id: Number(value?.contact?.id ?? 0),
      slug: value?.contact?.slug ?? null,
      wa_id: waId,
      name: name || waId || 'Unknown',
      display_phone: value?.contact?.display_phone ?? null,
      email: value?.contact?.email ?? null,
      phone: value?.contact?.phone ?? null,
      company: value?.contact?.company ?? null,
      notes: value?.contact?.notes ?? null,
      status: value?.contact?.status ?? null,
      source: value?.contact?.source ?? null,
      ctwa: value?.contact?.ctwa ?? null,
      tags: Array.isArray(value?.contact?.tags) ? value.contact.tags : [],
    },
    status: String(value.status ?? 'open'),
    last_message_preview: value.last_message_preview ?? null,
    last_message_at: value.last_message_at ?? null,
    last_inbound_message_at: value.last_inbound_message_at ?? null,
    connection: {
      id: connectionId,
      name: String(value?.connection?.name ?? 'Unknown'),
      slug: value?.connection?.slug ? String(value.connection.slug) : undefined,
      calling_status: value?.connection?.calling_status ?? null,
      calling_enabled: Boolean(value?.connection?.calling_enabled ?? false),
      calling_webhook_subscribed: Boolean(value?.connection?.calling_webhook_subscribed ?? false),
    },
    assigned_to: value.assigned_to == null ? null : Number(value.assigned_to),
    priority: value.priority ?? null,
    automation_state: value.automation_state ?? null,
    automation_processing: Boolean(value.automation_processing ?? false),
    automation_processing_mode: value.automation_processing_mode ?? null,
    bot_paused: Boolean(value.bot_paused ?? false),
    bot_paused_reason: value.bot_paused_reason ?? null,
    handoff_status: value.handoff_status ?? null,
    handoff_reason: value.handoff_reason ?? null,
    unread_count: Number(value.unread_count ?? value.unread ?? 0),
    // Only set when the server sent counts, so partial realtime updates don't wipe them.
    ...(value.message_counts
      ? {
          message_counts: {
            received: Number(value.message_counts.received ?? 0),
            sent: Number(value.message_counts.sent ?? 0),
            total: Number(value.message_counts.total ?? 0),
          },
        }
      : {}),
  };
};

export const normalizeConversationList = (items: unknown): Conversation[] => {
  if (!Array.isArray(items)) return [];
  return items.map(normalizeConversation).filter((item): item is Conversation => item !== null);
};

export const contactDisplayPhone = (conversation?: Conversation | null): string => {
  if (!conversation) return '';
  return (
    conversation.contact.display_phone ||
    conversation.contact.phone ||
    conversation.contact.wa_id ||
    ''
  );
};

export const conversationSourceLabel = (conversation?: Conversation | null): string => {
  if (!conversation) return 'WhatsApp';
  if (
    conversation.contact.ctwa?.source_type ||
    conversation.contact.ctwa?.source ||
    conversation.contact.ctwa?.ctwa_clid
  ) {
    return 'Click-to-WhatsApp';
  }

  const source = String(conversation.contact.source || '').trim();
  if (!source) return 'WhatsApp';

  return source.replace(/[_-]+/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
};

export const conversationSlaLabel = (
  conversation?: Conversation | null
): { label: string; tone: 'muted' | 'ok' | 'warn' | 'danger' } => {
  if (!conversation?.last_message_at) return { label: 'No SLA', tone: 'muted' };
  if (conversation.status === 'closed') return { label: 'Resolved', tone: 'ok' };

  const timestamp = new Date(conversation.last_message_at).getTime();
  if (!Number.isFinite(timestamp)) return { label: 'No SLA', tone: 'muted' };

  const minutes = Math.max(0, Math.floor((Date.now() - timestamp) / 60000));
  if (minutes >= 1440) return { label: `${Math.floor(minutes / 1440)}d`, tone: 'danger' };
  if (minutes >= 240) return { label: `${Math.floor(minutes / 60)}h`, tone: 'warn' };
  if (minutes >= 60) return { label: `${Math.floor(minutes / 60)}h`, tone: 'ok' };
  return { label: `${Math.max(1, minutes)}m`, tone: 'ok' };
};

export const slaClasses = (tone: 'muted' | 'ok' | 'warn' | 'danger'): string => {
  const classes = {
    muted: 'bg-gray-100 text-gray-600 dark:bg-slate-800 dark:text-slate-300',
    ok: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300',
    warn: 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300',
    danger: 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300',
  };

  return classes[tone];
};

export const formatReplyWindowRemaining = (milliseconds: number): string => {
  const minutes = Math.max(1, Math.ceil(milliseconds / 60000));
  if (minutes >= 60) {
    const hours = Math.floor(minutes / 60);
    const remainder = minutes % 60;
    return remainder > 0 ? `${hours}h ${remainder}m left` : `${hours}h left`;
  }

  return `${minutes}m left`;
};

export const customerServiceWindowFor = (
  conversation?: Conversation | null,
  messages: ChatMessage[] = [],
  nowMs: number = Date.now()
): CustomerServiceWindow => {
  const latestInboundFromMessages =
    [...messages].reverse().find((message) => message.direction === 'inbound')?.created_at ?? null;
  const lastInboundAt = latestInboundFromMessages ?? conversation?.last_inbound_message_at ?? null;

  if (!lastInboundAt) {
    return {
      state: 'none',
      isOpen: false,
      label: 'Template required',
      detail: 'No recent customer message found. Send an approved template to start the chat.',
      lastInboundAt: null,
      expiresAt: null,
      tone: 'muted',
    };
  }

  const inboundTime = new Date(lastInboundAt).getTime();
  if (!Number.isFinite(inboundTime)) {
    return {
      state: 'none',
      isOpen: false,
      label: 'Template required',
      detail: 'Latest customer message time is unavailable. Use an approved template.',
      lastInboundAt,
      expiresAt: null,
      tone: 'muted',
    };
  }

  const expiresAtMs = inboundTime + 24 * 60 * 60 * 1000;
  const remainingMs = expiresAtMs - nowMs;
  const expiresAt = new Date(expiresAtMs).toISOString();

  if (remainingMs <= 0) {
    return {
      state: 'closed',
      isOpen: false,
      label: '24h window closed',
      detail:
        'You can reply freely for 24 hours after the customer\'s last message. After that, send an approved template to restart the chat.',
      lastInboundAt,
      expiresAt,
      tone: 'danger',
    };
  }

  const closingSoon = remainingMs <= 2 * 60 * 60 * 1000;

  return {
    state: closingSoon ? 'closing' : 'open',
    isOpen: true,
    label: `Reply window ${formatReplyWindowRemaining(remainingMs)}`,
    detail: `Free-form replies allowed until ${new Date(expiresAtMs).toLocaleString()}.`,
    lastInboundAt,
    expiresAt,
    tone: closingSoon ? 'warn' : 'ok',
  };
};

export const mergeLastInboundAt = (
  previous?: string | null,
  incoming?: string | null,
  message?: Pick<ChatMessage, 'direction' | 'created_at'> | null
): string | null => {
  if (message?.direction === 'inbound' && message.created_at) {
    const messageTime = new Date(message.created_at).getTime();
    const previousTime = previous ? new Date(previous).getTime() : 0;
    if (
      Number.isFinite(messageTime) &&
      messageTime >= (Number.isFinite(previousTime) ? previousTime : 0)
    ) {
      return message.created_at;
    }
  }

  if (incoming) {
    const incomingTime = new Date(incoming).getTime();
    const previousTime = previous ? new Date(previous).getTime() : 0;
    if (
      Number.isFinite(incomingTime) &&
      incomingTime >= (Number.isFinite(previousTime) ? previousTime : 0)
    ) {
      return incoming;
    }
  }

  return previous ?? null;
};

export const isUsableAiSuggestion = (value: unknown): value is string => {
  if (typeof value !== 'string') {
    return false;
  }

  const text = value.trim();
  if (text.length < 24) {
    return false;
  }

  return ![
    /\bto help me understand\s*$/i,
    /\bplease share\s*$/i,
    /\bcan you share\s*$/i,
    /\bcould you share\s*$/i,
    /\bmay i know\s*$/i,
    /\btell me more about\s*$/i,
  ].some((pattern) => pattern.test(text));
};

export const conversationModeLabel = (conversation?: Conversation | null): string => {
  if (!conversation) return 'No chat selected';
  if (conversation.automation_processing)
    return conversation.automation_processing_mode === 'ai' ? 'AI replying' : 'Bot replying';
  if (conversation.bot_paused || conversation.handoff_status === 'manual') return 'Manual mode';
  if (conversation.automation_state) return 'Automation waiting';
  return 'Bot ready';
};

export const timelineBadgeForMessage = (
  message: ChatMessage
): { label: string; icon: LucideIcon; classes: string } | null => {
  if (isCallTranscriptMessage(message)) {
    return null;
  }

  if (message.type === 'template') {
    return {
      label: 'Template sent',
      icon: FileText,
      classes: 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-200',
    };
  }

  if (message.type === 'interactive') {
    const interactiveType = String(
      message.payload?.interactive_type ?? message.payload?.interactive?.type ?? ''
    );
    const isPayment =
      interactiveType === 'payment' ||
      message.payload?.payment_link ||
      message.payload?.payment_order_id;
    const isProduct =
      interactiveType.includes('product') ||
      message.payload?.catalog_product_id ||
      message.payload?.product_id;
    const isForm = interactiveType === 'flow' || message.payload?.flow_id;

    return {
      label: isPayment
        ? 'Payment link'
        : isProduct
          ? 'Product shared'
          : isForm
            ? 'WhatsApp form'
            : interactiveType === 'list'
              ? 'List sent'
              : 'Buttons sent',
      icon: isPayment ? CreditCard : isProduct ? ShoppingBag : ClipboardList,
      classes: isPayment
        ? 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-200'
        : 'bg-purple-50 text-purple-700 dark:bg-purple-500/10 dark:text-purple-200',
    };
  }

  if (message.status === 'failed') {
    return {
      label: 'Not delivered',
      icon: AlertCircle,
      classes: 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-200',
    };
  }

  return null;
};

export const EMOJI_QUICK = ['👍', '🙏', '😊', '🎉', '✅', '❤️', '🔥', '👋'];

export const HOVER_REACTIONS = ['👍', '❤️', '😂', '🙏'];

export const QUICK_REPLIES = [
  {
    id: 'catalog',
    label: 'Send catalog',
    text: 'Hi {{name}}, I will send the latest catalog right away.',
  },
  {
    id: 'tracking',
    label: 'Tracking update',
    text: 'Your order is being checked. I will share the tracking link shortly.',
  },
  {
    id: 'callback',
    label: 'Callback',
    text: 'Can I arrange a quick call with our team to help you faster?',
  },
  {
    id: 'thanks',
    label: 'Thank you',
    text: 'Thanks for confirming. Let me know if you need anything else.',
  },
];

export const INBOX_LABELS = [
  { id: 'vip', label: 'VIP', color: 'emerald' },
  { id: 'order', label: 'Order', color: 'blue' },
  { id: 'support', label: 'Support', color: 'purple' },
  { id: 'lead', label: 'Lead', color: 'amber' },
];

export const getConversationLabels = (conversation: Conversation): string[] => {
  const labels = new Set<string>();
  const contactTagNames = (conversation.contact.tags ?? []).map((tag) => tag.name.toLowerCase());
  if (conversation.priority === 'urgent') labels.add('vip');
  if (contactTagNames.includes('vip')) labels.add('vip');
  if (
    (conversation.last_message_preview || '')
      .toLowerCase()
      .match(/order|shipping|tracking|delivery/)
  )
    labels.add('order');
  if (contactTagNames.includes('order')) labels.add('order');
  if ((conversation.last_message_preview || '').toLowerCase().match(/help|issue|problem|support/))
    labels.add('support');
  if (contactTagNames.includes('support')) labels.add('support');
  if (conversation.status === 'pending') labels.add('lead');
  if (contactTagNames.includes('lead')) labels.add('lead');
  return Array.from(labels);
};

export const labelClasses = (color: string) => {
  const classes: Record<string, string> = {
    emerald: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300',
    blue: 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-300',
    purple: 'bg-purple-50 text-purple-700 dark:bg-purple-500/10 dark:text-purple-300',
    amber: 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300',
  };
  return classes[color] ?? classes.emerald;
};

export const isCallTranscriptMessage = (message: ChatMessage): boolean =>
  message.payload?.source === 'voice_bridge' || message.payload?.voice_session_id != null;

export const callTranscriptSpeaker = (message: ChatMessage): string => {
  if (message.direction === 'inbound') return 'Caller';
  if (message.payload?.source === 'ai_agent') return 'AI agent';

  return 'Agent';
};

export const noteToTimelineMessage = (note: ConversationNote): ChatMessage => ({
  id: `note-${note.id}`,
  direction: 'inbound',
  type: 'internal_note',
  text_body: note.note,
  payload: {
    note_id: note.id,
    created_by: note.created_by,
  },
  status: 'saved',
  created_at: note.created_at,
});

export const auditToTimelineMessage = (event: ConversationAuditEvent): ChatMessage => ({
  id: `audit-${event.id}`,
  direction: 'inbound',
  type: 'automation_status',
  text_body: event.description,
  payload: {
    audit_id: event.id,
    event_type: event.event_type,
    meta: event.meta ?? {},
    actor: event.actor ?? null,
  },
  status: event.event_type,
  created_at: event.created_at,
});

export const normalizeChatMessage = (value: any): ChatMessage | null => {
  if (!value || value.id == null) return null;

  return {
    id: value.id,
    direction: value.direction === 'outbound' ? 'outbound' : 'inbound',
    type: String(value.type ?? 'text'),
    text_body: value.text_body ?? value.text ?? null,
    payload: value.payload ?? {},
    status: value.status ?? undefined,
    meta_message_id: value.meta_message_id ?? null,
    created_at: value.created_at ?? new Date().toISOString(),
    updated_at: value.updated_at ?? null,
    sent_at: value.sent_at ?? null,
    delivered_at: value.delivered_at ?? null,
    read_at: value.read_at ?? null,
    reply_to:
      value.reply_to ?? value.payload?.reply?.preview ?? value.payload?.context?.preview ?? null,
    reply_to_message_id: value.reply_to_message_id ?? value.payload?.reply?.message_id ?? null,
    reply_to_meta_message_id:
      value.reply_to_meta_message_id ??
      value.payload?.reply?.meta_message_id ??
      value.payload?.context?.id ??
      null,
    error_message: value.error_message ?? null,
  };
};

export const isRenderableMessageUpdate = (message: ChatMessage): boolean => {
  if (message.type && message.type !== 'text') return true;
  if (message.text_body) return true;
  if (message.payload && Object.keys(message.payload).length > 0) return true;

  return false;
};

export const messageTimestamp = (message: Pick<ChatMessage, 'created_at'>): number => {
  const timestamp = new Date(message.created_at).getTime();
  return Number.isFinite(timestamp) ? timestamp : 0;
};

export const sortMessages = (messages: ChatMessage[]): ChatMessage[] =>
  [...messages].sort((a, b) => messageTimestamp(a) - messageTimestamp(b));

export const isOptimisticMatch = (optimistic: ChatMessage, incoming: ChatMessage): boolean => {
  if (!String(optimistic.id).startsWith('optimistic-')) return false;
  if (String(incoming.id).startsWith('optimistic-')) return false;
  if (optimistic.direction !== incoming.direction || optimistic.type !== incoming.type)
    return false;
  if ((optimistic.text_body ?? '') !== (incoming.text_body ?? '')) return false;

  return Math.abs(messageTimestamp(optimistic) - messageTimestamp(incoming)) < 120000;
};

export const isSameProviderMessage = (existing: ChatMessage, incoming: ChatMessage): boolean => {
  if (!existing.meta_message_id || !incoming.meta_message_id) return false;
  return String(existing.meta_message_id) === String(incoming.meta_message_id);
};

export const canReactToMessage = (message: ChatMessage): boolean =>
  Boolean(message.meta_message_id) &&
  !message.optimistic &&
  !String(message.id).startsWith('note-') &&
  !['internal_note', 'reaction'].includes(message.type) &&
  !isCallTranscriptMessage(message);

export const emptyContactGallery = (): ContactGallery => ({
  media: [],
  documents: [],
  links: [],
  counts: {
    media: 0,
    documents: 0,
    links: 0,
    total: 0,
  },
});

export const messageStatusIcon = (message: ChatMessage) => {
  if (message.optimistic || message.status === 'queued') {
    return <Clock className="h-3.5 w-3.5" aria-label="Sending" />;
  }

  if (message.status === 'failed') {
    return <AlertCircle className="h-3.5 w-3.5 text-red-500" aria-label="Failed" />;
  }

  if (message.read_at || message.status === 'read') {
    return <CheckCheck className="h-3.5 w-3.5 text-sky-500" aria-label="Read" />;
  }

  if (message.delivered_at || message.status === 'delivered') {
    return <CheckCheck className="h-3.5 w-3.5" aria-label="Delivered" />;
  }

  return <Check className="h-3.5 w-3.5" aria-label="Sent" />;
};

export const formatFileSize = (bytes: number): string => {
  if (!Number.isFinite(bytes) || bytes <= 0) return '0 KB';
  if (bytes < 1024 * 1024) return `${Math.max(1, Math.round(bytes / 1024))} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};

export const AUDIO_EXTENSIONS = new Set([
  'aac',
  'm4a',
  'mp4',
  'mp3',
  'amr',
  'ogg',
  'oga',
  'wav',
  'wave',
]);

export const VIDEO_EXTENSIONS = new Set(['mp4', 'mov', '3gp', '3gpp']);

export const IMAGE_EXTENSIONS = new Set(['jpg', 'jpeg', 'png', 'gif', 'webp']);

export const fileExtension = (file: File): string => {
  const name = file.name || '';
  const parts = name.split('.');
  return parts.length > 1 ? (parts.pop() || '').toLowerCase() : '';
};

export const isRecordedVoiceFile = (file: File): boolean => file.name.startsWith('voice-message-');

export const messageReplyPreview = (message?: ChatMessage | null): string | null => {
  if (!message) return null;
  if (message.text_body) return message.text_body.slice(0, 160);

  const payload = message.payload || {};
  const filename = payload.filename || payload.document?.filename || payload.media?.filename;
  if (filename) return String(filename).slice(0, 160);

  if (message.type === 'audio') return payload.voice ? 'Voice message' : 'Audio message';
  if (message.type === 'image') return 'Image message';
  if (message.type === 'video') return 'Video message';
  if (message.type === 'document') return 'Document message';
  if (message.type === 'location') return 'Location message';

  return `${message.type || 'Message'} message`;
};

export const messageReplyMetaId = (message?: ChatMessage | null): string | null => {
  if (!message) return null;
  return (
    message.meta_message_id ||
    message.payload?.context?.id ||
    message.payload?.reply?.meta_message_id ||
    null
  );
};

export const resolveMediaType = (file: File): 'image' | 'video' | 'audio' | 'document' => {
  const mime = (file.type || '').toLowerCase();
  const extension = fileExtension(file);

  if (mime.startsWith('image/')) return 'image';
  if (mime.startsWith('audio/') || mime === 'application/ogg') return 'audio';
  if (mime.startsWith('video/')) return 'video';
  if (IMAGE_EXTENSIONS.has(extension)) return 'image';
  if (AUDIO_EXTENSIONS.has(extension) && extension !== 'mp4') return 'audio';
  if (VIDEO_EXTENSIONS.has(extension)) return 'video';

  return 'document';
};
