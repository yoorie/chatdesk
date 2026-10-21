import { brandName } from '../../lib/brand';
export default function AppLogo({
  size = 30,
  withText = true,
  textClassName = 'text-ui-text',
}: {
  size?: number;
  withText?: boolean;
  textClassName?: string;
}) {
  return (
    <span className="inline-flex items-center gap-2">
      <svg viewBox="0 0 40 40" width={size} height={size} aria-hidden="true">
        <defs>
          <linearGradient id={`appLogo${size}`} x1="0" y1="0" x2="1" y2="1">
            <stop offset="0%" stopColor="#047857" />
            <stop offset="100%" stopColor="#0F766E" />
          </linearGradient>
        </defs>
        <rect x="4" y="5" width="32" height="25" rx="8" fill={`url(#appLogo${size})`} />
        <path d="M12 30 L12 36 L19 30 Z" fill={`url(#appLogo${size})`} />
        <circle cx="13" cy="17.5" r="2.3" fill="white" />
        <circle cx="20" cy="17.5" r="2.3" fill="white" />
        <circle cx="27" cy="17.5" r="2.3" fill="white" />
      </svg>
      {withText && (
        <span className={`text-lg font-bold tracking-tight ${textClassName}`}>{brandName()}</span>
      )}
    </span>
  );
}
