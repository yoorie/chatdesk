import { appUrl } from '@/lib/appUrl';
import { brandName } from './brand';
/**
 * Customer-facing wording helpers.
 *
 * The backend stores raw enum values ("past_due", "pending_review", "APPROVED")
 * and Meta/Graph returns technical error strings. Customers should never see
 * either, so every badge, toast and error line in the workspace app should go
 * through these helpers.
 */

export type FriendlyTone = 'success' | 'warning' | 'danger' | 'info' | 'default';

const STATUS_LABELS: Record<string, string> = {
  // Generic lifecycle
  active: 'Active',
  inactive: 'Off',
  enabled: 'On',
  disabled: 'Off',
  draft: 'Draft',
  created: 'Draft',
  new: 'New',
  archived: 'Archived',
  deleted: 'Deleted',
  open: 'Open',
  closed: 'Closed',
  resolved: 'Resolved',
  assigned: 'Assigned',
  unassigned: 'Unassigned',
  snoozed: 'Snoozed',
  pending: 'Pending',
  processing: 'In progress',
  in_progress: 'In progress',
  running: 'Running',
  queued: 'Queued',
  scheduled: 'Scheduled',
  sending: 'Sending',
  paused: 'Paused',
  completed: 'Completed',
  complete: 'Completed',
  done: 'Done',
  cancelled: 'Cancelled',
  canceled: 'Cancelled',
  failed: 'Failed',
  error: 'Needs attention',
  partial: 'Partly sent',
  partially_completed: 'Partly sent',
  // Messages
  sent: 'Sent',
  delivered: 'Delivered',
  read: 'Read',
  received: 'Received',
  accepted: 'Sent',
  undelivered: 'Not delivered',
  // Templates (Meta review)
  approved: 'Approved',
  rejected: 'Rejected',
  pending_review: 'In review',
  in_appeal: 'In review',
  under_review: 'In review',
  submitted: 'In review',
  paused_by_meta: 'Paused',
  limit_exceeded: 'Limit reached',
  pending_deletion: 'Being removed',
  flagged: 'Flagged',
  reinstated: 'Approved',
  // Connections / integrations
  connected: 'Connected',
  disconnected: 'Not connected',
  not_connected: 'Not connected',
  configured: 'Connected',
  healthy: 'Working',
  ok: 'Working',
  degraded: 'Needs attention',
  unhealthy: 'Needs attention',
  expired: 'Expired',
  verified: 'Verified',
  not_verified: 'Not verified',
  unverified: 'Not verified',
  restricted: 'Limited',
  banned: 'Blocked',
  flagged_number: 'Flagged',
  // Quality ratings
  green: 'High quality',
  yellow: 'Medium quality',
  red: 'Low quality',
  high: 'High',
  medium: 'Medium',
  low: 'Low',
  unknown: 'Not available yet',
  // Billing / subscription
  trial: 'Free trial',
  trialing: 'Free trial',
  past_due: 'Payment overdue',
  unpaid: 'Payment overdue',
  grace: 'Payment overdue',
  suspended: 'Paused',
  paid: 'Paid',
  refunded: 'Refunded',
  void: 'Cancelled',
  voided: 'Cancelled',
  awaiting_payment: 'Waiting for payment',
  awaiting_approval: 'Being verified',
  pending_approval: 'Being verified',
  // Flows
  published: 'Published',
  deprecated: 'Retired',
  blocked: 'Blocked',
  throttled: 'Slowed down',
  // Calls
  ringing: 'Ringing',
  answered: 'Answered',
  missed: 'Missed',
  ended: 'Ended',
  terminated: 'Ended',
  // Team
  invited: 'Invited',
  owner: 'Owner',
  admin: 'Admin',
  member: 'Agent',
};

const STATUS_TONES: Record<string, FriendlyTone> = {
  active: 'success',
  enabled: 'success',
  approved: 'success',
  reinstated: 'success',
  connected: 'success',
  configured: 'success',
  healthy: 'success',
  ok: 'success',
  completed: 'success',
  complete: 'success',
  done: 'success',
  delivered: 'success',
  read: 'success',
  paid: 'success',
  verified: 'success',
  published: 'success',
  open: 'success',
  green: 'success',
  high: 'success',
  answered: 'success',
  pending: 'warning',
  pending_review: 'warning',
  in_appeal: 'warning',
  under_review: 'warning',
  submitted: 'warning',
  processing: 'info',
  in_progress: 'info',
  running: 'info',
  queued: 'info',
  scheduled: 'info',
  sending: 'info',
  sent: 'info',
  trial: 'info',
  trialing: 'info',
  paused: 'warning',
  paused_by_meta: 'warning',
  snoozed: 'warning',
  partial: 'warning',
  partially_completed: 'warning',
  degraded: 'warning',
  yellow: 'warning',
  medium: 'warning',
  awaiting_payment: 'warning',
  awaiting_approval: 'warning',
  pending_approval: 'warning',
  restricted: 'warning',
  limit_exceeded: 'warning',
  invited: 'warning',
  failed: 'danger',
  error: 'danger',
  rejected: 'danger',
  undelivered: 'danger',
  unhealthy: 'danger',
  expired: 'danger',
  past_due: 'danger',
  unpaid: 'danger',
  banned: 'danger',
  blocked: 'danger',
  flagged: 'danger',
  red: 'danger',
  low: 'danger',
  missed: 'danger',
};

function normalizeKey(value: unknown): string {
  return String(value ?? '')
    .trim()
    .toLowerCase()
    .replace(/[\s-]+/g, '_');
}

/** Turn a raw enum ("pending_review", "APPROVED", "past-due") into a short label. */
export function friendlyStatus(value: unknown, fallback = '—'): string {
  const key = normalizeKey(value);
  if (!key) {
    return fallback;
  }

  if (STATUS_LABELS[key]) {
    return STATUS_LABELS[key];
  }

  // Unknown value: make it readable rather than showing snake_case.
  const words = key.replace(/_/g, ' ');
  return words.charAt(0).toUpperCase() + words.slice(1);
}

export function friendlyStatusTone(value: unknown): FriendlyTone {
  return STATUS_TONES[normalizeKey(value)] ?? 'default';
}

/** Map well-known Meta (WhatsApp Cloud / Graph) error codes to plain language. */
const META_ERROR_CODES: Record<string, string> = {
  '0': 'WhatsApp could not confirm your login. Reconnect your WhatsApp account.',
  '3': 'Your WhatsApp account does not have permission for this action yet.',
  '4': 'Too many requests right now. Please wait a minute and try again.',
  '10': `Your WhatsApp account has not given ${brandName()} permission for this action. Reconnect your WhatsApp account.`,
  '100': 'Some details were not accepted by WhatsApp. Please check them and try again.',
  '190': 'Your WhatsApp connection has expired. Reconnect your WhatsApp account to continue.',
  '200': `Your WhatsApp account has not given ${brandName()} permission for this action. Reconnect your WhatsApp account.`,
  '368': 'WhatsApp has temporarily limited this number for policy reasons.',
  '80007': 'Too many requests right now. Please wait a minute and try again.',
  '130429': `You are sending messages too quickly. ${brandName()} will slow down; please try again shortly.`,
  '130472':
    'This customer is part of a WhatsApp experiment and did not receive the marketing message.',
  '130497': 'WhatsApp does not allow this type of message to be sent to this country.',
  '131000': 'Something went wrong on WhatsApp. Please try again.',
  '131005': 'Your WhatsApp account does not have permission for this action.',
  '131008': 'A required field is missing. Please check the message and try again.',
  '131009': 'One of the values in this message is not valid. Please check it and try again.',
  '131016': 'WhatsApp is temporarily unavailable. Please try again in a few minutes.',
  '131021': 'You cannot send a message to your own business number.',
  '131026':
    'This message could not be delivered. The number may not use WhatsApp or may need to update the app.',
  '131031': 'Your WhatsApp account has been locked by Meta. Check Meta Business Suite for details.',
  '131037': 'Your WhatsApp display name needs approval before you can send messages.',
  '131042':
    'There is a payment problem on your Meta account. Add or update a payment method in Meta Business Suite.',
  '131045':
    'Your business phone number needs to be registered again. Reconnect your WhatsApp account.',
  '131047':
    'More than 24 hours have passed since the customer last replied. Send an approved template to restart the chat.',
  '131048': 'Too many of your messages were reported as spam, so sending is limited for now.',
  '131049':
    'WhatsApp held back this marketing message to keep a healthy experience for the customer. Try again later.',
  '131050': 'This customer has stopped receiving marketing messages from you.',
  '131051': 'This message type is not supported.',
  '131052': 'The media file could not be downloaded. Please ask the customer to send it again.',
  '131053': 'The media file could not be uploaded. Check the file type and size and try again.',
  '131056':
    'Too many messages were sent to this customer in a short time. Please wait and try again.',
  '131057': 'Your WhatsApp account is under maintenance. Please try again later.',
  '132000':
    'The template variables do not match the approved template. Check the values and try again.',
  '132001': 'This template is not available in the selected language, or is not approved yet.',
  '132005':
    'The message is too long once the variables are filled in. Shorten the values and try again.',
  '132007': 'This template breaks a WhatsApp content rule. Edit it and submit it again.',
  '132012': 'The template variables are in the wrong format. Check the values and try again.',
  '132015':
    'This template has been paused by WhatsApp because of low quality. Edit it or use another template.',
  '132016': 'This template has been disabled by WhatsApp. Use another template.',
  '132068': 'This WhatsApp Flow is blocked. Check it in WhatsApp Flows.',
  '132069': 'This WhatsApp Flow is being slowed down by WhatsApp. Please try again later.',
  '133000': 'Your phone number registration is not complete. Reconnect your WhatsApp account.',
  '133004': 'WhatsApp is temporarily unavailable. Please try again in a few minutes.',
  '133005': 'The PIN for this phone number is incorrect.',
  '133010':
    'This phone number is not registered with WhatsApp yet. Reconnect your WhatsApp account.',
  '135000': 'Something went wrong on WhatsApp. Please try again.',
  '2388093': 'This template name is already in use in this language. Pick a different name.',
  '2388024': 'A template with this name already exists. Pick a different name.',
  '2388040': 'A template field is too long. Shorten it and try again.',
  '2388047': 'The template header is not in the right format.',
  '2388072': 'The template body is not in the right format.',
  '2388073': 'The template footer is not in the right format.',
  '2388299': 'Template variables cannot be at the very start or end of the message.',
  '2494102': 'The media file is not valid. Try a different file.',
};

const RAW_PATTERNS: Array<[RegExp, string]> = [
  [
    /24.?hour|re-?engagement|outside.*(customer service|session) window/i,
    META_ERROR_CODES['131047'],
  ],
  [
    /access token|session has expired|error validating|oauth|token.*(expired|invalid)/i,
    META_ERROR_CODES['190'],
  ],
  [
    /(permission|permissions).*(denied|missing|not granted)|does not have permission/i,
    META_ERROR_CODES['10'],
  ],
  [/rate limit|too many (calls|requests)|throttl/i, META_ERROR_CODES['4']],
  [
    /not a valid whatsapp|recipient.*(not|isn't).*whatsapp|undeliverable/i,
    META_ERROR_CODES['131026'],
  ],
  [
    /template.*(does not exist|not found)|template name does not exist/i,
    META_ERROR_CODES['132001'],
  ],
  [
    /param(eter)?s?.*(mismatch|does not match)|number of (localizable_)?params/i,
    META_ERROR_CODES['132000'],
  ],
  [
    /curl error|could not resolve host|connection (timed out|refused)|timeout/i,
    'We could not reach WhatsApp just now. Please check your connection and try again.',
  ],
  [
    /sqlstate|exception|stack trace|undefined (index|offset|array key)|call to (a )?member function/i,
    'Something went wrong on our side. Please try again, or contact support if it keeps happening.',
  ],
];

/** Strings that look like raw provider/system output rather than a sentence for people. */
function looksTechnical(message: string): boolean {
  return (
    /\(#\d+\)/.test(message) ||
    /\b(OAuthException|GraphMethodException|Graph API|graph\.facebook|fbtrace_id|error_subcode|error_data)\b/i.test(
      message
    ) ||
    /^\s*[{[]/.test(message) ||
    /\b(phone_number_id|waba_id|business_account_id|access_token)\b|wamid\./i.test(message) ||
    /\b(SQLSTATE|Exception|Stack trace)\b/.test(message)
  );
}

function extractMetaCode(message: string): string | null {
  const patterns = [
    /\(#(\d{1,8})\)/,
    /"code"\s*:\s*(\d{1,8})/,
    /\bcode[\s:=]+(\d{1,8})\b/i,
    /\berror(?:\s+code)?\s*[:#]?\s*(\d{3,8})\b/i,
  ];

  for (const pattern of patterns) {
    const match = message.match(pattern);
    if (match && META_ERROR_CODES[match[1]]) {
      return match[1];
    }
  }

  return null;
}

/**
 * Turn any error (Meta/Graph string, exception text, error code, object) into
 * a short sentence a customer can act on. Plain sentences pass through.
 */
export function friendlyError(
  error: unknown,
  fallback = 'Something went wrong. Please try again.'
): string {
  if (error === null || error === undefined || error === '') {
    return fallback;
  }

  if (typeof error === 'number') {
    return META_ERROR_CODES[String(error)] ?? fallback;
  }

  if (typeof error === 'object') {
    const candidate = error as Record<string, any>;
    const code = candidate.code ?? candidate.error_code ?? candidate.error?.code;
    if (code !== undefined && META_ERROR_CODES[String(code)]) {
      return META_ERROR_CODES[String(code)];
    }
    const nested =
      candidate.error_user_msg ??
      candidate.error?.error_user_msg ??
      candidate.message ??
      candidate.title ??
      candidate.error?.message ??
      candidate.error;
    return typeof nested === 'string' || typeof nested === 'number'
      ? friendlyError(nested, fallback)
      : fallback;
  }

  const message = String(error).trim();
  if (/^\d{1,8}$/.test(message)) {
    return META_ERROR_CODES[message] ?? fallback;
  }

  const code = extractMetaCode(message);
  if (code) {
    return META_ERROR_CODES[code];
  }

  for (const [pattern, friendly] of RAW_PATTERNS) {
    if (pattern.test(message)) {
      return friendly;
    }
  }

  if (looksTechnical(message)) {
    return fallback;
  }

  return message;
}

export interface FriendlyAction {
  label: string;
  href: string;
}

/** Suggest a next step for an error message that has already been made friendly. */
export function friendlyErrorAction(message: string | null | undefined): FriendlyAction | null {
  const text = String(message ?? '');
  if (!text) {
    return null;
  }

  if (
    /reconnect your whatsapp account|connect whatsapp|whatsapp account (is )?not connected/i.test(
      text
    )
  ) {
    return { label: 'Open WhatsApp account', href: appUrl('/app/connections') };
  }
  if (/approved template|send a template/i.test(text)) {
    return { label: 'Open templates', href: appUrl('/app/templates') };
  }

  return null;
}
