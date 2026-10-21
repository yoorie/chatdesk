import { pathIncludes } from '@/lib/appUrl';
import { Head, usePage } from '@inertiajs/react';
import { Lock, ShieldAlert, Sparkles } from 'lucide-react';
import ErrorFrame, { defaultBackAction, defaultDashboardAction } from './ErrorFrame';

interface ForbiddenProps {
  reason?: 'plan' | null;
  feature?: string | null;
  requiredPlan?: string | null;
  billingUrl?: string | null;
}

export default function Forbidden({ reason, feature, requiredPlan, billingUrl }: ForbiddenProps) {
  const path = usePage().url.split('?')[0];
  const isApp = pathIncludes(path, '/app');

  if (reason === 'plan') {
    const featureName = feature || 'This feature';
    const planText = requiredPlan ? `the ${requiredPlan} plan` : 'a higher plan';

    return (
      <>
        <Head title={`${featureName} is a paid feature`} />
        <ErrorFrame
          code="Upgrade"
          title={`${featureName} is available on ${requiredPlan || 'a higher plan'}`}
          description={`Your current plan does not include ${featureName.toLowerCase()}. Upgrade to ${planText} to start using it. Everything else in your workspace keeps working as before.`}
          icon={Lock}
          accent="green"
          actions={[
            {
              label: requiredPlan ? `Upgrade to ${requiredPlan}` : 'View plans',
              href: billingUrl || (typeof route !== 'undefined' ? route('app.billing.index') : '/app/settings/billing'),
              icon: Sparkles,
              primary: true,
            },
            defaultDashboardAction(),
          ]}
        />
      </>
    );
  }

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
