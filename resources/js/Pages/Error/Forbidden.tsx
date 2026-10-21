import { pathIncludes } from '@/lib/appUrl';
import { Head, usePage } from '@inertiajs/react';
import { ShieldAlert } from 'lucide-react';
import ErrorFrame, { defaultBackAction, defaultDashboardAction } from './ErrorFrame';

export default function Forbidden() {
  const path = usePage().url.split('?')[0];
  const isApp = pathIncludes(path, '/app');

  const actions = [
    defaultBackAction(),
    ...(isApp ? [defaultDashboardAction()] : []),
    ...(isApp && typeof route !== 'undefined'
      ? [{ label: 'Inbox', href: route('app.whatsapp.conversations.index') }]
      : []),
  ];

  return (
    <>
      <Head title="Access denied" />
      <ErrorFrame
        code="403"
        title="You don't have access to this page"
        description="Your role in this workspace doesn't include this page. Ask a workspace owner or admin if you need access."
        icon={ShieldAlert}
        accent="amber"
        actions={actions}
      />
    </>
  );
}
