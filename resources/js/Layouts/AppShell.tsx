import DemoBanner from '@/Components/DemoBanner';
import AnalyticsScripts from '@/Components/Analytics/AnalyticsScripts';
import { BrandingWrapper } from '@/Components/Branding/BrandingWrapper';
import CookieConsentBanner from '@/Components/Compliance/CookieConsentBanner';
import { Sidebar } from '@/Components/Layout/Sidebar';
import { Topbar } from '@/Components/Layout/Topbar';
import RealtimeInboxAlerts from '@/Components/Notifications/RealtimeInboxAlerts';
import { usePage } from '@inertiajs/react';
import { ReactNode, useEffect, useState } from 'react';

interface AppShellProps {
  children: ReactNode;
  fullscreen?: boolean;
}

export default function AppShell({ children, fullscreen = false }: AppShellProps) {
  const { account, navigation, auth, ziggy } = usePage().props as any;
  const currentRoute = usePage().url.split('?')[0];
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [sidebarCollapsed, setSidebarCollapsed] = useState(false);

  // The sidebar builds links during this render. Ziggy has to be the page's
  // config first, or a locked item such as Calls falls back to /app/settings/billing.
  if (typeof window !== 'undefined' && ziggy) {
    (window as any).Ziggy = ziggy;
  }

  // Close sidebar when route changes on mobile
  useEffect(() => {
    setSidebarOpen(false);
  }, [currentRoute]);

  // Close sidebar on window resize to desktop
  useEffect(() => {
    const handleResize = () => {
      if (window.innerWidth >= 1024) {
        setSidebarOpen(false);
      }
    };
    window.addEventListener('resize', handleResize);
    return () => window.removeEventListener('resize', handleResize);
  }, []);

  return (
    <BrandingWrapper>
      <div className="workspace-app flex h-[100dvh] overflow-hidden bg-ui-bg text-ui-text dark:bg-ui-dark-bg dark:text-ui-dark-text">
        {!fullscreen && (
          <Sidebar
            navigation={navigation || []}
            currentRoute={currentRoute}
            account={account}
            isOpen={sidebarOpen}
            onClose={() => setSidebarOpen(false)}
            onCollapseChange={setSidebarCollapsed}
          />
        )}
        <div
          className={`flex-1 flex flex-col min-w-0 transition-[padding] duration-300 ${fullscreen ? '' : sidebarCollapsed ? 'lg:pl-14' : 'lg:pl-56'}`}
        >
          {!fullscreen && (
            <Topbar user={auth?.user} onMenuClick={() => setSidebarOpen(!sidebarOpen)} />
          )}
          <main
            className={
              fullscreen
                ? 'flex-1 overflow-hidden'
                : 'ui-scrollbar flex-1 overflow-y-auto p-4 lg:px-6 lg:py-5'
            }
          >
            {children}
<DemoBanner />
            {!fullscreen && (
              <footer className="mt-8 border-t border-gray-200 pt-4 pb-2 text-center text-xs text-ui-text-muted dark:border-ui-dark-border dark:text-ui-dark-text-muted">
                ChatDesk open-source edition &middot; Built by{' '}
                <a href="https://yoorie.com" target="_blank" rel="noreferrer" className="font-medium underline">
                  Yoorie
                </a>
                . Want to run it as a SaaS, or need more? Get a paid plan at{' '}
                <a href="https://chatdesk.yoorie.com" target="_blank" rel="noreferrer" className="font-medium underline">
                  chatdesk.yoorie.com
                </a>{' '}
                or use{' '}
                <a href="https://zyptos.com" target="_blank" rel="noreferrer" className="font-medium underline">
                  Zyptos
                </a>
                , the premium version of ChatDesk.
              </footer>
            )}
          </main>
        </div>
        <CookieConsentBanner />
        <AnalyticsScripts />
        <RealtimeInboxAlerts />
      </div>
    </BrandingWrapper>
  );
}
