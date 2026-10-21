export interface Agent {
  id: number;
  name: string;
  email?: string;
  role?: string;
}

export interface AiAgentOption {
  id: number;
  name: string;
  avatar?: string | null;
  role?: string;
  tone?: string;
  mode?: 'suggest' | 'approval' | 'autopilot';
}

export interface Conversation {
  id: number;
  account_id?: number | string;
  contact: {
    id: number;
    slug?: string | null;
    wa_id: string;
    name: string;
    display_phone?: string | null;
    email?: string | null;
    phone?: string | null;
    company?: string | null;
    notes?: string | null;
    status?: string | null;
    source?: string | null;
    ctwa?: {
      source?: string | null;
      source_type?: string | null;
      source_id?: string | null;
      source_url?: string | null;
      ctwa_clid?: string | null;
      headline?: string | null;
      body?: string | null;
      media_type?: string | null;
      captured_at?: string | null;
    } | null;
    tags?: Array<{ id: number; name: string; color?: string | null }>;
  };
  status: string;
  last_message_preview: string | null;
  last_message_at: string | null;
  last_inbound_message_at?: string | null;
  connection: {
    id: number;
    name: string;
    slug?: string;
    calling_status?: string | null;
    calling_enabled?: boolean;
    calling_webhook_subscribed?: boolean;
  };
  assigned_to?: number | null;
  priority?: string | null;
  automation_state?: AutomationState | null;
  automation_processing?: boolean;
  automation_processing_mode?: string | null;
  bot_paused?: boolean;
  bot_paused_reason?: string | null;
  handoff_status?: string | null;
  handoff_reason?: string | null;
  unread_count?: number;
  /** Whole-chat message totals from the server (reactions excluded). */
  message_counts?: { received: number; sent: number; total: number };
}

export interface AutomationState {
  bot_id?: number | null;
  flow_id: number;
  flow_name: string;
  waiting_node_id?: number | null;
  waiting_node_label?: string | null;
  status?: string;
  invalid_replies?: number;
  updated_at?: string | null;
}

export interface ChatMessage {
  id: number | string;
  direction: 'inbound' | 'outbound';
  type: string;
  text_body: string | null;
  payload?: any;
  status?: string;
  meta_message_id?: string | null;
  created_at: string;
  updated_at?: string | null;
  sent_at?: string | null;
  delivered_at?: string | null;
  read_at?: string | null;
  optimistic?: boolean;
  reply_to?: string | null;
  reply_to_message_id?: number | string | null;
  reply_to_meta_message_id?: string | null;
  /** Plain-language reason a message failed (never a raw WhatsApp error). */
  error_message?: string | null;
}

export interface ConversationNote {
  id: number;
  note: string;
  created_at: string;
  created_by?: {
    id: number;
    name: string;
    email?: string;
  } | null;
}

export interface ConversationAuditEvent {
  id: number;
  event_type: string;
  description: string;
  meta?: any;
  created_at: string;
  actor?: {
    id: number;
    name: string;
    email?: string;
  } | null;
}

export interface InboxCallRecord {
  id: number;
  whatsapp_connection_id?: number | null;
  direction: string;
  phone_number?: string | null;
  contact_name?: string | null;
  status: string;
  route_mode?: string | null;
  routed_to?: string | null;
  provider_call_id?: string | null;
  duration_seconds?: number;
  summary?: string | null;
  metadata?: Record<string, any> | null;
  agent?: { id: number; name: string } | null;
  created_at?: string | null;
  updated_at?: string | null;
}

export interface InboxCallingSettings {
  enabled: boolean;
  routing_mode?: string;
  whatsapp_connection_id?: number | string | null;
  calling_eligibility_status?: string | null;
  calling_webhook_subscribed?: boolean;
  default_agent_id?: number | null;
  outbound_enabled?: boolean;
  transfer_number?: string | null;
  setup_url?: string;
}

export interface TemplateOption {
  id: number;
  name: string;
  language?: string;
  body_text?: string | null;
  variable_count?: number;
}

export interface CatalogProductOption {
  id: number;
  name: string;
  sku?: string | null;
  catalog_id?: string | null;
  retailer_id?: string | null;
  price?: number | null;
  currency?: string | null;
  image_url?: string | null;
  source?: string | null;
}

export interface SavedButtonOption {
  id: number;
  type?: string | null;
  label: string;
  shortcut?: string | null;
  message?: string | null;
  button_text?: string | null;
}

export interface SavedListOption {
  id: number;
  connection_id?: number | null;
  name: string;
  button_text?: string | null;
  description?: string | null;
  footer_text?: string | null;
  sections?: any[];
}

export interface SavedFormOption {
  id: number;
  connection_id?: number | null;
  meta_flow_id: string;
  name: string;
  status?: string | null;
  category?: string | null;
}

export type InteractiveMode =
  | 'buttons'
  | 'list'
  | 'form'
  | 'product'
  | 'link'
  | 'payment'
  | 'contact';

export interface CustomerServiceWindow {
  state: 'open' | 'closing' | 'closed' | 'none';
  isOpen: boolean;
  label: string;
  detail: string;
  lastInboundAt: string | null;
  expiresAt: string | null;
  tone: 'muted' | 'ok' | 'warn' | 'danger';
}

export interface MediaPreview {
  type: string;
  url: string;
  title: string;
  filename?: string;
}

export interface GalleryItem {
  id: number | string;
  message_id?: number | string;
  direction?: 'inbound' | 'outbound';
  type: string;
  url?: string | null;
  title?: string | null;
  filename?: string | null;
  mime_type?: string | null;
  file_size?: number | null;
  caption?: string | null;
  created_at?: string | null;
}

export interface ContactGallery {
  media: GalleryItem[];
  documents: GalleryItem[];
  links: GalleryItem[];
  counts: {
    media: number;
    documents: number;
    links: number;
    total: number;
  };
}

export type LocationMapState = {
  lat: number;
  lng: number;
  zoom: number;
};

export type LocationSearchResult = {
  label: string;
  lat: number;
  lng: number;
};
