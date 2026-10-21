import InputError from '@/Components/InputError';
import Button from '@/Components/UI/Button';
import { Modal } from '@/Components/UI/Elements';
import { useForm } from '@inertiajs/react';
import type { FormEventHandler } from 'react';

export const CONNECT_GUIDE_URL =
  'https://github.com/yoorie/chatdesk/blob/main/docs/connect-whatsapp.md';

const inputClass =
  'mt-1 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-ui-text shadow-sm focus:border-ui-green focus:outline-none focus:ring-1 focus:ring-ui-green dark:border-slate-600 dark:bg-slate-900 dark:text-ui-dark-text';

export default function ManualConnectDialog({
  open,
  onClose,
}: {
  open: boolean;
  onClose: () => void;
}) {
  const form = useForm({
    name: '',
    phone_number_id: '',
    waba_id: '',
    access_token: '',
    business_phone: '',
  });

  const submit: FormEventHandler = (event) => {
    event.preventDefault();
    form.post(route('app.whatsapp.connections.store'), {
      preserveScroll: true,
      onSuccess: () => {
        form.reset();
        onClose();
      },
    });
  };

  const field = (
    key: 'name' | 'phone_number_id' | 'waba_id' | 'access_token' | 'business_phone',
    label: string,
    hint: string,
    props: Record<string, unknown> = {}
  ) => (
    <div>
      <label htmlFor={`wa-${key}`} className="text-sm font-medium text-ui-text dark:text-ui-dark-text">
        {label}
      </label>
      <input
        id={`wa-${key}`}
        className={inputClass}
        value={form.data[key]}
        onChange={(e) => form.setData(key, e.target.value)}
        {...props}
      />
      <p className="mt-1 text-xs text-ui-text-muted dark:text-ui-dark-text-muted">{hint}</p>
      <InputError message={form.errors[key]} className="mt-1" />
    </div>
  );

  return (
    <Modal open={open} onClose={onClose} title="Connect WhatsApp Cloud API">
      <form onSubmit={submit} className="space-y-4">
        <p className="text-sm text-ui-text-muted dark:text-ui-dark-text-muted">
          Copy these values from your Meta app (WhatsApp &rarr; API Setup).{' '}
          <a
            href={CONNECT_GUIDE_URL}
            target="_blank"
            rel="noreferrer"
            className="font-medium text-ui-green-dark underline dark:text-emerald-300"
          >
            Step-by-step guide
          </a>
        </p>
        {field('phone_number_id', 'Phone Number ID', 'Numeric ID of your WhatsApp number, not the phone number itself.', { required: true, inputMode: 'numeric' })}
        {field('waba_id', 'WhatsApp Business Account ID (WABA ID)', 'Shown under the phone number on the API Setup page.', { required: true, inputMode: 'numeric' })}
        {field('access_token', 'Permanent access token', 'A System User token with whatsapp_business_messaging and whatsapp_business_management.', { required: true, type: 'password', autoComplete: 'off' })}
        {field('business_phone', 'Display phone number (optional)', 'For example +91 98765 43210. Filled automatically when Meta returns it.')}
        {field('name', 'Label (optional)', 'A friendly name for this number.')}
        <div className="flex justify-end gap-2 pt-2">
          <Button type="button" variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" disabled={form.processing}>
            {form.processing ? 'Saving…' : 'Save connection'}
          </Button>
        </div>
      </form>
    </Modal>
  );
}
