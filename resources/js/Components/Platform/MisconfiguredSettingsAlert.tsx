import { Link } from '@inertiajs/react';
import { AlertTriangle, ChevronDown, X } from 'lucide-react';

interface MisconfiguredSetting {
  group: string;
  name: string;
  required: boolean;
  issues: string[];
  impact: string;
  route: string;
  tab: string;
}

interface MisconfiguredSettingsAlertProps {
  misconfiguredSettings: MisconfiguredSetting[];
  onDismiss?: () => void;
  variant?: 'dashboard' | 'settings';
}

export default function MisconfiguredSettingsAlert({
  misconfiguredSettings,
  onDismiss,
}: MisconfiguredSettingsAlertProps) {
  if (!misconfiguredSettings?.length) return null;
  const required = misconfiguredSettings.filter((setting) => setting.required).length;

  return (
    <div className="rounded-xl border border-amber-200 bg-amber-50/60 text-amber-950 dark:border-amber-400/25 dark:bg-amber-500/10 dark:text-amber-100">
      <div className="flex items-start gap-2 p-4">
        <AlertTriangle aria-hidden="true" className="mt-0.5 h-4 w-4 shrink-0" />
        <details className="group min-w-0 flex-1">
          <summary className="flex cursor-pointer list-none flex-wrap items-center gap-x-3 gap-y-1 text-sm font-medium [&::-webkit-details-marker]:hidden">
            {required > 0
              ? `${required} required settings need attention`
              : 'Optional integrations are not configured'}
            <span className="inline-flex items-center gap-1 text-xs font-normal opacity-75">
              View {misconfiguredSettings.length}{' '}
              {misconfiguredSettings.length === 1 ? 'item' : 'items'}
              <ChevronDown aria-hidden="true" className="h-3 w-3 group-open:rotate-180" />
            </span>
          </summary>
          <ul className="mt-4 divide-y divide-amber-200/60 dark:divide-amber-400/20">
            {misconfiguredSettings.map((setting) => (
              <li
                key={`${setting.group}-${setting.name}`}
                className="flex flex-wrap items-start justify-between gap-3 py-3 first:pt-0 last:pb-0"
              >
                <div className="min-w-0 flex-1 text-xs leading-5">
                  <p className="font-semibold">
                    {setting.name} · {setting.required ? 'Required' : 'Optional'}
                  </p>
                  <p className="opacity-80">{setting.impact}</p>
                  <ul className="list-inside list-disc opacity-80">
                    {setting.issues.map((issue) => (
                      <li key={issue}>{issue}</li>
                    ))}
                  </ul>
                </div>
                <Link
                  href={route('platform.settings', { tab: setting.tab })}
                  className="text-xs font-semibold underline underline-offset-2"
                >
                  Configure
                </Link>
              </li>
            ))}
          </ul>
        </details>
        {onDismiss && (
          <button
            type="button"
            onClick={onDismiss}
            aria-label="Dismiss configuration notice"
            className="rounded p-1 hover:bg-amber-100 dark:hover:bg-amber-500/20"
          >
            <X aria-hidden="true" className="h-4 w-4" />
          </button>
        )}
      </div>
    </div>
  );
}
