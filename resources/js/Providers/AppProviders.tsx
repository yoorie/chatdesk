import { ConfirmProvider } from '@/Components/Notifications/ConfirmProvider';
import { Toaster } from '@/Components/UI/Toaster';
import { router } from '@inertiajs/react';
import { ReactNode, useEffect, useState } from 'react';
import { RealtimeProvider } from './RealtimeProvider';
import { ThemeProvider } from './ThemeProvider';

export function AppProviders({
  children,
  pusherConfig,
}: {
  children: ReactNode;
  pusherConfig?: { pusherKey: string; pusherCluster: string };
}) {
  const [activePusherConfig, setActivePusherConfig] = useState(pusherConfig);

  useEffect(
    () =>
      router.on('navigate', (event) => {
        setActivePusherConfig(event.detail.page.props.pusherConfig as typeof pusherConfig);
        (window as any).__brandingName = (event.detail.page.props.branding as any)?.platform_name;
      }),
    []
  );

  return (
    <RealtimeProvider pusherConfig={activePusherConfig}>
      <ThemeProvider>
        <ConfirmProvider>
          {children}
          <Toaster />
        </ConfirmProvider>
      </ThemeProvider>
    </RealtimeProvider>
  );
}
