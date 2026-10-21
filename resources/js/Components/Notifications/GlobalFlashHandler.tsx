import { useToast } from '@/hooks/useToast';
import { usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

/**
 * GlobalFlashHandler Component
 *
 * Automatically converts Laravel flash messages and shared errors to toast notifications.
 * Flash is provided via HandleInertiaRequests shared props; errors come from redirect()->withErrors().
 */
export function GlobalFlashHandler() {
  const { flash, errors } = usePage().props as {
    flash?: FlashProps;
    errors?: Record<string, string>;
  };
  const { addToast } = useToast();
  const lastPayloadRef = useRef<string>('');

  useEffect(() => {
    const payloadSignature = JSON.stringify({
      flash: flash ?? null,
      errors: errors ?? null,
    });

    // Inertia can re-render with the same flash payload; avoid duplicate toasts.
    if (lastPayloadRef.current === payloadSignature) {
      return;
    }
    lastPayloadRef.current = payloadSignature;

    const emitted = new Set<string>();
    const emit = (
      title: string,
      description: string,
      variant: 'success' | 'error' | 'warning' | 'info'
    ) => {
      const key = `${variant}|${title}|${description}`;
      if (emitted.has(key)) {
        return;
      }
      emitted.add(key);
      addToast({ title, description, variant, source: 'flash' });
    };

    // Flash messages (redirect()->with('success', ...) etc.)
    if (flash) {
      if (flash.success) {
        emit('Done', flash.success, 'success');
      }
      if (flash.error) {
        emit("Couldn't complete that", flash.error, 'error');
      }
      if (flash.warning) {
        emit('Heads up', flash.warning, 'warning');
      }
      if (flash.info) {
        emit('Good to know', flash.info, 'info');
      }
      if (flash.status) {
        const statusMessages: Record<
          string,
          { title: string; variant: 'success' | 'error' | 'warning' | 'info' }
        > = {
          'verification-link-sent': { title: 'Verification email sent', variant: 'success' },
          'password-updated': { title: 'Password updated', variant: 'success' },
          'profile-updated': { title: 'Profile updated', variant: 'success' },
        };
        const statusDescriptions: Record<string, string> = {
          'verification-link-sent': 'Check your inbox for a new verification link.',
          'password-updated': 'Your new password is saved.',
          'profile-updated': 'Your changes are saved.',
        };
        const config = statusMessages[flash.status] || {
          title: 'Good to know',
          variant: 'info' as const,
        };
        // Unknown status keys look like "some-status-key"; show them as words.
        const description =
          statusDescriptions[flash.status] ||
          (/^[a-z0-9]+(?:[-_][a-z0-9]+)+$/.test(flash.status)
            ? flash.status.replace(/[-_]+/g, ' ').replace(/^./, (c) => c.toUpperCase()) + '.'
            : flash.status);
        emit(config.title, description, config.variant);
      }
    }

    // Validation/redirect errors (redirect()->withErrors([...]))
    if (errors && typeof errors === 'object' && Object.keys(errors).length > 0) {
      const messages = Object.entries(errors)
        .map(([, value]) =>
          typeof value === 'string' ? value : Array.isArray(value) ? value[0] : String(value)
        )
        .filter(Boolean);
      const description = messages.length === 1 ? messages[0] : messages.slice(0, 3).join(' • ');
      const flashError = (flash?.error ?? '').trim().toLowerCase();
      if (!flashError || flashError !== description.trim().toLowerCase()) {
        emit('Please check the form', description, 'error');
      }
    }
  }, [flash, errors, addToast]);

  return null;
}

interface FlashProps {
  success?: string;
  error?: string;
  warning?: string;
  info?: string;
  status?: string;
}
