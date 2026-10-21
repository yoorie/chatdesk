import { appUrl, pathIncludes } from '@/lib/appUrl';
import { useRealtime } from '@/Providers/RealtimeProvider';
import { useToast } from '@/hooks/useToast';
import { router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef } from 'react';

type MessageEvent = {
  conversation_id?: number;
  message?: {
    id?: number | string;
    direction?: string;
    type?: string;
    text_body?: string | null;
  };
  contact?: {
    name?: string | null;
    wa_id?: string | null;
  } | null;
};

function isQuietHours(user: any): boolean {
  if (!user?.quiet_hours_enabled) return false;
  const start = String(user.quiet_hours_start || '22:00');
  const end = String(user.quiet_hours_end || '08:00');
  const now = new Date();
  const current = now.getHours() * 60 + now.getMinutes();
  const [startHour, startMinute] = start.split(':').map((value) => Number(value || 0));
  const [endHour, endMinute] = end.split(':').map((value) => Number(value || 0));
  const startMinutes = startHour * 60 + startMinute;
  const endMinutes = endHour * 60 + endMinute;

  if (startMinutes === endMinutes) return false;

  return startMinutes < endMinutes
    ? current >= startMinutes && current < endMinutes
    : current >= startMinutes || current < endMinutes;
}

function playTone(frequency = 880, duration = 0.12, volume = 0.04) {
  const AudioContextClass = (window as any).AudioContext || (window as any).webkitAudioContext;
  if (!AudioContextClass) return;

  const context = new AudioContextClass();
  const gain = context.createGain();
  gain.gain.value = Math.max(volume, 0.12);
  gain.connect(context.destination);
  [0, 0.16].forEach((offset, index) => {
    const oscillator = context.createOscillator();
    oscillator.type = 'sine';
    oscillator.frequency.value = index === 0 ? frequency : 1174;
    oscillator.connect(gain);
    oscillator.start(context.currentTime + offset);
    oscillator.stop(context.currentTime + offset + duration);
  });
  window.setTimeout(() => void context.close?.(), Math.ceil((duration + 0.2) * 1000) + 80);
}

export default function RealtimeInboxAlerts() {
  const { account, auth } = usePage().props as any;
  const { subscribe } = useRealtime();
  const { addToast } = useToast();
  const processedEvents = useRef<Set<string>>(new Set());
  const user = auth?.user;
  const quiet = isQuietHours(user);
  const soundEnabled = Boolean(user?.notify_sound_enabled ?? true) && !quiet;
  const inAppEnabled = Boolean(user?.notify_in_app_enabled ?? true);
  const browserNotificationsEnabled =
    typeof window !== 'undefined' &&
    'Notification' in window &&
    Notification.permission === 'granted';
  const isInboxPage =
    typeof window !== 'undefined' && pathIncludes(window.location.pathname, '/app/conversations');

  const trimProcessedEvents = useCallback(() => {
    if (processedEvents.current.size <= 160) return;
    processedEvents.current = new Set(Array.from(processedEvents.current).slice(-80));
  }, []);

  const showBrowserNotification = useCallback(
    (tag: string, title: string, body: string, conversationId?: number) => {
      if (!browserNotificationsEnabled) return;

      const notification = new Notification(title, {
        body,
        tag,
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
    [browserNotificationsEnabled]
  );

  const notifyMessage = useCallback(
    (event: MessageEvent) => {
      const message = event.message;
      if (!message || message.direction !== 'inbound') return;
      const eventId = `global-message-${event.conversation_id || 'x'}-${message.id || Date.now()}`;
      if (processedEvents.current.has(eventId)) return;
      processedEvents.current.add(eventId);
      trimProcessedEvents();

      const contact = event.contact?.name || event.contact?.wa_id || 'WhatsApp contact';
      const preview = message.text_body || message.type || 'New WhatsApp message';
      if (inAppEnabled) {
        addToast({
          title: 'New WhatsApp message',
          description: `${contact}: ${preview}`,
          variant: 'info',
          duration: 3500,
          source: 'realtime-inbox',
        });
      }
      if (soundEnabled) {
        playTone();
      }
      showBrowserNotification(
        eventId,
        `New message from ${contact}`,
        preview,
        event.conversation_id
      );
    },
    [addToast, inAppEnabled, showBrowserNotification, soundEnabled, trimProcessedEvents]
  );

  const channel = useMemo(
    () => (account?.id ? `account.${account.id}.whatsapp.inbox` : null),
    [account?.id]
  );

  useEffect(() => {
    if (!channel || isInboxPage) return;

    const unsubscribeMessage = subscribe(channel, '.whatsapp.message.created', notifyMessage);

    return () => {
      unsubscribeMessage();
    };
  }, [channel, isInboxPage, notifyMessage, subscribe]);

  return null;
}
