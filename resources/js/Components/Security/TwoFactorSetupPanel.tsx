import QRCode from 'qrcode';
import { useEffect, useState } from 'react';

type TwoFactorSetup = {
  secret: string;
  otpauth_url: string;
};

export default function TwoFactorSetupPanel({ setup }: { setup: TwoFactorSetup }) {
  const [qrDataUrl, setQrDataUrl] = useState<string | null>(null);
  const [qrError, setQrError] = useState(false);

  useEffect(() => {
    let active = true;

    setQrDataUrl(null);
    setQrError(false);

    QRCode.toDataURL(setup.otpauth_url, {
      errorCorrectionLevel: 'M',
      margin: 2,
      scale: 6,
      color: {
        dark: '#111827',
        light: '#ffffff',
      },
    })
      .then((url) => {
        if (active) setQrDataUrl(url);
      })
      .catch(() => {
        if (active) setQrError(true);
      });

    return () => {
      active = false;
    };
  }, [setup.otpauth_url]);

  return (
    <div className="rounded-card border border-gray-100 bg-gray-50 p-3 dark:border-ui-dark-border dark:bg-ui-dark-surface-2">
      <div className="grid gap-4 sm:grid-cols-[164px,minmax(0,1fr)]">
        <div className="flex h-[164px] w-[164px] items-center justify-center rounded-card border border-gray-200 bg-white p-2 dark:border-slate-700">
          {qrDataUrl ? (
            <img src={qrDataUrl} alt="Authenticator QR code" className="h-full w-full" />
          ) : (
            <div className="px-3 text-center text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
              {qrError ? 'QR unavailable' : 'Preparing QR...'}
            </div>
          )}
        </div>
        <div className="min-w-0">
          <div className="text-xs font-semibold uppercase tracking-wide text-ui-text-muted dark:text-ui-dark-text-muted">
            Authenticator setup
          </div>
          <p className="mt-1 text-sm text-ui-text-muted dark:text-ui-dark-text-muted">
            Scan this QR code in Google Authenticator, Microsoft Authenticator, 1Password, or
            another TOTP app.
          </p>
          <div className="mt-3 text-xs font-semibold uppercase tracking-wide text-ui-text-muted dark:text-ui-dark-text-muted">
            Manual key
          </div>
          <code className="mt-2 block break-all rounded-btn bg-white p-2 font-mono text-xs dark:bg-slate-900">
            {setup.secret}
          </code>
        </div>
      </div>
    </div>
  );
}
