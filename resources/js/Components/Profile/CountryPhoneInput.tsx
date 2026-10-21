import InputError from '@/Components/InputError';
import { Phone } from 'lucide-react';

const countryCodes = [
  { code: '+91', country: 'India', timezone: 'Asia/Kolkata', placeholder: '99887 76655' },
  {
    code: '+1',
    country: 'United States',
    timezone: 'America/New_York',
    placeholder: '415 555 0199',
  },
  { code: '+44', country: 'United Kingdom', timezone: 'Europe/London', placeholder: '7400 123456' },
  {
    code: '+971',
    country: 'United Arab Emirates',
    timezone: 'Asia/Dubai',
    placeholder: '50 123 4567',
  },
  { code: '+65', country: 'Singapore', timezone: 'Asia/Singapore', placeholder: '8123 4567' },
  { code: '+61', country: 'Australia', timezone: 'Australia/Sydney', placeholder: '412 345 678' },
  { code: '+49', country: 'Germany', timezone: 'Europe/Berlin', placeholder: '1512 3456789' },
  { code: '+33', country: 'France', timezone: 'Europe/Paris', placeholder: '6 12 34 56 78' },
  { code: '+81', country: 'Japan', timezone: 'Asia/Tokyo', placeholder: '90 1234 5678' },
  { code: '+880', country: 'Bangladesh', timezone: 'Asia/Dhaka', placeholder: '1712 345678' },
  { code: '+94', country: 'Sri Lanka', timezone: 'Asia/Colombo', placeholder: '77 123 4567' },
  { code: '+977', country: 'Nepal', timezone: 'Asia/Kathmandu', placeholder: '984 1234567' },
];

export function timezoneForCountryCode(countryCode: string) {
  return countryCodes.find((item) => item.code === countryCode)?.timezone || 'Asia/Kolkata';
}

export function splitPhoneNumber(phone?: string | null, preferredCode?: string | null) {
  const source = (phone || '').trim();
  const matched = countryCodes
    .slice()
    .sort((a, b) => b.code.length - a.code.length)
    .find((item) => source.startsWith(item.code));
  const countryCode = preferredCode || matched?.code || '+91';
  const localPhone = source.startsWith(countryCode)
    ? source.slice(countryCode.length).replace(/^\s+/, '')
    : source;

  return { countryCode, localPhone };
}

export default function CountryPhoneInput({
  countryCode,
  phone,
  onCountryCodeChange,
  onPhoneChange,
  error,
}: {
  countryCode: string;
  phone: string;
  onCountryCodeChange: (value: string) => void;
  onPhoneChange: (value: string) => void;
  error?: string;
}) {
  const selectedCountry = countryCodes.find((item) => item.code === countryCode) || countryCodes[0];

  return (
    <div>
      <label className="mb-1 block text-xs font-medium text-ui-text dark:text-ui-dark-text">
        Phone number
      </label>
      <div className="grid grid-cols-[minmax(120px,150px)_1fr] gap-2">
        <select
          value={countryCode}
          onChange={(event) => onCountryCodeChange(event.target.value)}
          className="h-9 w-full rounded-btn border border-gray-200 bg-white px-2 text-sm text-ui-text outline-none transition focus:border-ui-green focus:ring-2 focus:ring-ui-green/15 dark:border-slate-600 dark:bg-slate-900 dark:text-ui-dark-text"
          aria-label="Country code"
        >
          {countryCodes.map((item) => (
            <option key={`${item.country}-${item.code}`} value={item.code}>
              {item.country} ({item.code})
            </option>
          ))}
        </select>
        <div className="relative">
          <Phone className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
          <input
            type="tel"
            value={phone}
            onChange={(event) => onPhoneChange(event.target.value)}
            autoComplete="tel-national"
            placeholder={selectedCountry.placeholder}
            className="h-9 w-full rounded-btn border border-gray-200 bg-white pl-9 pr-3 text-sm text-ui-text outline-none transition placeholder:text-gray-400 focus:border-ui-green focus:ring-2 focus:ring-ui-green/15 dark:border-slate-600 dark:bg-slate-900 dark:text-ui-dark-text dark:placeholder:text-ui-dark-text-muted"
          />
        </div>
      </div>
      <InputError message={error} className="mt-2 text-xs" />
    </div>
  );
}
