import { usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

const CONSENT_KEY = 'app.cookie-consent';
const LEGACY_CONSENT_KEY = 'legacy.cookie-consent';
const CONSENT_EVENT = 'app:cookie-consent';
const LEGACY_CONSENT_EVENT = 'legacy:cookie-consent';

export default function AnalyticsScripts() {
  const { analyticsSettings, compliance, features } = usePage().props as any;
  const [consentState, setConsentState] = useState<string | null>(null);

  const consentRequired = compliance?.cookie_consent_required ?? false;
  const consentGranted = useMemo(() => {
    if (!consentRequired) return true;
    if (consentState === null) return false;
    return consentState === 'accepted';
  }, [consentRequired, consentState]);

  useEffect(() => {
    if (!consentRequired) {
      setConsentState('accepted');
      return;
    }
    const stored =
      window.localStorage.getItem(CONSENT_KEY) ?? window.localStorage.getItem(LEGACY_CONSENT_KEY);
    setConsentState(stored);

    const handler = (event: Event) => {
      const detail = (event as CustomEvent).detail;
      if (detail === 'accepted' || detail === 'declined') {
        setConsentState(detail);
      }
    };
    window.addEventListener(CONSENT_EVENT, handler as EventListener);
    window.addEventListener(LEGACY_CONSENT_EVENT, handler as EventListener);
    return () => {
      window.removeEventListener(CONSENT_EVENT, handler as EventListener);
      window.removeEventListener(LEGACY_CONSENT_EVENT, handler as EventListener);
    };
  }, [consentRequired]);

  useEffect(() => {
    if (!consentGranted || !analyticsSettings) return;
    if (features && features.analytics === false) return;

    const {
      google_analytics_enabled: gaEnabled,
      google_analytics_id: gaId,
      mixpanel_enabled: mixpanelEnabled,
      mixpanel_token: mixpanelToken,
    } = analyticsSettings;

    if (gaEnabled && gaId && !document.getElementById('ga-script')) {
      const script = document.createElement('script');
      script.id = 'ga-script';
      script.async = true;
      script.src = `https://www.googletagmanager.com/gtag/js?id=${gaId}`;
      document.head.appendChild(script);

      const inline = document.createElement('script');
      inline.id = 'ga-inline';
      inline.textContent = `
                window.dataLayer = window.dataLayer || [];
                function gtag(){dataLayer.push(arguments);}
                gtag('js', new Date());
                gtag('config', ${JSON.stringify(String(gaId))});
            `;
      document.head.appendChild(inline);
    }

    if (mixpanelEnabled && mixpanelToken && !document.getElementById('mixpanel-script')) {
      const script = document.createElement('script');
      script.id = 'mixpanel-script';
      script.textContent = `
                (function(f,b){if(!b.__SV){var e,g,i,h;window.mixpanel=b;b._i=[];b.init=function(e,f,c){function g(a,d){var b=d.split(\".\");2==b.length&&(a=a[b[0]],d=b[1]);a[d]=function(){a.push([d].concat(Array.prototype.slice.call(arguments,0)))}}var a=b;\"undefined\"!==typeof c?a=b[c]=[]:c=\"mixpanel\";a.people=a.people||[];a.toString=function(a){var d=\"mixpanel\";\"mixpanel\"!==c&&(d+=\".\"+c);a||(d+=\" (stub)\");return d};a.people.toString=function(){return a.toString(1)+\".people (stub)\"};i=\"disable time_event track track_pageview track_links track_forms register register_once alias unregister identify name_tag set_config reset people.set people.set_once people.unset people.increment people.append people.union people.track_charge people.clear_charges people.delete_user\".split(\" \");for(h=0;h<i.length;h++)g(a,i[h]);b._i.push([e,f,c])};b.__SV=1.2;e=f.createElement(\"script\");e.type=\"text/javascript\";e.async=!0;e.src=\"https://cdn.mxpnl.com/libs/mixpanel-2-latest.min.js\";g=f.getElementsByTagName(\"script\")[0];g.parentNode.insertBefore(e,g)}})(document,window.mixpanel||[]);
                mixpanel.init(${JSON.stringify(String(mixpanelToken))});
            `;
      document.head.appendChild(script);
    }
  }, [analyticsSettings, consentGranted, features]);

  return null;
}
