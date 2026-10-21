import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

declare global {
  interface Window {
    Pusher: typeof Pusher;
    Echo: Echo<any> | null;
  }
}

let echoInstance: Echo<any> | null = null;
const realtimeDebug = import.meta.env.VITE_REALTIME_DEBUG === 'true';

/**
 * Create the shared Echo client. Never throws: realtime is an enhancement, so a bad Pusher
 * config or a failing socket must not break the first render. Returns null when unavailable.
 */
export function initializeEcho(config: {
  pusherKey: string;
  pusherCluster: string;
  authEndpoint: string;
}): Echo<any> | null {
  if (echoInstance) {
    return echoInstance;
  }

  try {
    return createEcho(config);
  } catch (error) {
    if (realtimeDebug) {
      console.error('[Echo] Failed to initialize realtime', error);
    }
    // Tear down a half-initialised client (disconnectEcho never throws).
    disconnectEcho();
    return null;
  }
}

function createEcho(config: {
  pusherKey: string;
  pusherCluster: string;
  authEndpoint: string;
}): Echo<any> {
  if (!config.pusherKey?.trim()) {
    throw new Error('Pusher key is missing.');
  }

  // Make Pusher available globally
  window.Pusher = Pusher;

  // Helper function to get current CSRF token (reads from meta tag which is updated by bootstrap.ts)
  const getCsrfToken = () => {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  };

  const getXsrfToken = () => {
    return (
      document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='))
        ?.split('=')[1] || ''
    );
  };

  const cluster = config.pusherCluster?.trim();
  if (!cluster || cluster === '') {
    throw new Error('Pusher cluster is missing. Set a cluster or provide a custom host.');
  }

  const options: any = {
    broadcaster: 'pusher',
    key: config.pusherKey,
    authEndpoint: config.authEndpoint,
    cluster,
    auth: {
      headers: {
        'X-CSRF-TOKEN': getCsrfToken(),
        'X-XSRF-TOKEN': getXsrfToken() ? decodeURIComponent(getXsrfToken()) : '',
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json',
      },
    },
    enabledTransports: ['ws', 'wss'],
    forceTLS: true,
    authorizer: (channel: any) => ({
      authorize: (socketId: string, callback: (error: any, data: any) => void) => {
        // Get fresh tokens for each authorization request
        const currentCsrfToken = getCsrfToken();
        const currentXsrfToken = getXsrfToken();

        axios
          .post(
            config.authEndpoint,
            {
              socket_id: socketId,
              channel_name: channel.name,
            },
            {
              headers: {
                'X-CSRF-TOKEN': currentCsrfToken,
                'X-XSRF-TOKEN': currentXsrfToken ? decodeURIComponent(currentXsrfToken) : '',
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
              },
              withCredentials: true,
            }
          )
          .then((response) => callback(null, response.data))
          .catch((error) => {
            if (realtimeDebug) {
              console.error('[Echo] Channel authorization failed', {
                channel: channel.name,
                status: error.response?.status,
                statusText: error.response?.statusText,
                data: error.response?.data,
                message: error.message,
                hasCsrfToken: !!currentCsrfToken,
                hasXsrfToken: !!currentXsrfToken,
              });
            }

            // Return error to Pusher
            callback(error, null);
          });
      },
    }),
  };

  echoInstance = new Echo(options);

  // Handle connection events
  echoInstance.connector.pusher.connection.bind('connected', () => {
    if (realtimeDebug) {
      console.log('[Echo] Connected to Pusher');
    }
  });

  echoInstance.connector.pusher.connection.bind('disconnected', () => {
    if (realtimeDebug) {
      console.log('[Echo] Disconnected from Pusher');
    }
  });

  echoInstance.connector.pusher.connection.bind('error', (err: any) => {
    if (!realtimeDebug) return;
    const detail = err?.error?.data || err?.error || err;
    console.error('[Echo] Connection error:', detail);
  });

  window.Echo = echoInstance;

  return echoInstance;
}

export function disconnectEcho(): void {
  const instance = echoInstance;
  echoInstance = null;
  if (typeof window !== 'undefined') window.Echo = null;
  if (!instance) return;
  try {
    const connection = instance.connector?.pusher?.connection;
    connection?.unbind?.();
    // Closing a socket that is already closing/closed is what logs
    // "WebSocket is already in CLOSING or CLOSED state"; skip it.
    if (connection && ['disconnected', 'failed', 'unavailable'].includes(connection.state)) return;
    instance.disconnect();
  } catch (error) {
    if (realtimeDebug) {
      console.warn('[Echo] Disconnect failed', error);
    }
  }
}
