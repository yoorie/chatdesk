import type { ChatMessage } from './types';

/**
 * Pure helpers for what the inbox thread shows and counts.
 * Kept free of JSX so they can be unit tested with node --test.
 */

export interface MessageCounts {
  received: number;
  sent: number;
  total: number;
}

/** Timeline rows that are not customer/business messages. */
const NON_MESSAGE_TYPES = new Set(['automation_status', 'internal_note', 'reaction']);

/** Event types written by the auto-reply runner for every inbound message. */
const AUTOMATION_RUN_EVENTS = new Set([
  'automation_running',
  'ai_replying',
  'automation_completed',
  'automation_failed',
  'automation_skipped',
]);

const eventType = (message: ChatMessage): string => String(message.payload?.event_type ?? '');

export const isAutomationRunEvent = (message: ChatMessage): boolean =>
  message.type === 'automation_status' &&
  (message.payload?.meta?.status_kind === 'automation' ||
    AUTOMATION_RUN_EVENTS.has(eventType(message)));

/**
 * Friendly one-line text for an auto-reply event, or null when it should stay hidden.
 * Only outcomes the team should know about are shown: a reply went out, or it couldn't be sent.
 * "Started", "writing", and "skipped" (e.g. no AI assistant switched on) are hidden.
 */
export const automationEventLabel = (message: ChatMessage): string | null => {
  const type = eventType(message);
  if (type === 'automation_completed') {
    return message.payload?.meta?.chatbot_matched ? 'Replied automatically' : 'AI replied';
  }
  if (type === 'automation_failed') {
    return "Auto-reply couldn't be sent";
  }

  return null;
};

/**
 * Removes noisy auto-reply status rows from the thread. At most one subtle line is kept per
 * customer message, and only when something actually happened.
 */
export const visibleTimelineMessages = (messages: ChatMessage[]): ChatMessage[] => {
  const lastOutcomeIdByInbound = new Map<string, string>();
  messages.forEach((message) => {
    if (!isAutomationRunEvent(message) || automationEventLabel(message) === null) return;
    const inboundId = message.payload?.meta?.inbound_message_id;
    const key = inboundId != null ? `inbound-${inboundId}` : `event-${message.id}`;
    lastOutcomeIdByInbound.set(key, String(message.id));
  });
  const keep = new Set(lastOutcomeIdByInbound.values());

  return messages.flatMap((message) => {
    if (!isAutomationRunEvent(message)) return [message];
    if (!keep.has(String(message.id))) return [];

    return [{ ...message, text_body: automationEventLabel(message) }];
  });
};

/** Counts real messages in the loaded thread (system events, notes and reactions excluded). */
export const countLoadedMessages = (messages: ChatMessage[]): MessageCounts => {
  let received = 0;
  let sent = 0;
  messages.forEach((message) => {
    if (NON_MESSAGE_TYPES.has(message.type)) return;
    if (message.direction === 'outbound') sent += 1;
    else received += 1;
  });

  return { received, sent, total: received + sent };
};

/**
 * Server counts cover the whole chat; loaded counts include messages that arrived since the page
 * loaded. Use whichever is higher for each direction.
 */
export const resolveMessageCounts = (
  serverCounts: Partial<MessageCounts> | null | undefined,
  loaded: MessageCounts
): MessageCounts => {
  const received = Math.max(Number(serverCounts?.received ?? 0) || 0, loaded.received);
  const sent = Math.max(Number(serverCounts?.sent ?? 0) || 0, loaded.sent);

  return { received, sent, total: received + sent };
};
