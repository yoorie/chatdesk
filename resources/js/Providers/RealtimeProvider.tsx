import { RealtimeSubscriptions } from '@/realtime/subscriptions';
import type Echo from 'laravel-echo';
import {
  createContext,
  ReactNode,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
} from 'react';

interface RealtimeConfig {
  pusherKey: string;
  pusherCluster: string;
}

interface RealtimeContextType {
  echo: Echo<any> | null;
  connected: boolean;
  subscribe: (channel: string, event: string, callback: (data: any) => void) => () => void;
  unsubscribe: (channel: string) => void;
}

const RealtimeContext = createContext<RealtimeContextType | null>(null);
const realtimeDebug = import.meta.env.VITE_REALTIME_DEBUG === 'true';

interface RealtimeProviderProps {
  children: ReactNode;
  pusherConfig?: RealtimeConfig;
}

export function RealtimeProvider({ children, pusherConfig }: RealtimeProviderProps) {
  // Echo and its subscription registry live and die together, so channels are always left
  // while the socket is still open (see RealtimeSubscriptions.dispose()).
  const [realtime, setRealtime] = useState<{
    echo: Echo<any>;
    subscriptions: RealtimeSubscriptions;
  } | null>(null);
  const [connected, setConnected] = useState(false);
  const echo = realtime?.echo ?? null;
  const subscriptions = realtime?.subscriptions ?? null;
  const pusherKey = pusherConfig?.pusherKey?.trim() || '';
  const pusherCluster = pusherConfig?.pusherCluster?.trim() || '';

  useEffect(() => {
    if (!pusherKey) {
      setRealtime(null);
      setConnected(false);
      if (realtimeDebug) {
        console.warn('[RealtimeProvider] Pusher config not available, realtime disabled');
      }
      return;
    }

    let cancelled = false;
    let disconnect: (() => void) | undefined;
    let registry: RealtimeSubscriptions | null = null;

    const connect = async () => {
      try {
        if (!pusherCluster) {
          if (realtimeDebug) {
            console.warn(
              '[RealtimeProvider] Missing Pusher cluster. Set cluster in Platform Settings or provide a custom host.'
            );
          }
          return;
        }
        const { initializeEcho, disconnectEcho } = await import('@/realtime/echo');
        if (cancelled) return;
        disconnect = disconnectEcho;
        const echoInstance = initializeEcho({
          pusherKey,
          pusherCluster,
          authEndpoint: '/broadcasting/auth',
        });
        if (!echoInstance || cancelled) return;

        registry = new RealtimeSubscriptions(echoInstance);
        setRealtime({ echo: echoInstance, subscriptions: registry });
        (window as any).__echo = echoInstance;

        // Monitor connection state
        const pusher = echoInstance.connector.pusher;
        setConnected(pusher.connection.state === 'connected');

        pusher.connection.bind('connected', () => {
          if (realtimeDebug) {
            console.log('[RealtimeProvider] Pusher connected');
          }
          setConnected(true);
        });

        pusher.connection.bind('disconnected', () => {
          setConnected(false);
        });

        pusher.connection.bind('error', () => {
          setConnected(false);
        });

        pusher.connection.bind('message', (event: any) => {
          if (realtimeDebug && event?.event === 'pusher:error') {
            console.error('[Echo] Pusher error', event?.data);
          }
        });
      } catch (error) {
        if (realtimeDebug) {
          console.error('[RealtimeProvider] Failed to initialize Echo:', error);
        }
      }
    };
    void connect();

    return () => {
      cancelled = true;
      // Leave channels first, while the socket can still send, then close it.
      registry?.dispose();
      disconnect?.();
      setConnected(false);
      setRealtime(null);
      delete (window as any).__echo;
    };
  }, [pusherKey, pusherCluster]);

  const subscribe = useCallback(
    (channel: string, event: string, callback: (data: any) => void) => {
      return subscriptions?.subscribe(channel, event, callback) ?? (() => {});
    },
    [subscriptions]
  );

  const unsubscribe = useCallback(
    (channel: string) => {
      subscriptions?.unsubscribe(channel);
    },
    [subscriptions]
  );

  const contextValue = useMemo(
    () => ({ echo, connected, subscribe, unsubscribe }),
    [echo, connected, subscribe, unsubscribe]
  );

  return <RealtimeContext.Provider value={contextValue}>{children}</RealtimeContext.Provider>;
}

export function useRealtime(): RealtimeContextType {
  const context = useContext(RealtimeContext);
  if (!context) {
    throw new Error('useRealtime must be used within RealtimeProvider');
  }
  return context;
}
