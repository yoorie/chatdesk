import { brandName } from '../../../lib/brand';
import InputError from '@/Components/InputError';
import { Badge } from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/UI/Card';
import { Modal, PageHeader } from '@/Components/UI/Elements';
import { useLatest } from '@/hooks/useLatest';
import { useToast } from '@/hooks/useToast';
import { friendlyError, friendlyStatus } from '@/lib/friendly';
import SettingsNav from '@/Components/Layout/SettingsNav';
import AppShell from '@/Layouts/AppShell';
import ManualConnectDialog, { CONNECT_GUIDE_URL } from './ManualConnectDialog';
import { Head, router, useForm } from '@inertiajs/react';
import {
  AlertTriangle,
  BadgeCheck,
  Building2,
  CheckCircle2,
  Globe2,
  Image,
  Loader2,
  Mail,
  MapPin,
  Medal,
  MessageCircle,
  Phone,
  RefreshCw,
  ShieldCheck,
  Sparkles,
  Trash2,
  UserCheck,
  Zap,
} from 'lucide-react';
import { FormEventHandler, ReactNode, useEffect, useMemo, useRef, useState } from 'react';
declare global {
  interface Window {
    FB?: any;
    fbAsyncInit?: () => void;
  }
}
interface Connection {
  id: number;
  slug?: string;
  name: string;
  waba_id: string | null;
  meta_business_id?: string | null;
  meta_waba_name?: string | null;
  meta_account_review_status?: string | null;
  meta_business_verification_status?: string | null;
  meta_timezone_id?: string | null;
  meta_template_namespace?: string | null;
  meta_subscribed_apps?: any[] | null;
  phone_number_id: string;
  business_phone: string | null;
  meta_verified_name?: string | null;
  phone_number_status?: string | null;
  quality_rating?: string | null;
  code_verification_status?: string | null;
  business_category?: string | null;
  business_username?: string | null;
  business_about?: string | null;
  business_address?: string | null;
  business_description?: string | null;
  business_email?: string | null;
  business_websites?: string[] | null;
  business_vertical?: string | null;
  profile_picture_url?: string | null;
  profile_picture_handle?: string | null;
  profile_synced_at?: string | null;
  profile_sync_error?: string | null;
  setup_method?: string | null;
  connection_mode?: 'cloud_api' | 'coexistence' | string | null;
  coexistence_status?: string | null;
  coexistence_metadata?: Record<string, any> | null;
  coexistence_last_checked_at?: string | null;
  coexistence_last_error?: string | null;
  webhook_url?: string | null;
  webhook_verify_token?: string | null;
  webhook_subscribed?: boolean;
  webhook_last_received_at?: string | null;
  webhook_last_error?: string | null;
  is_active: boolean;
  api_version?: string | null;
  throughput_cap_per_minute?: number | null;
  quiet_hours_start?: string | null;
  quiet_hours_end?: string | null;
  quiet_hours_timezone?: string | null;
  meta_event_logs?: Array<{
    id: number;
    field?: string | null;
    event_type?: string | null;
    status?: string | null;
    message?: string | null;
    received_at?: string | null;
  }>;
}
type EmbeddedSignup = {
  enabled?: boolean;
  appId?: string;
  configId?: string;
  coexistenceEnabled?: boolean;
  coexistenceConfigId?: string;
  apiVersion?: string;
};
type CentralWebhook = {
  url: string;
  verify_token: string;
};
type SetupPath = 'new_cloud_api' | 'migrate_api' | 'coexistence';
type PendingEmbeddedReview = {
  payload: Record<string, string>;
  summary: {
    mode: 'cloud_api' | 'coexistence';
    wabaId: string;
    phoneNumberId: string;
    businessPhone: string;
    businessId: string;
  };
};
const profileValue = (value: string | null | undefined, fallback = '') => {
  const normalized = typeof value === 'string' ? value.trim() : '';
  return normalized !== '' ? normalized : fallback;
};
export default function ConnectionsIndex({
  connections,
  canCreate,
  embeddedSignup,
  centralWebhook,
  defaultApiVersion,
}: {
  account: any;
  connections: Connection[];
  canCreate: boolean;
  embeddedSignup: EmbeddedSignup;
  centralWebhook: CentralWebhook;
  defaultApiVersion: string;
}) {
  const { addToast } = useToast();
  const activeConnections = connections.filter((item) => item.is_active);
  const [selectedId, setSelectedId] = useState<number | null>(activeConnections[0]?.id ?? null);
  const connection =
    activeConnections.find((item) => item.id === selectedId) ?? activeConnections[0] ?? null;
  const [embeddedReady, setEmbeddedReady] = useState(false);
  const [autoStatus, setAutoStatus] = useState<
    'idle' | 'loading' | 'authorizing' | 'saving' | 'error'
  >('idle');
  const [autoMessage, setAutoMessage] = useState(
    'Sign in with Facebook to finish. Takes about 2 minutes.'
  );
  const [showAutoDialog, setShowAutoDialog] = useState(false);
  const [showWebhookDialog, setShowWebhookDialog] = useState(false);
  const [showVerificationDialog, setShowVerificationDialog] = useState(false);
  const [showMetaDetails, setShowMetaDetails] = useState(false);
  const [showDisconnectDialog, setShowDisconnectDialog] = useState(false);
  const [signupMode, setSignupMode] = useState<'cloud_api' | 'coexistence'>('cloud_api');
  const [setupPath, setSetupPath] = useState<SetupPath>('new_cloud_api');
  const [refreshingHealth, setRefreshingHealth] = useState(false);
  const [disconnecting, setDisconnecting] = useState(false);
  const [pendingEmbeddedReview, setPendingEmbeddedReview] = useState<PendingEmbeddedReview | null>(
    null
  );
  const embeddedSessionRef = useRef<Record<string, any>>({});
  const embeddedEnabled = Boolean(
    embeddedSignup?.enabled &&
    embeddedSignup?.appId &&
    (embeddedSignup?.configId || embeddedSignup?.coexistenceConfigId)
  );
  const connectionKey = connection?.id ?? 'new';
  const profileForm = useForm({
    _method: 'put',
    name: connection?.name ?? '',
    waba_id: connection?.waba_id ?? '',
    phone_number_id: connection?.phone_number_id ?? '',
    business_phone: connection?.business_phone ?? '',
    business_category: connection?.business_category ?? 'Retail',
    business_username: connection?.business_username ?? '',
    business_about: profileValue(
      connection?.business_about,
      'Turn conversations into conversions.'
    ),
    business_address: profileValue(connection?.business_address),
    business_description: profileValue(connection?.business_description),
    business_email: profileValue(connection?.business_email),
    business_websites: connection?.business_websites?.length
      ? connection.business_websites
      : ['', ''],
    business_vertical: connection?.business_vertical ?? 'RETAIL',
    profile_picture_handle: connection?.profile_picture_handle ?? '',
    profile_picture_file: null as File | null,
    sync_to_meta: false,
    access_token: '',
    api_version: connection?.api_version ?? defaultApiVersion,
    throughput_cap_per_minute: connection?.throughput_cap_per_minute ?? 120,
    quiet_hours_start: connection?.quiet_hours_start ?? '',
    quiet_hours_end: connection?.quiet_hours_end ?? '',
    quiet_hours_timezone: connection?.quiet_hours_timezone ?? 'UTC',
  });
  const profileSetup = useLatest({
    connection,
    defaultApiVersion,
    setProfileData: profileForm.setData,
  });
  useEffect(() => {
    const { connection, defaultApiVersion, setProfileData } = profileSetup.current;
    if (!connection) {
      setProfileData({
        _method: 'put',
        name: '',
        waba_id: '',
        phone_number_id: '',
        business_phone: '',
        business_category: 'Retail',
        business_username: '',
        business_about: 'Turn conversations into conversions.',
        business_address: '',
        business_description: '',
        business_email: '',
        business_websites: ['', ''],
        business_vertical: 'RETAIL',
        profile_picture_handle: '',
        profile_picture_file: null,
        sync_to_meta: false,
        access_token: '',
        api_version: defaultApiVersion,
        throughput_cap_per_minute: 120,
        quiet_hours_start: '',
        quiet_hours_end: '',
        quiet_hours_timezone: 'UTC',
      });
      return;
    }
    setProfileData({
      _method: 'put',
      name: connection.name ?? '',
      waba_id: connection.waba_id ?? '',
      phone_number_id: connection.phone_number_id ?? '',
      business_phone: connection.business_phone ?? '',
      business_category: connection.business_category ?? 'Retail',
      business_username: connection.business_username ?? '',
      business_about: profileValue(
        connection.business_about,
        'Turn conversations into conversions.'
      ),
      business_address: profileValue(connection.business_address),
      business_description: profileValue(connection.business_description),
      business_email: profileValue(connection.business_email),
      business_websites: connection.business_websites?.length
        ? connection.business_websites
        : ['', ''],
      business_vertical: connection.business_vertical ?? 'RETAIL',
      profile_picture_handle: connection.profile_picture_handle ?? '',
      profile_picture_file: null,
      sync_to_meta: false,
      access_token: '',
      api_version: connection.api_version ?? defaultApiVersion,
      throughput_cap_per_minute: connection.throughput_cap_per_minute ?? 120,
      quiet_hours_start: connection.quiet_hours_start ?? '',
      quiet_hours_end: connection.quiet_hours_end ?? '',
      quiet_hours_timezone: connection.quiet_hours_timezone ?? 'UTC',
    });
  }, [connectionKey, profileSetup]);
  useEffect(() => {
    if (!embeddedEnabled) {
      return;
    }
    const handleEmbeddedMessage = (event: MessageEvent) => {
      if (!event.origin.startsWith('https://')) return;
      const origin = new URL(event.origin);
      if (
        origin.protocol !== 'https:' ||
        (origin.hostname !== 'facebook.com' && !origin.hostname.endsWith('.facebook.com'))
      )
        return;
      try {
        const data = typeof event.data === 'string' ? JSON.parse(event.data) : event.data;
        if (data?.type === 'WA_EMBEDDED_SIGNUP') {
          embeddedSessionRef.current = data || {};
          if (data.event === 'FINISH') {
            setAutoMessage('Signed in. Finishing up...');
          } else if (data.event === 'CANCEL') {
            setAutoStatus('error');
            setAutoMessage('Sign-in was closed. Try again when you are ready.');
          } else if (data.event === 'ERROR') {
            setAutoStatus('error');
            setAutoMessage(
              friendlyError(data?.data?.error_message, 'Something went wrong. Please try again.')
            );
          }
        }
      } catch (error) {
        setAutoStatus('error');
        setAutoMessage('Something went wrong while connecting. Please try again.');
      }
    };
    window.addEventListener('message', handleEmbeddedMessage);
    const initSdk = () => {
      if (!window.FB) {
        return;
      }
      window.FB.init({
        appId: embeddedSignup.appId,
        cookie: true,
        xfbml: true,
        version: embeddedSignup.apiVersion || defaultApiVersion || 'v25.0',
      });
      setEmbeddedReady(true);
      setAutoStatus('idle');
      setAutoMessage('Sign in with Facebook to finish. Takes about 2 minutes.');
    };
    if (window.FB) {
      initSdk();
      return () => window.removeEventListener('message', handleEmbeddedMessage);
    }
    setAutoStatus('loading');
    setAutoMessage('Getting ready...');
    window.fbAsyncInit = initSdk;
    if (!document.getElementById('facebook-jssdk')) {
      const script = document.createElement('script');
      script.id = 'facebook-jssdk';
      script.src = 'https://connect.facebook.net/en_US/sdk.js';
      script.async = true;
      script.defer = true;
      script.onerror = () => {
        setAutoStatus('error');
        setAutoMessage('Could not load. Refresh the page and try again.');
      };
      document.body.appendChild(script);
    }
    return () => {
      window.removeEventListener('message', handleEmbeddedMessage);
    };
  }, [
    connectionKey,
    embeddedEnabled,
    embeddedSignup?.appId,
    embeddedSignup?.apiVersion,
    defaultApiVersion,
  ]);
  useEffect(() => {
    if (connection || typeof window === 'undefined') {
      return;
    }
    const setup = new URLSearchParams(window.location.search).get('setup');
    if (setup === 'embedded') {
      setSetupPath('new_cloud_api');
      setSignupMode('cloud_api');
      setShowAutoDialog(true);
      return;
    }
    if (setup === 'migrate_api') {
      setSetupPath('migrate_api');
      setSignupMode('cloud_api');
      setShowAutoDialog(true);
      return;
    }
    if (setup === 'coexistence') {
      setSetupPath('coexistence');
      setSignupMode('coexistence');
      setShowAutoDialog(true);
      return;
    }
  }, [connectionKey, connection]);
  const automaticDisabled =
    !canCreate ||
    !embeddedEnabled ||
    !embeddedReady ||
    (signupMode === 'cloud_api' && !embeddedSignup?.configId) ||
    (signupMode === 'coexistence' && !embeddedSignup?.coexistenceEnabled) ||
    autoStatus === 'authorizing' ||
    autoStatus === 'saving';
  const automaticButtonLabel = useMemo(() => {
    if (pendingEmbeddedReview) return 'Confirm and connect';
    if (!embeddedEnabled) return 'Not available yet';
    if (signupMode === 'cloud_api' && !embeddedSignup?.configId) return 'Not available yet';
    if (signupMode === 'coexistence' && !embeddedSignup?.coexistenceEnabled)
      return 'Not available yet';
    if (!embeddedReady || autoStatus === 'loading') return 'Loading...';
    if (autoStatus === 'authorizing') return 'Opening Facebook...';
    if (autoStatus === 'saving') return 'Finishing setup...';
    return 'Continue with Facebook';
  }, [
    pendingEmbeddedReview,
    embeddedEnabled,
    embeddedReady,
    autoStatus,
    signupMode,
    embeddedSignup?.coexistenceEnabled,
    embeddedSignup?.configId,
  ]);
  const submitEmbedded = (payload: Record<string, string>) => {
    setAutoStatus('saving');
    setAutoMessage('Linking your number...');
    router.post(route('app.whatsapp.connections.store-embedded', {}), payload, {
      preserveScroll: true,
      onSuccess: (page) => {
        const error = (page.props.flash as { error?: string } | undefined)?.error;
        if (error) {
          setAutoStatus('error');
          setAutoMessage(error);
          addToast({
            title: 'WhatsApp is not connected yet',
            description: friendlyError(error),
            variant: 'error',
          });
          return;
        }
        setPendingEmbeddedReview(null);
        setAutoStatus('idle');
        setAutoMessage('WhatsApp connected.');
        setShowAutoDialog(false);
        addToast({ title: 'WhatsApp connected', variant: 'success' });
      },
      onError: (errors) => {
        setAutoStatus('error');
        setAutoMessage(
          friendlyError((errors as any)?.embedded, 'Could not finish connecting. Please try again.')
        );
        addToast({ title: 'WhatsApp is not connected yet', variant: 'error' });
      },
    });
  };
  const confirmEmbeddedReview = () => {
    if (!pendingEmbeddedReview) {
      startEmbeddedSignup();
      return;
    }
    submitEmbedded(pendingEmbeddedReview.payload);
  };
  const startEmbeddedSignup = () => {
    if (automaticDisabled || !window.FB) {
      return;
    }
    setPendingEmbeddedReview(null);
    setAutoStatus('authorizing');
    setAutoMessage('Opening Facebook...');
    window.FB.login(
      (response: any) => {
        if (!response?.authResponse) {
          setAutoStatus('error');
          setAutoMessage('Sign-in was closed. Try again when you are ready.');
          return;
        }
        const code = response.authResponse.code || '';
        const accessToken = response.authResponse.accessToken || '';
        if (!code && !accessToken) {
          setAutoStatus('error');
          setAutoMessage('Facebook did not finish signing you in. Please try again.');
          return;
        }
        window.setTimeout(() => {
          const session = embeddedSessionRef.current || {};
          const sessionData = session.data || {};
          const payload = {
            name: '',
            waba_id: sessionData.waba_id || session.waba_id || '',
            phone_number_id: sessionData.phone_number_id || session.phone_number_id || '',
            business_id: sessionData.business_id || session.business_id || '',
            business_phone: sessionData.phone_number || sessionData.display_phone_number || '',
            session_info: JSON.stringify(session),
            connection_mode: signupMode,
            access_token: accessToken,
            code,
            pin: '',
          };
          setPendingEmbeddedReview({
            payload,
            summary: {
              mode: signupMode,
              wabaId: payload.waba_id,
              phoneNumberId: payload.phone_number_id,
              businessPhone: payload.business_phone,
              businessId: payload.business_id,
            },
          });
          setAutoStatus('idle');
          setAutoMessage('Check the number, then confirm.');
        }, 350);
      },
      {
        config_id:
          signupMode === 'coexistence' && embeddedSignup.coexistenceConfigId
            ? embeddedSignup.coexistenceConfigId
            : embeddedSignup.configId,
        response_type: 'code',
        override_default_response_type: true,
        scope: 'whatsapp_business_management,whatsapp_business_messaging,business_management',
        extras: {
          ...(signupMode === 'coexistence'
            ? { featureType: 'whatsapp_business_app_onboarding' }
            : {}),
          version:
            signupMode === 'coexistence'
              ? 'v4'
              : embeddedSignup.apiVersion || defaultApiVersion || 'v25.0',
          sessionInfoVersion: '3',
          setup: {},
        },
      }
    );
  };
  const submitProfile: FormEventHandler = (event) => {
    event.preventDefault();
    if (!connection) return;
    profileForm.transform((data) => ({ ...data, sync_to_meta: false }));
    profileForm.post(
      route('app.whatsapp.connections.update', { connection: connection.slug ?? connection.id }),
      {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => addToast({ title: 'Profile saved', variant: 'success' }),
        onFinish: () => profileForm.transform((data) => data),
      }
    );
  };
  const syncProfileToMeta = () => {
    if (!connection) return;
    profileForm.transform((data) => ({ ...data, sync_to_meta: true }));
    profileForm.post(
      route('app.whatsapp.connections.update', { connection: connection.slug ?? connection.id }),
      {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => addToast({ title: 'Profile updated on WhatsApp', variant: 'success' }),
        onFinish: () => profileForm.transform((data) => data),
      }
    );
  };
  const updateWebsite = (index: number, value: string) => {
    const websites = [...(profileForm.data.business_websites || ['', ''])];
    websites[index] = value;
    profileForm.setData('business_websites', websites);
  };
  const refreshMetaHealth = () => {
    if (!connection) return;
    setRefreshingHealth(true);
    router.post(
      route('app.whatsapp.connections.sync-meta', { connection: connection.slug ?? connection.id }),
      {},
      {
        preserveScroll: true,
        onFinish: () => setRefreshingHealth(false),
        onSuccess: () => addToast({ title: 'Details refreshed from WhatsApp', variant: 'success' }),
      }
    );
  };
  const disconnectWaba = () => {
    if (!connection) return;
    setDisconnecting(true);
    router.delete(
      route('app.whatsapp.connections.destroy', { connection: connection.slug ?? connection.id }),
      {
        preserveScroll: true,
        onSuccess: () => {
          setShowDisconnectDialog(false);
          addToast({ title: 'WhatsApp disconnected', variant: 'success' });
        },
        onFinish: () => setDisconnecting(false),
      }
    );
  };
  return (
    <AppShell>
      <Head title="WhatsApp account" />
      <div className="module-page max-w-[1100px]">
        <SettingsNav active="whatsapp" className="mb-0" />
        <PageHeader
          title="WhatsApp account"
          description={
            connection
              ? 'Your WhatsApp number and business profile.'
              : 'Connect your number to start messaging.'
          }
          actions={
            connection && canCreate ? (
              <Button
                type="button"
                onClick={() => {
                  setSetupPath('new_cloud_api');
                  setSignupMode('cloud_api');
                  setShowAutoDialog(true);
                }}
              >
                Add another number
              </Button>
            ) : null
          }
        />
        {activeConnections.length > 1 && (
          <div className="flex gap-2 overflow-x-auto pb-1" role="tablist" aria-label="WhatsApp numbers">
            {activeConnections.map((item) => (
              <button
                key={item.id}
                type="button"
                role="tab"
                aria-selected={connection?.id === item.id}
                onClick={() => setSelectedId(item.id)}
                className={
                  connection?.id === item.id
                    ? 'shrink-0 rounded-lg bg-[#047857] px-3 py-1.5 text-sm font-medium text-white'
                    : 'shrink-0 rounded-lg bg-white px-3 py-1.5 text-sm font-medium text-ui-text ring-1 ring-gray-200 dark:bg-slate-800 dark:text-ui-dark-text dark:ring-slate-600'
                }
              >
                {item.business_phone || item.name}
              </button>
            ))}
          </div>
        )}
        {!connection ? (
          <section>
            <Card className="mx-auto max-w-xl p-5 text-center sm:p-8">
              <span className="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-ui-green/10 text-ui-green-dark dark:bg-ui-green/15 dark:text-emerald-300">
                <MessageCircle className="h-6 w-6" />
              </span>
              <h2 className="mt-4 text-xl font-bold tracking-tight text-ui-text dark:text-ui-dark-text">
                Connect your WhatsApp number
              </h2>
              <p className="mt-1 text-sm text-ui-text-muted dark:text-ui-dark-text-muted">
                Uses your own Meta app and the official WhatsApp Cloud API.{' '}
                <a href={CONNECT_GUIDE_URL} target="_blank" rel="noreferrer" className="font-medium text-ui-green-dark underline dark:text-emerald-300">
                  Read the setup guide
                </a>
              </p>
              <ol className="mx-auto mt-6 grid max-w-md grid-cols-3 gap-2 text-left sm:gap-3">
                {['Create a Meta app', 'Paste your IDs & token', 'Add the webhook'].map(
                  (label, index) => (
                    <li key={label} className="flex flex-col items-center gap-2 text-center">
                      <span className="flex h-7 w-7 items-center justify-center rounded-full bg-ui-green text-xs font-bold text-white">
                        {index + 1}
                      </span>
                      <span className="text-xs font-medium leading-4 text-ui-text dark:text-ui-dark-text">
                        {label}
                      </span>
                    </li>
                  )
                )}
              </ol>
              <Button
                size="lg"
                onClick={() => {
                  setSetupPath('new_cloud_api');
                  setSignupMode('cloud_api');
                  setShowAutoDialog(true);
                }}
                disabled={!canCreate}
                className="mt-6 w-full sm:w-auto sm:min-w-[240px]"
              >
                Connect WhatsApp
              </Button>
              <details className="group mt-5 text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
                <summary className="cursor-pointer list-none font-medium text-ui-green-dark hover:underline dark:text-emerald-300">
                  Learn more
                </summary>
                <ul className="mx-auto mt-2 max-w-sm space-y-1 text-left leading-5">
                  <li>• Use a new number, or one already on the WhatsApp Cloud API.</li>
                  <li>• Need several numbers or a multi-tenant SaaS? See ChatDesk paid plans or Zyptos.</li>
                  <li>• Old chats are not copied over. New chats show up once connected.</li>
                </ul>
              </details>
            </Card>
          </section>
        ) : (
          <section className="space-y-6">
            {
              <div className="rounded-card bg-gradient-to-br from-ui-green-soft to-emerald-50 p-5 ring-1 ring-emerald-100 dark:from-emerald-950/30 dark:to-slate-800 dark:ring-emerald-900/50 lg:p-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start">
                  <div className="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-white shadow-card dark:bg-slate-800">
                    <Phone className="h-6 w-6 text-ui-green-dark dark:text-ui-green" />
                  </div>
                  <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                      <span className="truncate text-lg font-semibold text-ui-text dark:text-ui-dark-text">
                        {connection.meta_verified_name || profileForm.data.name || connection.name}
                      </span>
                      <span className="inline-flex items-center gap-1 rounded-full bg-emerald-500 px-2 py-0.5 text-[11px] font-medium text-white">
                        <CheckCircle2 className="h-2.5 w-2.5" />
                        Connected
                      </span>
                    </div>
                    <p className="mt-1 text-sm text-ui-text-muted dark:text-ui-dark-text-muted">
                      {connection.business_phone || connection.phone_number_id} ·{' '}
                      {connection.meta_waba_name ||
                        verticalLabel(profileForm.data.business_vertical) ||
                        'Business'}
                    </p>
                    <div className="mt-3 flex flex-wrap gap-3 text-xs">
                      <StatusPill
                        icon={<Zap className="h-3 w-3 text-emerald-600" />}
                        label="Number:"
                        value={friendlyStatus(connection.phone_number_status || 'connected')}
                      />
                      <StatusPill
                        icon={<ShieldCheck className="h-3 w-3 text-blue-600" />}
                        label="Display name:"
                        value={connection.meta_verified_name || 'Not set yet'}
                      />
                      <StatusPill
                        icon={<Medal className="h-3 w-3 text-amber-600" />}
                        label="Quality:"
                        value={friendlyStatus(connection.quality_rating, 'Not rated yet')}
                      />
                      {connection.connection_mode === 'coexistence' && (
                        <StatusPill
                          icon={<MessageCircle className="h-3 w-3 text-emerald-700" />}
                          label="WhatsApp app:"
                          value={friendlyStatus(connection.coexistence_status || 'connected')}
                        />
                      )}
                    </div>
                  </div>
                  <div className="flex flex-shrink-0 flex-col gap-2 sm:items-end">
                    <div className="flex flex-wrap gap-2">
                      {null}
                      <Button
                        variant="secondary"
                        onClick={() => {
                          setSetupPath(
                            connection.connection_mode === 'coexistence'
                              ? 'coexistence'
                              : 'new_cloud_api'
                          );
                          setSignupMode(
                            connection.connection_mode === 'coexistence'
                              ? 'coexistence'
                              : 'cloud_api'
                          );
                          setShowAutoDialog(true);
                        }}
                        disabled={autoStatus === 'authorizing' || autoStatus === 'saving'}
                      >
                        {autoStatus === 'authorizing' || autoStatus === 'saving' ? (
                          <Loader2 className="h-4 w-4 animate-spin" />
                        ) : (
                          <RefreshCw className="h-4 w-4" />
                        )}
                        Reconnect
                      </Button>
                      <Button
                        variant="danger"
                        onClick={() => setShowDisconnectDialog(true)}
                        disabled={disconnecting}
                      >
                        {disconnecting ? (
                          <Loader2 className="h-4 w-4 animate-spin" />
                        ) : (
                          <Trash2 className="h-4 w-4" />
                        )}
                        Disconnect
                      </Button>
                    </div>
                    <Button
                      type="button"
                      variant="ghost"
                      size="sm"
                      onClick={() => setShowMetaDetails((value) => !value)}
                    >
                      {showMetaDetails ? 'Hide account details' : 'Show account details'}
                    </Button>
                  </div>
                </div>
              </div>
            }

            {showMetaDetails && (
              <Card>
                <CardContent className="p-4">
                  <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <DetailItem
                      label="WhatsApp Business account ID"
                      value={connection.waba_id || 'Not available'}
                      mono
                    />
                    <DetailItem
                      label="Meta business ID"
                      value={connection.meta_business_id || 'Not available yet'}
                      mono
                    />
                    <DetailItem
                      label="Account name"
                      value={connection.meta_waba_name || 'Not available yet'}
                    />
                    <DetailItem
                      label="Number ID (for support)"
                      value={connection.phone_number_id || 'Not available'}
                      mono
                    />
                    <DetailItem
                      label="Business number"
                      value={connection.business_phone || 'Not available yet'}
                    />
                    <DetailItem
                      label="WhatsApp username"
                      value={
                        connection.business_username
                          ? `@${connection.business_username.replace(/^@+/, '')}`
                          : 'Not added'
                      }
                    />
                    <DetailItem
                      label="Display name"
                      value={connection.meta_verified_name || 'Not set yet'}
                    />
                    <DetailItem
                      label="Number status"
                      value={friendlyStatus(connection.phone_number_status, 'Not available yet')}
                    />
                    <DetailItem
                      label="Quality rating"
                      value={friendlyStatus(connection.quality_rating, 'Not rated yet')}
                    />
                    <DetailItem
                      label="Number verification"
                      value={friendlyStatus(
                        connection.code_verification_status,
                        'Not available yet'
                      )}
                    />
                    <DetailItem
                      label="Account review"
                      value={friendlyStatus(
                        connection.meta_account_review_status,
                        'Not available yet'
                      )}
                    />
                    <DetailItem
                      label="Business verification"
                      value={friendlyStatus(
                        connection.meta_business_verification_status,
                        'Not available yet'
                      )}
                    />
                    <DetailItem
                      label="Template namespace (for support)"
                      value={connection.meta_template_namespace || 'Not available yet'}
                      mono
                    />
                    <DetailItem
                      label="Account timezone"
                      value={connection.meta_timezone_id || 'Not available yet'}
                    />
                    <DetailItem
                      label="Business category"
                      value={verticalLabel(connection.business_vertical) || 'Not set yet'}
                    />
                    <DetailItem
                      label="How it's connected"
                      value={
                        connection.connection_mode === 'coexistence'
                          ? 'With the WhatsApp Business app'
                          : 'WhatsApp Business platform'
                      }
                    />
                    {connection.connection_mode === 'coexistence' && (
                      <>
                        <DetailItem
                          label="WhatsApp app status"
                          value={friendlyStatus(connection.coexistence_status, 'Not checked yet')}
                        />
                        <DetailItem
                          label="Last checked"
                          value={
                            connection.coexistence_last_checked_at
                              ? new Date(connection.coexistence_last_checked_at).toLocaleString()
                              : 'Not checked'
                          }
                        />
                        <DetailItem
                          label="WhatsApp app warning"
                          value={
                            connection.coexistence_last_error
                              ? friendlyError(connection.coexistence_last_error)
                              : 'None'
                          }
                          className="sm:col-span-2"
                        />
                      </>
                    )}
                    <DetailItem
                      label="Full address"
                      value={connection.business_address || 'Not added'}
                      className="sm:col-span-2"
                    />
                  </div>
                </CardContent>
              </Card>
            )}

            {showWebhookDialog && (
              <WebhookSetupDialog
                connection={connection}
                centralWebhook={centralWebhook}
                onClose={() => setShowWebhookDialog(false)}
              />
            )}

            {showVerificationDialog && (
              <VerificationDialog onClose={() => setShowVerificationDialog(false)} />
            )}

            {
              <WabaHealthPanel
                connection={connection}
                embeddedEnabled={embeddedEnabled}
                refreshing={refreshingHealth}
                onRefresh={refreshMetaHealth}
                onWebhookSetup={() => setShowWebhookDialog(true)}
              />
            }

            {
              <div className="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
                <Card>
                  <CardHeader>
                    <CardTitle>WhatsApp preview</CardTitle>
                    <CardDescription>
                      How customers see this business profile in WhatsApp.
                    </CardDescription>
                  </CardHeader>
                  <CardContent>
                    <WhatsAppProfilePreview connection={connection} />
                  </CardContent>
                </Card>

                <Card>
                  <CardHeader>
                    <CardTitle>Verified badge services</CardTitle>
                    <CardDescription>
                      Get help applying for the green verified badge on WhatsApp.
                    </CardDescription>
                  </CardHeader>
                  <CardContent className="space-y-4">
                    <div className="rounded-card border border-blue-200 bg-blue-50 p-4 dark:border-blue-900/70 dark:bg-blue-950/20">
                      <div className="flex items-start gap-3">
                        <BadgeCheck className="mt-0.5 h-5 w-5 flex-shrink-0 text-blue-700 dark:text-blue-300" />
                        <div>
                          <h3 className="text-sm font-semibold text-blue-950 dark:text-blue-100">
                            Blue-tick assistance
                          </h3>
                          <p className="mt-1 text-sm text-blue-800 dark:text-blue-200">
                            We can help prepare business verification, profile completeness, policy
                            readiness, and submission support. Meta makes the final approval or
                            subscription decision.
                          </p>
                        </div>
                      </div>
                    </div>
                    <ul className="space-y-2 text-sm text-ui-text-muted dark:text-ui-dark-text-muted">
                      {[
                        'Business Manager verification readiness',
                        'Display name and profile review',
                        'Policy and website/domain checks',
                        'Meta Verified and Official Business Account guidance',
                      ].map((item) => (
                        <li key={item} className="flex items-center gap-2">
                          <CheckCircle2 className="h-4 w-4 text-ui-green" />
                          {item}
                        </li>
                      ))}
                    </ul>
                    <Button
                      type="button"
                      onClick={() => setShowVerificationDialog(true)}
                      className="w-full"
                    >
                      <BadgeCheck className="h-4 w-4" />
                      View service details
                    </Button>
                  </CardContent>
                </Card>
              </div>
            }

            {
              <Card>
                <CardHeader>
                  <CardTitle>Business profile</CardTitle>
                  <CardDescription>
                    What customers see when they open your business profile in WhatsApp.
                  </CardDescription>
                </CardHeader>
                <CardContent>
                  <form onSubmit={submitProfile} className="space-y-5">
                    <div className="flex flex-col gap-4 rounded-card border border-ui-border bg-gray-50 p-4 dark:border-ui-dark-border dark:bg-slate-900/60 sm:flex-row sm:items-center">
                      <div className="flex h-20 w-20 flex-shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-ui-border bg-white dark:border-ui-dark-border dark:bg-slate-950">
                        {connection.profile_picture_url ? (
                          <img
                            src={connection.profile_picture_url}
                            alt=""
                            className="h-full w-full object-cover"
                          />
                        ) : (
                          <Image className="h-8 w-8 text-ui-text-muted dark:text-ui-dark-text-muted" />
                        )}
                      </div>
                      <div className="min-w-0 flex-1">
                        <div className="text-sm font-semibold text-ui-text dark:text-ui-dark-text">
                          Profile photo
                        </div>
                        <p className="mt-1 text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
                          Upload a square JPG or PNG. It shows on WhatsApp after you publish your
                          profile.
                        </p>
                        <input
                          type="file"
                          accept="image/jpeg,image/png"
                          onChange={(event) =>
                            profileForm.setData(
                              'profile_picture_file',
                              event.currentTarget.files?.[0] ?? null
                            )
                          }
                          className="mt-3 block w-full text-xs text-ui-text-muted file:mr-3 file:rounded-btn file:border-0 file:bg-ui-green file:px-3 file:py-2 file:text-xs file:font-semibold file:text-white dark:text-ui-dark-text-muted"
                        />
                        <InputError
                          message={profileForm.errors.profile_picture_file}
                          className="mt-1"
                        />
                      </div>
                    </div>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                      <Field
                        icon={<Building2 className="h-3.5 w-3.5" />}
                        label="Business display name"
                        value={profileForm.data.name}
                        onChange={(value) => profileForm.setData('name', value)}
                        error={profileForm.errors.name}
                        required
                      />
                      <SelectField
                        label="Business category"
                        value={profileForm.data.business_vertical}
                        onChange={(value) => profileForm.setData('business_vertical', value)}
                        error={profileForm.errors.business_vertical}
                      />
                      <Field
                        icon={<Phone className="h-3.5 w-3.5" />}
                        label="Business phone"
                        value={profileForm.data.business_phone}
                        onChange={(value) => profileForm.setData('business_phone', value)}
                        error={profileForm.errors.business_phone}
                      />
                      <Field
                        icon={<UserCheck className="h-3.5 w-3.5" />}
                        label="WhatsApp username"
                        value={profileForm.data.business_username}
                        onChange={(value) => profileForm.setData('business_username', value)}
                        error={profileForm.errors.business_username}
                        placeholder="@yourbrand"
                      />
                      <Field
                        icon={<Mail className="h-3.5 w-3.5" />}
                        label="Contact email"
                        value={profileForm.data.business_email}
                        onChange={(value) => profileForm.setData('business_email', value)}
                        error={profileForm.errors.business_email}
                        type="email"
                      />
                      <Field
                        icon={<MapPin className="h-3.5 w-3.5" />}
                        label="Business address"
                        value={profileForm.data.business_address}
                        onChange={(value) => profileForm.setData('business_address', value)}
                        error={profileForm.errors.business_address}
                        className="sm:col-span-2"
                      />
                      <TextAreaField
                        label="About"
                        value={profileForm.data.business_about}
                        onChange={(value) => profileForm.setData('business_about', value)}
                        error={profileForm.errors.business_about}
                        maxLength={139}
                        className="sm:col-span-2"
                      />
                      <TextAreaField
                        label="Business description"
                        value={profileForm.data.business_description}
                        onChange={(value) => profileForm.setData('business_description', value)}
                        error={profileForm.errors.business_description}
                        maxLength={512}
                        className="sm:col-span-2"
                      />
                      <Field
                        icon={<Globe2 className="h-3.5 w-3.5" />}
                        label="Website 1"
                        value={profileForm.data.business_websites?.[0] ?? ''}
                        onChange={(value) => updateWebsite(0, value)}
                        error={(profileForm.errors as any)['business_websites.0']}
                        placeholder="https://example.com"
                      />
                      <Field
                        icon={<Globe2 className="h-3.5 w-3.5" />}
                        label="Website 2"
                        value={profileForm.data.business_websites?.[1] ?? ''}
                        onChange={(value) => updateWebsite(1, value)}
                        error={(profileForm.errors as any)['business_websites.1']}
                        placeholder="https://instagram.com/yourbrand"
                      />
                    </div>

                    <div className="flex flex-wrap items-center justify-between gap-3 border-t border-ui-border pt-4 dark:border-ui-dark-border">
                      <div className="space-y-1 text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
                        {connection.profile_synced_at && (
                          <div>
                            Last published to WhatsApp:{' '}
                            {new Date(connection.profile_synced_at).toLocaleString()}
                          </div>
                        )}
                        {connection.profile_sync_error && (
                          <div className="text-amber-700 dark:text-amber-300">
                            Last update didn't reach WhatsApp:{' '}
                            {friendlyError(connection.profile_sync_error)}
                          </div>
                        )}
                      </div>
                      <div className="flex flex-wrap gap-2">
                        <Button
                          type="submit"
                          size="sm"
                          variant="secondary"
                          disabled={profileForm.processing}
                          title={`Save in ${brandName()} without changing your WhatsApp profile yet`}
                        >
                          {profileForm.processing ? (
                            <Loader2 className="h-4 w-4 animate-spin" />
                          ) : (
                            <CheckCircle2 className="h-4 w-4" />
                          )}
                          Save draft
                        </Button>
                        <Button
                          type="button"
                          size="sm"
                          disabled={profileForm.processing}
                          onClick={syncProfileToMeta}
                        >
                          {profileForm.processing ? (
                            <Loader2 className="h-4 w-4 animate-spin" />
                          ) : (
                            <RefreshCw className="h-4 w-4" />
                          )}
                          Save & publish to WhatsApp
                        </Button>
                      </div>
                    </div>
                  </form>
                </CardContent>
              </Card>
            }

            <p className="text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
              <span className="font-semibold text-ui-text dark:text-ui-dark-text">
                Daily limit:
              </span>{' '}
              WhatsApp sets how many new customers you can message a day. It grows as you send good
              messages.
            </p>
          </section>
        )}
        <ManualConnectDialog open={showAutoDialog} onClose={() => setShowAutoDialog(false)} />
        <DisconnectWabaDialog
          open={showDisconnectDialog}
          onClose={() => setShowDisconnectDialog(false)}
          onConfirm={disconnectWaba}
          processing={disconnecting}
          connection={connection}
        />
      </div>
    </AppShell>
  );
}
function WabaHealthPanel({
  connection,
  embeddedEnabled,
  refreshing,
  onRefresh,
  onWebhookSetup,
}: {
  connection: Connection;
  embeddedEnabled: boolean;
  refreshing: boolean;
  onRefresh: () => void;
  onWebhookSetup: () => void;
}) {
  const checks = [
    {
      label: 'WhatsApp sign-up',
      desc: embeddedEnabled
        ? 'WhatsApp sign-up is available for this workspace.'
        : 'WhatsApp sign-up is not available yet. Please contact support.',
      ok: embeddedEnabled,
    },
    {
      label: 'Business number linked',
      desc:
        connection.waba_id && connection.phone_number_id
          ? 'Your WhatsApp Business account and number are linked.'
          : 'Some account details are missing. Reconnect WhatsApp to fix this.',
      ok: Boolean(connection.waba_id && connection.phone_number_id),
    },
    {
      label: 'Number registered',
      desc:
        connection.phone_number_status || connection.code_verification_status
          ? `Status: ${friendlyStatus(connection.phone_number_status || connection.code_verification_status)}`
          : 'We have not checked your number with WhatsApp yet. Click "Check again".',
      ok: Boolean(connection.phone_number_status || connection.code_verification_status),
    },
    {
      label: 'Account review',
      desc:
        connection.meta_account_review_status || connection.meta_business_verification_status
          ? `Status: ${friendlyStatus(connection.meta_account_review_status || connection.meta_business_verification_status)}`
          : 'We have not checked your account review with WhatsApp yet. Click "Check again".',
      ok: Boolean(
        connection.meta_account_review_status || connection.meta_business_verification_status
      ),
    },
    {
      label: 'Business profile',
      desc: connection.profile_sync_error
        ? friendlyError(connection.profile_sync_error)
        : connection.profile_synced_at
          ? 'Your profile is published on WhatsApp.'
          : `Your profile is saved in ${brandName()}. Publish it so customers see it on WhatsApp.`,
      ok: Boolean(connection.profile_synced_at && !connection.profile_sync_error),
    },
    {
      label: 'Receiving messages',
      desc: connection.webhook_subscribed
        ? `New messages and delivery updates are reaching ${brandName()}.`
        : `${brandName()} is not receiving new messages from WhatsApp yet. Turn on message updates to fix this.`,
      ok: Boolean(connection.webhook_subscribed),
      action: onWebhookSetup,
    },
    ...(connection.connection_mode === 'coexistence'
      ? [
          {
            label: 'WhatsApp Business app',
            desc: connection.coexistence_last_error
              ? friendlyError(connection.coexistence_last_error)
              : connection.coexistence_status
                ? `Status: ${friendlyStatus(connection.coexistence_status)}`
                : 'Click "Check again" to confirm your WhatsApp Business app is linked.',
            ok: Boolean(connection.coexistence_status && !connection.coexistence_last_error),
          },
        ]
      : []),
  ];
  const readyCount = checks.filter((check) => check.ok).length;
  const allChecksReady = readyCount === checks.length;
  if (allChecksReady) {
    return null;
  }
  return (
    <Card>
      <CardHeader className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <CardTitle>Connection health</CardTitle>
          <CardDescription>
            A few things need a look before everything works smoothly.
          </CardDescription>
        </div>
        <div className="flex items-center gap-2">
          <Badge variant="warning">
            {readyCount} of {checks.length} ready
          </Badge>
          <Button
            type="button"
            size="sm"
            variant="secondary"
            onClick={onRefresh}
            disabled={refreshing}
          >
            {refreshing ? (
              <Loader2 className="h-4 w-4 animate-spin" />
            ) : (
              <RefreshCw className="h-4 w-4" />
            )}
            Check again
          </Button>
        </div>
      </CardHeader>
      <CardContent>
        <div className="grid gap-3 md:grid-cols-2">
          {checks.map((check) => (
            <div
              key={check.label}
              className="flex items-start gap-3 rounded-card border border-ui-border bg-gray-50 p-4 dark:border-ui-dark-border dark:bg-slate-900/70"
            >
              <span
                className={`mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full ${check.ok ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-200' : 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-200'}`}
              >
                {check.ok ? (
                  <CheckCircle2 className="h-4 w-4" />
                ) : (
                  <AlertTriangle className="h-4 w-4" />
                )}
              </span>
              <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center gap-2">
                  <h3 className="text-sm font-semibold text-ui-text dark:text-ui-dark-text">
                    {check.label}
                  </h3>
                  <Badge variant={check.ok ? 'success' : 'warning'}>
                    {check.ok ? 'Ready' : 'Needs attention'}
                  </Badge>
                </div>
                <p className="mt-1 text-sm text-ui-text-muted dark:text-ui-dark-text-muted">
                  {check.desc}
                </p>
                {check.action && (
                  <Button
                    type="button"
                    variant="link"
                    size="sm"
                    className="mt-2 h-auto p-0"
                    onClick={check.action}
                  >
                    Fix this
                  </Button>
                )}
              </div>
            </div>
          ))}
        </div>
      </CardContent>
    </Card>
  );
}
function StatusPill({ icon, label, value }: { icon: ReactNode; label: string; value: string }) {
  return (
    <span className="inline-flex items-center gap-1.5 rounded-full bg-white/70 px-2.5 py-1 text-ui-text dark:bg-slate-800/80 dark:text-ui-dark-text">
      {icon}
      {label} <strong>{value}</strong>
    </span>
  );
}
function SetupWizardDialog({
  open,
  onClose,
  embeddedEnabled,
  embeddedReady,
  autoStatus,
  autoMessage,
  automaticDisabled,
  automaticButtonLabel,
  startEmbeddedSignup,
  confirmEmbeddedReview,
  pendingEmbeddedReview,
  connection,
  setupPath,
  setSignupMode,
  setSetupPath,
  coexistenceEnabled,
}: {
  open: boolean;
  onClose: () => void;
  embeddedEnabled: boolean;
  embeddedReady: boolean;
  autoStatus: 'idle' | 'loading' | 'authorizing' | 'saving' | 'error';
  autoMessage: string;
  automaticDisabled: boolean;
  automaticButtonLabel: string;
  startEmbeddedSignup: () => void;
  confirmEmbeddedReview: () => void;
  pendingEmbeddedReview: PendingEmbeddedReview | null;
  connection: Connection | null;
  setupPath: SetupPath;
  signupMode: 'cloud_api' | 'coexistence';
  setSignupMode: (mode: 'cloud_api' | 'coexistence') => void;
  setSetupPath: (path: SetupPath) => void;
  coexistenceEnabled: boolean;
}) {
  const options: Array<{
    path: SetupPath;
    mode: 'cloud_api' | 'coexistence';
    label: string;
    hint: string;
    icon: ReactNode;
    disabled?: boolean;
  }> = [
    {
      path: 'new_cloud_api',
      mode: 'cloud_api',
      label: 'New number',
      hint: 'Not on WhatsApp yet',
      icon: <Sparkles className="h-4 w-4" />,
    },
    {
      path: 'migrate_api',
      mode: 'cloud_api',
      label: 'Move from another provider',
      hint: 'Keep your number and chats',
      icon: <RefreshCw className="h-4 w-4" />,
    },
    {
      path: 'coexistence',
      mode: 'coexistence',
      label: 'WhatsApp Business app',
      hint: 'Keep using the app on your phone',
      icon: <MessageCircle className="h-4 w-4" />,
      disabled: !coexistenceEnabled,
    },
  ];
  const busy = autoStatus === 'loading' || autoStatus === 'authorizing' || autoStatus === 'saving';
  const statusText = !embeddedEnabled
    ? 'Not available yet. Contact support and we will connect you.'
    : !embeddedReady && autoStatus !== 'error'
      ? 'Getting ready...'
      : autoMessage;
  const showStatus =
    !embeddedEnabled || autoStatus === 'error' || busy || (!embeddedReady && embeddedEnabled);
  const selectedLabel = options.find((option) => option.path === setupPath)?.label ?? 'New number';
  return (
    <Modal
      open={open}
      onClose={onClose}
      title={connection ? 'Reconnect WhatsApp' : 'Connect WhatsApp'}
      description={
        pendingEmbeddedReview
          ? 'Check the number, then confirm.'
          : 'Pick your number type, then sign in with Facebook.'
      }
      className="max-w-md"
      footer={
        <>
          <Button type="button" variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button
            type="button"
            onClick={pendingEmbeddedReview ? confirmEmbeddedReview : startEmbeddedSignup}
            disabled={pendingEmbeddedReview ? autoStatus === 'saving' : automaticDisabled}
          >
            {busy ? (
              <Loader2 className="h-4 w-4 animate-spin" />
            ) : pendingEmbeddedReview ? (
              <CheckCircle2 className="h-4 w-4" />
            ) : (
              <UserCheck className="h-4 w-4" />
            )}
            {automaticButtonLabel}
          </Button>
        </>
      }
    >
      <div className="space-y-3">
        {pendingEmbeddedReview ? (
          <div className="rounded-card border border-ui-green/30 bg-ui-green/5 p-4 dark:border-ui-green/40 dark:bg-ui-green/10">
            <div className="flex items-center gap-3">
              <span className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-ui-green text-white">
                <Phone className="h-5 w-5" />
              </span>
              <div className="min-w-0">
                <div className="truncate text-base font-semibold text-ui-text dark:text-ui-dark-text">
                  {pendingEmbeddedReview.summary.businessPhone || 'Your WhatsApp number'}
                </div>
                <div className="text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
                  {pendingEmbeddedReview.summary.mode === 'coexistence'
                    ? 'WhatsApp Business app'
                    : selectedLabel}
                </div>
              </div>
            </div>
            <details className="mt-3 text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
              <summary className="cursor-pointer list-none font-medium text-ui-green-dark hover:underline dark:text-emerald-300">
                Details for support
              </summary>
              <div className="mt-2 space-y-1 font-mono">
                <div>
                  Number ID: {pendingEmbeddedReview.summary.phoneNumberId || 'Not available'}
                </div>
                <div>Account ID: {pendingEmbeddedReview.summary.wabaId || 'Not available'}</div>
              </div>
            </details>
            <p className="mt-2 text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
              Wrong number? Cancel and start again.
            </p>
          </div>
        ) : (
          <div role="radiogroup" aria-label="Number type" className="space-y-2">
            {options.map((option) => {
              const selected = setupPath === option.path;
              return (
                <button
                  key={option.path}
                  type="button"
                  role="radio"
                  aria-checked={selected}
                  disabled={option.disabled}
                  onClick={() => {
                    setSetupPath(option.path);
                    setSignupMode(option.mode);
                  }}
                  className={`flex w-full items-center gap-3 rounded-card border px-3 py-2.5 text-left transition ${
                    option.disabled
                      ? 'cursor-not-allowed border-dashed border-ui-border opacity-60 dark:border-ui-dark-border'
                      : selected
                        ? 'border-ui-green bg-ui-green/5 ring-1 ring-ui-green/30 dark:bg-ui-green/10'
                        : 'border-ui-border bg-white hover:border-ui-green/50 dark:border-ui-dark-border dark:bg-slate-900'
                  }`}
                >
                  <span
                    className={`flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full ${selected && !option.disabled ? 'bg-ui-green text-white' : 'bg-gray-100 text-ui-text-muted dark:bg-slate-800 dark:text-ui-dark-text-muted'}`}
                  >
                    {option.icon}
                  </span>
                  <span className="min-w-0 flex-1">
                    <span className="block text-sm font-semibold text-ui-text dark:text-ui-dark-text">
                      {option.label}
                    </span>
                    <span className="block truncate text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
                      {option.hint}
                    </span>
                  </span>
                  {option.disabled ? (
                    <span className="text-[11px] font-medium text-ui-text-muted dark:text-ui-dark-text-muted">
                      Unavailable
                    </span>
                  ) : (
                    <span
                      className={`h-4 w-4 flex-shrink-0 rounded-full border-2 ${selected ? 'border-ui-green bg-ui-green shadow-[inset_0_0_0_2px_white] dark:shadow-[inset_0_0_0_2px_#0f172a]' : 'border-gray-300 dark:border-slate-600'}`}
                      aria-hidden
                    />
                  )}
                </button>
              );
            })}
          </div>
        )}

        {showStatus && (
          <div
            className={`flex items-center gap-2 rounded-card px-3 py-2 text-xs ${autoStatus === 'error' ? 'bg-red-50 text-red-700 dark:bg-red-950/30 dark:text-red-200' : 'bg-gray-50 text-ui-text-muted dark:bg-slate-900/70 dark:text-ui-dark-text-muted'}`}
            role={autoStatus === 'error' ? 'alert' : 'status'}
          >
            {busy && <Loader2 className="h-3.5 w-3.5 animate-spin" />}
            {autoStatus === 'error' && <AlertTriangle className="h-3.5 w-3.5" />}
            <span>{statusText}</span>
          </div>
        )}
      </div>
    </Modal>
  );
}
function DisconnectWabaDialog({
  open,
  onClose,
  onConfirm,
  processing,
  connection,
}: {
  open: boolean;
  onClose: () => void;
  onConfirm: () => void;
  processing: boolean;
  connection: Connection | null;
}) {
  return (
    <Modal
      open={open}
      onClose={processing ? () => undefined : onClose}
      title="Disconnect WhatsApp?"
      description={`Your number will stop sending and receiving messages in ${brandName()}.`}
      footer={
        <>
          <Button type="button" variant="secondary" onClick={onClose} disabled={processing}>
            Cancel
          </Button>
          <Button type="button" variant="danger" onClick={onConfirm} disabled={processing}>
            {processing ? (
              <Loader2 className="h-4 w-4 animate-spin" />
            ) : (
              <Trash2 className="h-4 w-4" />
            )}
            Disconnect
          </Button>
        </>
      }
    >
      <div className="space-y-4">
        <div className="rounded-card border border-red-200 bg-red-50 p-4 dark:border-red-900/70 dark:bg-red-950/20">
          <div className="flex items-start gap-3">
            <div className="mt-0.5 flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-200">
              <AlertTriangle className="h-5 w-5" />
            </div>
            <div>
              <h4 className="text-sm font-semibold text-red-950 dark:text-red-100">
                Messages will stop until you connect again.
              </h4>
              <p className="mt-1 text-sm leading-6 text-red-800 dark:text-red-200">
                Your chats, contacts and templates stay in {brandName()}. You can connect this number, or a
                different one, at any time.
              </p>
            </div>
          </div>
        </div>

        <div className="grid gap-3 text-sm sm:grid-cols-2">
          <DetailItem
            label="Display name"
            value={connection?.meta_verified_name || connection?.name || 'Your WhatsApp account'}
          />
          <DetailItem label="Phone" value={connection?.business_phone || 'Not available'} />

          <DetailItem
            label="Receiving messages"
            value={connection?.webhook_subscribed ? 'Yes' : 'No'}
          />
        </div>

        <p className="text-sm text-ui-text-muted dark:text-ui-dark-text-muted">
          Related WhatsApp records linked to this connection, such as conversations, templates, and
          lists, may also be removed by database cascade.
        </p>
      </div>
    </Modal>
  );
}
function DetailItem({
  label,
  value,
  mono = false,
  className = '',
}: {
  label: string;
  value: string;
  mono?: boolean;
  className?: string;
}) {
  return (
    <div
      className={`rounded-card border border-ui-border bg-gray-50 p-3 dark:border-ui-dark-border dark:bg-slate-900/70 ${className}`}
    >
      <div className="text-[11px] font-semibold uppercase tracking-wider text-ui-text-muted dark:text-ui-dark-text-muted">
        {label}
      </div>
      <div
        className={`mt-1 break-words text-sm font-semibold text-ui-text dark:text-ui-dark-text ${mono ? 'font-mono' : ''}`}
      >
        {value}
      </div>
    </div>
  );
}
function WhatsAppProfilePreview({ connection }: { connection: Connection }) {
  const displayName = connection.meta_verified_name || connection.name || 'Business';
  const phone = connection.business_phone || connection.phone_number_id || '';
  const username = connection.business_username
    ? connection.business_username.replace(/^@+/, '')
    : '';
  const about = profileValue(connection.business_about, 'Business account');
  const description = profileValue(
    connection.business_description,
    'No business description added yet.'
  );
  const address = profileValue(connection.business_address, 'Address not added');
  const websites = (connection.business_websites || []).filter(Boolean);
  return (
    <div className="mx-auto max-w-sm overflow-hidden rounded-[28px] bg-[#111b21] p-2 shadow-pop">
      <div className="rounded-[22px] bg-[#0b141a] text-[#e9edef]">
        <div className="flex items-center gap-3 bg-[#075e54] px-4 py-3">
          <div className="flex h-10 w-10 items-center justify-center overflow-hidden rounded-full bg-white/20">
            {connection.profile_picture_url ? (
              <img
                src={connection.profile_picture_url}
                alt=""
                className="h-full w-full object-cover"
              />
            ) : (
              <Building2 className="h-5 w-5 text-white" />
            )}
          </div>
          <div className="min-w-0 flex-1">
            <div className="flex min-w-0 items-center gap-1">
              <span className="truncate text-sm font-semibold text-white">{displayName}</span>
            </div>
            <div className="truncate text-[11px] text-white/75">
              {username ? `@${username}` : phone}
            </div>
          </div>
        </div>

        <div className="space-y-3 p-4">
          <div className="rounded-xl bg-[#202c33] p-3">
            <div className="text-xs font-semibold uppercase tracking-wider text-[#8696a0]">
              About
            </div>
            <div className="mt-1 text-sm leading-5 text-[#e9edef]">{about}</div>
          </div>
          <div className="rounded-xl bg-[#202c33] p-3">
            <div className="text-xs font-semibold uppercase tracking-wider text-[#8696a0]">
              Business details
            </div>
            <div className="mt-2 space-y-2 text-sm text-[#d1d7db]">
              <div className="flex gap-2">
                <MapPin className="mt-0.5 h-4 w-4 flex-shrink-0 text-[#8696a0]" />
                <span>{address}</span>
              </div>
              <div className="flex gap-2">
                <MessageCircle className="mt-0.5 h-4 w-4 flex-shrink-0 text-[#8696a0]" />
                <span>{description}</span>
              </div>
              {connection.business_email && (
                <div className="flex gap-2">
                  <Mail className="mt-0.5 h-4 w-4 flex-shrink-0 text-[#8696a0]" />
                  <span>{connection.business_email}</span>
                </div>
              )}
              {websites.map((website) => (
                <div key={website} className="flex gap-2">
                  <Globe2 className="mt-0.5 h-4 w-4 flex-shrink-0 text-[#8696a0]" />
                  <span className="break-all text-[#53bdeb]">{website}</span>
                </div>
              ))}
            </div>
          </div>
          <button
            type="button"
            className="flex h-10 w-full items-center justify-center gap-2 rounded-full bg-[#00a884] text-sm font-semibold text-[#08130f]"
          >
            <MessageCircle className="h-4 w-4" />
            Message business
          </button>
        </div>
      </div>
    </div>
  );
}
function VerificationDialog({ onClose }: { onClose: () => void }) {
  return (
    <Modal
      open
      onClose={onClose}
      title="Blue-tick verification services"
      description={`Meta controls verified badge eligibility and approval. ${brandName()} can provide readiness checks and guided submission support.`}
      className="max-w-xl"
      footer={
        <Button type="button" variant="secondary" onClick={onClose}>
          Close
        </Button>
      }
    >
      <div className="space-y-4">
        <div className="flex items-start gap-3 rounded-card border border-blue-200 bg-blue-50 p-4 dark:border-blue-900/70 dark:bg-blue-950/20">
          <div className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-blue-100 dark:bg-blue-500/10">
            <BadgeCheck className="h-5 w-5 text-blue-700 dark:text-blue-300" />
          </div>
          <div>
            <h4 className="text-sm font-semibold text-blue-950 dark:text-blue-100">
              Readiness and guided submission
            </h4>
            <p className="mt-1 text-sm text-blue-800 dark:text-blue-200">
              We can review the workspace profile, business verification assets, website policy
              pages, and Meta submission readiness.
            </p>
          </div>
        </div>
        <div className="rounded-card border border-ui-border bg-gray-50 p-4 dark:border-ui-dark-border dark:bg-slate-900/70">
          <h4 className="text-sm font-semibold text-ui-text dark:text-ui-dark-text">
            What we can add
          </h4>
          <div className="mt-3 grid grid-cols-1 gap-2 text-sm text-ui-text-muted dark:text-ui-dark-text-muted">
            <div>Business verification document checklist</div>
            <div>Display name, website, domain, and policy readiness checks</div>
            <div>Meta Verified subscription guidance where available</div>
            <div>Official Business Account readiness and support handoff</div>
          </div>
        </div>
        <div className="rounded-card border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900/70 dark:bg-amber-950/20 dark:text-amber-100">
          Badge approval is not guaranteed. Meta Verified is a Meta product, and Official Business
          Account approval depends on Meta eligibility, business verification, policy compliance,
          and brand notability.
        </div>
      </div>
    </Modal>
  );
}
function WebhookSetupDialog({
  connection,
  centralWebhook,
  onClose,
}: {
  connection: Connection;
  centralWebhook: CentralWebhook;
  onClose: () => void;
}) {
  const subscribeWebhook = () => {
    router.post(
      route('app.whatsapp.connections.subscribe-webhook', {
        connection: connection.slug ?? connection.id,
      }),
      {},
      {
        preserveScroll: true,
      }
    );
  };
  const unsubscribeWebhook = () => {
    router.delete(
      route('app.whatsapp.connections.unsubscribe-webhook', {
        connection: connection.slug ?? connection.id,
      }),
      {
        preserveScroll: true,
      }
    );
  };
  return (
    <Modal
      open
      onClose={onClose}
      title="Receive WhatsApp updates"
      description={`Make sure new customer messages and delivery updates reach ${brandName()}.`}
      className="max-w-2xl"
      footer={
        <>
          <Button type="button" variant="secondary" onClick={onClose}>
            Close
          </Button>
          {connection.webhook_subscribed ? (
            <Button type="button" variant="warning" onClick={unsubscribeWebhook}>
              Turn off message updates
            </Button>
          ) : (
            <Button type="button" onClick={subscribeWebhook}>
              Turn on message updates
            </Button>
          )}
        </>
      }
    >
      <div className="space-y-4">
        <div className="flex items-start gap-3 rounded-card border border-ui-border bg-gray-50 p-4 dark:border-ui-dark-border dark:bg-slate-900/70">
          <div
            className={`mt-0.5 flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl ${connection.webhook_subscribed ? 'bg-emerald-100 dark:bg-emerald-500/10' : 'bg-amber-100 dark:bg-amber-500/10'}`}
          >
            {connection.webhook_subscribed ? (
              <CheckCircle2 className="h-5 w-5 text-emerald-700 dark:text-emerald-300" />
            ) : (
              <AlertTriangle className="h-5 w-5 text-amber-700 dark:text-amber-300" />
            )}
          </div>
          <div>
            <Badge variant={connection.webhook_subscribed ? 'success' : 'warning'}>
              {connection.webhook_subscribed ? 'Receiving messages' : 'Not receiving messages'}
            </Badge>
            <p className="mt-2 text-sm text-ui-text-muted dark:text-ui-dark-text-muted">
              WhatsApp sends new messages and delivery updates to {brandName()} automatically once this is
              on.
            </p>
          </div>
        </div>

        <div className="rounded-card border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-900/70 dark:bg-emerald-950/20">
          <h4 className="text-sm font-semibold text-emerald-900 dark:text-emerald-100">
            Advanced details
          </h4>
          <p className="mt-1 text-sm text-emerald-800 dark:text-emerald-200">
            You do not need to set anything up. These details are only for advanced setups or if our
            support team asks for them.
          </p>
          <div className="mt-3 grid grid-cols-1 gap-3">
            <ReadOnlyCopyField label="Callback URL" value={centralWebhook.url} />
            <ReadOnlyCopyField label="Verification token" value={centralWebhook.verify_token} />
          </div>
        </div>

        <div className="rounded-card border border-ui-border bg-white p-3 text-xs text-ui-text-muted dark:border-ui-dark-border dark:bg-slate-900 dark:text-ui-dark-text-muted">
          {connection.webhook_last_received_at ? (
            <span>
              Last update received: {new Date(connection.webhook_last_received_at).toLocaleString()}
            </span>
          ) : (
            <span>No messages or updates have arrived yet.</span>
          )}
          {connection.webhook_last_error && (
            <div className="mt-1 text-red-600 dark:text-red-300">
              Last problem: {friendlyError(connection.webhook_last_error)}
            </div>
          )}
        </div>

        <div className="rounded-card border border-ui-border bg-white p-4 dark:border-ui-dark-border dark:bg-slate-900">
          <div className="text-sm font-semibold text-ui-text dark:text-ui-dark-text">
            Recent WhatsApp updates
          </div>
          {connection.meta_event_logs?.length ? (
            <div className="mt-3 divide-y divide-gray-100 dark:divide-ui-dark-border">
              {connection.meta_event_logs.map((event) => (
                <div key={event.id} className="py-2 text-xs">
                  <div className="flex items-center justify-between gap-3">
                    <span className="font-medium text-ui-text dark:text-ui-dark-text">
                      {event.field || 'event'}
                      {event.event_type ? ` / ${event.event_type}` : ''}
                    </span>
                    <span className="shrink-0 text-[11px] text-ui-text-muted dark:text-ui-dark-text-muted">
                      {event.received_at
                        ? new Date(event.received_at).toLocaleString()
                        : 'Just now'}
                    </span>
                  </div>
                  {(event.status || event.message) && (
                    <div className="mt-1 text-ui-text-muted dark:text-ui-dark-text-muted">
                      {[event.status, event.message].filter(Boolean).join(' - ')}
                    </div>
                  )}
                </div>
              ))}
            </div>
          ) : (
            <p className="mt-2 text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
              No updates from WhatsApp yet. They will show here once customers start messaging you.
            </p>
          )}
        </div>
      </div>
    </Modal>
  );
}
function Field({
  icon,
  label,
  value,
  onChange,
  error,
  required = false,
  type = 'text',
  className = '',
  placeholder = '',
}: {
  icon?: ReactNode;
  label: string;
  value: string | number | null | undefined;
  onChange: (value: string) => void;
  error?: string;
  required?: boolean;
  type?: string;
  className?: string;
  placeholder?: string;
}) {
  return (
    <div className={className}>
      <label className="mb-1.5 block text-xs font-medium text-ui-text dark:text-ui-dark-text">
        {label}
        {required && <span className="text-red-500"> *</span>}
      </label>
      <div className="relative">
        {icon && (
          <span className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-ui-text-muted dark:text-ui-dark-text-muted">
            {icon}
          </span>
        )}
        <input
          type={type}
          value={value ?? ''}
          placeholder={placeholder}
          onChange={(event) => onChange(event.target.value)}
          className={`w-full rounded-card border border-ui-border bg-white py-2 text-sm text-ui-text shadow-sm placeholder:text-ui-text-muted/60 dark:border-ui-dark-border dark:bg-slate-900 dark:text-ui-dark-text dark:placeholder:text-ui-dark-text-muted/60 ${icon ? 'pl-9 pr-3' : 'px-3'}`}
        />
      </div>
      <InputError message={error} className="mt-1" />
    </div>
  );
}
function TextAreaField({
  label,
  value,
  onChange,
  error,
  maxLength,
  className = '',
}: {
  label: string;
  value: string | null | undefined;
  onChange: (value: string) => void;
  error?: string;
  maxLength?: number;
  className?: string;
}) {
  const length = String(value ?? '').length;
  return (
    <div className={className}>
      <div className="mb-1.5 flex items-center justify-between gap-3">
        <label className="block text-xs font-medium text-ui-text dark:text-ui-dark-text">
          {label}
        </label>
        {maxLength && (
          <span className="text-[11px] text-ui-text-muted dark:text-ui-dark-text-muted">
            {length}/{maxLength}
          </span>
        )}
      </div>
      <textarea
        value={value ?? ''}
        maxLength={maxLength}
        onChange={(event) => onChange(event.target.value)}
        rows={3}
        className="w-full resize-none rounded-card border border-ui-border bg-white px-3 py-2 text-sm text-ui-text shadow-sm placeholder:text-ui-text-muted/60 dark:border-ui-dark-border dark:bg-slate-900 dark:text-ui-dark-text dark:placeholder:text-ui-dark-text-muted/60"
      />
      <InputError message={error} className="mt-1" />
    </div>
  );
}
function ReadOnlyCopyField({ label, value }: { label: string; value: string }) {
  const { addToast } = useToast();
  const copy = () => {
    if (!value) return;
    navigator.clipboard.writeText(value);
    addToast({ title: `${label} copied`, variant: 'success' });
  };
  return (
    <div>
      <label className="mb-1.5 block text-xs font-medium text-ui-text dark:text-ui-dark-text">
        {label}
      </label>
      <div className="flex overflow-hidden rounded-card border border-ui-border bg-white shadow-sm dark:border-ui-dark-border dark:bg-slate-900">
        <input
          readOnly
          value={value}
          className="min-w-0 flex-1 border-0 bg-transparent px-3 py-2 font-mono text-xs text-ui-text outline-none dark:text-ui-dark-text"
        />
        <button
          type="button"
          onClick={copy}
          className="border-l border-ui-border px-3 text-xs font-semibold text-ui-green-dark hover:bg-ui-green-soft dark:border-ui-dark-border dark:text-ui-green dark:hover:bg-ui-green/10"
        >
          Copy
        </button>
      </div>
    </div>
  );
}
function SelectField({
  label,
  value,
  onChange,
  error,
}: {
  label: string;
  value: string | null | undefined;
  onChange: (value: string) => void;
  error?: string;
}) {
  return (
    <div>
      <label className="mb-1.5 block text-xs font-medium text-ui-text dark:text-ui-dark-text">
        {label}
      </label>
      <select
        value={value || 'RETAIL'}
        onChange={(event) => onChange(event.target.value)}
        className="w-full rounded-card border border-ui-border bg-white px-3 py-2 text-sm text-ui-text shadow-sm dark:border-ui-dark-border dark:bg-slate-900 dark:text-ui-dark-text"
      >
        {BUSINESS_VERTICALS.map((vertical) => (
          <option key={vertical.value} value={vertical.value}>
            {vertical.label}
          </option>
        ))}
      </select>
      <InputError message={error} className="mt-1" />
    </div>
  );
}
const BUSINESS_VERTICALS = [
  { value: 'AUTO', label: 'Automotive' },
  { value: 'BEAUTY', label: 'Beauty, spa and salon' },
  { value: 'APPAREL', label: 'Clothing and apparel' },
  { value: 'EDU', label: 'Education' },
  { value: 'ENTERTAIN', label: 'Entertainment' },
  { value: 'EVENT_PLAN', label: 'Event planning' },
  { value: 'FINANCE', label: 'Finance and banking' },
  { value: 'GROCERY', label: 'Grocery' },
  { value: 'GOVT', label: 'Government' },
  { value: 'HOTEL', label: 'Hotel and lodging' },
  { value: 'HEALTH', label: 'Healthcare' },
  { value: 'NONPROFIT', label: 'Nonprofit' },
  { value: 'PROF_SERVICES', label: 'Professional services' },
  { value: 'RETAIL', label: 'Retail' },
  { value: 'TRAVEL', label: 'Travel and transportation' },
  { value: 'RESTAURANT', label: 'Restaurant' },
  { value: 'NOT_A_BIZ', label: 'Not a business' },
  { value: 'OTHER', label: 'Other' },
];

/** Show Meta's business category code ("PROF_SERVICES") as words. */
function verticalLabel(value?: string | null): string {
  if (!value) {
    return '';
  }
  const match = BUSINESS_VERTICALS.find((item) => item.value === value);
  return match ? match.label : friendlyStatus(value, '');
}
