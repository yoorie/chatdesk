import { friendlyError, friendlyErrorAction } from '@/lib/friendly';
import { useCallback, useEffect, useState } from 'react';

export interface Toast {
  id: string;
  title: string;
  description?: string;
  variant?: 'success' | 'error' | 'warning' | 'info';
  duration?: number;
  source?: string;
  /** Optional next-step button shown inside the toast. */
  action?: { label: string; href: string };
}

let toastIdCounter = 0;
const toastListeners: Set<(toasts: Toast[]) => void> = new Set();
let toasts: Toast[] = [];
const recentToastSignatures: Map<string, { id: string; at: number }> = new Map();
const TOAST_DEDUPE_WINDOW_MS = 6000;
const TOAST_EVENT_DEDUPE_WINDOW_MS = 10000;
const FLASH_AFTER_LOCAL_SUPPRESS_MS = 3500;
const GENERIC_TITLES = new Set(['success', 'error', 'warning', 'info', 'status']);
const recentEventSignatures: Map<string, number> = new Map();
const recentLocalVariantAt: Map<string, number> = new Map();

function normalizeToastMessage(toast: Omit<Toast, 'id'>): string {
  const normalize = (value: string): string =>
    value
      .trim()
      .toLowerCase()
      .replace(/\b(successfully|successful|success)\b/g, '')
      .replace(/\bfailed to\b/g, '')
      .replace(/\bunable to\b/g, '')
      .replace(/[.!?]+$/g, '')
      .replace(/\s+/g, ' ')
      .trim();
  const title = normalize(toast.title || '');
  const description = normalize(toast.description || '');

  if (!title && !description) {
    return '';
  }

  // If title is generic and description contains the actual message, use description as canonical text.
  if (description && GENERIC_TITLES.has(title.toLowerCase())) {
    return description.toLowerCase();
  }

  // If both exist, include both for semantic uniqueness.
  if (title && description) {
    return `${title} | ${description}`.toLowerCase();
  }

  return description || title;
}

export function useToast() {
  const [localToasts, setLocalToasts] = useState<Toast[]>(toasts);

  const removeToast = useCallback((id: string) => {
    toasts = toasts.filter((t) => t.id !== id);
    toastListeners.forEach((listener) => listener(toasts));
    setLocalToasts(toasts);
  }, []);

  const addToast = useCallback(
    (rawToast: Omit<Toast, 'id'>) => {
      const now = Date.now();
      const variant = rawToast.variant || 'info';
      // Never show raw provider/system errors to customers.
      const toast: Omit<Toast, 'id'> =
        variant === 'error' || variant === 'warning'
          ? (() => {
              const description = rawToast.description
                ? friendlyError(rawToast.description)
                : rawToast.description;
              const title = rawToast.description
                ? rawToast.title
                : friendlyError(rawToast.title, rawToast.title);
              return {
                ...rawToast,
                title,
                description,
                action:
                  rawToast.action ??
                  friendlyErrorAction(description || title) ??
                  undefined,
                duration: rawToast.duration ?? (variant === 'error' ? 8000 : undefined),
              };
            })()
          : rawToast;

      if (toast.source === 'flash') {
        const recentLocalAt = recentLocalVariantAt.get(variant);
        if (recentLocalAt && now - recentLocalAt < FLASH_AFTER_LOCAL_SUPPRESS_MS) {
          return `guarded-flash-${variant}`;
        }
      } else {
        recentLocalVariantAt.set(variant, now);
        if (recentLocalVariantAt.size > 20) {
          for (const [key, at] of recentLocalVariantAt.entries()) {
            if (now - at > FLASH_AFTER_LOCAL_SUPPRESS_MS * 2) {
              recentLocalVariantAt.delete(key);
            }
          }
        }
      }

      const canonicalMessage = normalizeToastMessage(toast);
      const signature =
        canonicalMessage || `${variant}|${toast.title || ''}|${toast.description || ''}`;
      const eventSignature = `${variant}|${signature}`;

      // Global duplicate-toast guard middleware (event-level)
      // Prevents duplicate toasts from mixed sources (flash + local handlers).
      const eventSeenAt = recentEventSignatures.get(eventSignature);
      if (eventSeenAt && now - eventSeenAt < TOAST_EVENT_DEDUPE_WINDOW_MS) {
        return `guarded-${eventSignature}`;
      }
      recentEventSignatures.set(eventSignature, now);

      if (recentEventSignatures.size > 400) {
        for (const [key, at] of recentEventSignatures.entries()) {
          if (now - at > TOAST_EVENT_DEDUPE_WINDOW_MS * 2) {
            recentEventSignatures.delete(key);
          }
        }
      }

      const recent = recentToastSignatures.get(signature);
      if (recent && now - recent.at < TOAST_DEDUPE_WINDOW_MS) {
        return recent.id;
      }

      // Keep dedupe map bounded.
      if (recentToastSignatures.size > 200) {
        for (const [key, value] of recentToastSignatures.entries()) {
          if (now - value.at > TOAST_DEDUPE_WINDOW_MS * 2) {
            recentToastSignatures.delete(key);
          }
        }
      }

      const id = `toast-${++toastIdCounter}`;
      const newToast: Toast = {
        id,
        duration: 5000,
        ...toast,
      };

      toasts = [...toasts, newToast];
      recentToastSignatures.set(signature, { id, at: now });
      toastListeners.forEach((listener) => listener(toasts));
      setLocalToasts(toasts);

      if (newToast.duration && newToast.duration > 0) {
        setTimeout(() => {
          removeToast(id);
        }, newToast.duration);
      }

      return id;
    },
    [removeToast]
  );

  const toast = {
    success: (message: string, description?: string) => {
      addToast({ title: message, description, variant: 'success' });
    },
    error: (message: string, description?: string) => {
      addToast({ title: message, description, variant: 'error' });
    },
    warning: (message: string, description?: string) => {
      addToast({ title: message, description, variant: 'warning' });
    },
    info: (message: string, description?: string) => {
      addToast({ title: message, description, variant: 'info' });
    },
  };

  // Subscribe to global toast updates
  useEffect(() => {
    const listener = (newToasts: Toast[]) => {
      setLocalToasts(newToasts);
    };
    toastListeners.add(listener);
    setLocalToasts(toasts);
    return () => {
      toastListeners.delete(listener);
    };
  }, []);

  return {
    toasts: localToasts,
    addToast,
    removeToast,
    toast,
  };
}
