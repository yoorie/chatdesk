import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import CountryPhoneInput, {
  splitPhoneNumber,
  timezoneForCountryCode,
} from '@/Components/Profile/CountryPhoneInput';
import TextInput from '@/Components/TextInput';
import Button from '@/Components/UI/Button';
import { Card } from '@/Components/UI/Card';
import { Transition } from '@headlessui/react';
import { useForm, usePage } from '@inertiajs/react';
import { Briefcase, CheckCircle2, Mail, Save, User } from 'lucide-react';

export default function ProfileTab() {
  const { auth } = usePage().props as any;
  const user = auth?.user;
  const parsedPhone = splitPhoneNumber(user?.phone, user?.country_code);

  const { data, setData, patch, processing, errors, reset, recentlySuccessful } = useForm({
    name: user?.name || '',
    email: user?.email || '',
    country_code: parsedPhone.countryCode,
    phone: parsedPhone.localPhone,
    job_title: user?.job_title || '',
    locale: user?.locale || 'en-IN',
    timezone: user?.timezone || timezoneForCountryCode(parsedPhone.countryCode),
  });

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    patch(route('profile.update'), {
      preserveScroll: true,
      onSuccess: () => reset(),
    });
  };

  return (
    <div className="space-y-6">
      <Card className="p-5">
        <form onSubmit={submit} className="space-y-5">
          <div>
            <InputLabel
              htmlFor="name"
              value="Full name"
              className="mb-1 text-xs font-medium text-ui-text dark:text-ui-dark-text"
            />
            <div className="relative">
              <User className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
              <TextInput
                id="name"
                type="text"
                value={data.name}
                onChange={(e) => setData('name', e.target.value)}
                className="block h-9 w-full rounded-btn border-gray-200 pl-9 text-sm focus:border-ui-green focus:ring-ui-green/15 dark:border-slate-600 dark:bg-slate-900"
                required
              />
            </div>
            <InputError message={errors.name} className="mt-2 text-xs" />
          </div>

          <div>
            <InputLabel
              htmlFor="email"
              value="Email address"
              className="mb-1 text-xs font-medium text-ui-text dark:text-ui-dark-text"
            />
            <div className="relative">
              <Mail className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
              <TextInput
                id="email"
                type="email"
                value={data.email}
                onChange={(e) => setData('email', e.target.value)}
                className="block h-9 w-full rounded-btn border-gray-200 pl-9 text-sm focus:border-ui-green focus:ring-ui-green/15 dark:border-slate-600 dark:bg-slate-900"
                required
              />
            </div>
            <InputError message={errors.email} className="mt-2 text-xs" />
          </div>

          <CountryPhoneInput
            countryCode={data.country_code}
            phone={data.phone}
            onCountryCodeChange={(value) =>
              setData((current) => ({
                ...current,
                country_code: value,
                timezone: timezoneForCountryCode(value),
              }))
            }
            onPhoneChange={(value) => setData('phone', value)}
            error={errors.phone || errors.country_code}
          />

          <div className="grid gap-5 sm:grid-cols-3">
            <div>
              <InputLabel
                htmlFor="job_title"
                value="Role / title"
                className="mb-1 text-xs font-medium text-ui-text dark:text-ui-dark-text"
              />
              <div className="relative">
                <Briefcase className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                <TextInput
                  id="job_title"
                  value={data.job_title}
                  onChange={(e) => setData('job_title', e.target.value)}
                  className="block h-9 w-full rounded-btn border-gray-200 pl-9 text-sm focus:border-ui-green focus:ring-ui-green/15 dark:border-slate-600 dark:bg-slate-900"
                  placeholder="Marketing manager"
                />
              </div>
              <InputError message={errors.job_title} className="mt-2 text-xs" />
            </div>
            <div>
              <InputLabel
                htmlFor="locale"
                value="Language"
                className="mb-1 text-xs font-medium text-ui-text dark:text-ui-dark-text"
              />
              <select
                id="locale"
                value={data.locale}
                onChange={(e) => setData('locale', e.target.value)}
                className="h-9 w-full rounded-btn border border-gray-200 bg-white px-3 text-sm text-ui-text outline-none transition focus:border-ui-green focus:ring-2 focus:ring-ui-green/15 dark:border-slate-600 dark:bg-slate-900 dark:text-ui-dark-text"
              >
                <option value="en-IN">English (India)</option>
                <option value="en-US">English (US)</option>
                <option value="hi-IN">Hindi</option>
                <option value="ta-IN">Tamil</option>
                <option value="te-IN">Telugu</option>
                <option value="mr-IN">Marathi</option>
                <option value="bn-IN">Bengali</option>
              </select>
              <InputError message={errors.locale} className="mt-2 text-xs" />
            </div>
          </div>

          <div className="flex items-center justify-end gap-4 border-t border-gray-100 pt-4 dark:border-slate-700">
            <Button type="submit" disabled={processing}>
              {processing ? (
                'Saving...'
              ) : (
                <>
                  <Save className="h-4 w-4" />
                  Save changes
                </>
              )}
            </Button>
            <Transition
              show={recentlySuccessful}
              enter="transition ease-in-out"
              enterFrom="opacity-0"
              leave="transition ease-in-out"
              leaveTo="opacity-0"
            >
              <div className="flex items-center gap-2 text-sm text-emerald-600 dark:text-emerald-300">
                <CheckCircle2 className="h-4 w-4" />
                Saved
              </div>
            </Transition>
          </div>
        </form>
      </Card>
    </div>
  );
}
