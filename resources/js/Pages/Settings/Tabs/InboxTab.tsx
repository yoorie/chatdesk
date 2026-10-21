import InputError from '@/Components/InputError';
import Button from '@/Components/UI/Button';
import { Card } from '@/Components/UI/Card';
import { Transition } from '@headlessui/react';
import { useForm, usePage } from '@inertiajs/react';
import { CheckCircle2, Clock, MessageCircle, Sparkles } from 'lucide-react';

export default function InboxTab() {
  const { account } = usePage().props as any;

  const { data, setData, post, processing, errors, recentlySuccessful } = useForm({
    auto_assign_enabled: Boolean(account?.auto_assign_enabled),
    auto_assign_strategy: account?.auto_assign_strategy || 'round_robin',
    welcome_message_enabled: Boolean(account?.welcome_message_enabled),
    welcome_message_body:
      account?.welcome_message_body ||
      'Hi {{name}}, thanks for messaging us. Our team will reply shortly.',
    auto_close_conversations_enabled: Boolean(account?.auto_close_conversations_enabled),
    auto_close_after_hours: account?.auto_close_after_hours || 48,
  });

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    post(route('app.settings.inbox', {}), {
      preserveScroll: true,
    });
  };

  return (
    <form onSubmit={submit} className="space-y-6">
      <Card className="p-4">
        <div className="flex items-start justify-between gap-4">
          <div>
            <div className="flex items-center gap-2 text-sm font-medium text-ui-text dark:text-ui-dark-text">
              <Sparkles className="h-4 w-4 text-ui-green-dark dark:text-emerald-300" />
              Auto-assign incoming chats
            </div>
            <p className="mt-0.5 text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
              Automatically distribute new conversations among your agents.
            </p>
          </div>
          <label className="relative inline-flex cursor-pointer items-center">
            <input
              type="checkbox"
              className="peer sr-only"
              checked={data.auto_assign_enabled}
              onChange={(e) => setData('auto_assign_enabled', e.target.checked)}
            />
            <div className="h-6 w-11 rounded-full bg-gray-200 transition peer-checked:bg-ui-green peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-ui-green/20 dark:bg-slate-700" />
            <div className="absolute left-1 top-1 h-4 w-4 rounded-full bg-white transition peer-checked:translate-x-5" />
          </label>
        </div>
        <InputError message={errors.auto_assign_enabled} className="mt-2 text-xs" />
      </Card>

      <div className="space-y-2">
        <label className="text-xs font-medium text-ui-text dark:text-ui-dark-text">
          Assignment strategy
        </label>
        <select
          value={data.auto_assign_strategy}
          onChange={(e) => setData('auto_assign_strategy', e.target.value)}
          className="block h-9 w-full rounded-btn border-gray-200 bg-white px-3 text-sm shadow-sm focus:border-ui-green focus:ring-ui-green/15 dark:border-slate-600 dark:bg-slate-900 dark:text-ui-dark-text"
          disabled={!data.auto_assign_enabled}
        >
          <option value="round_robin">Take turns</option>
        </select>
        <InputError message={errors.auto_assign_strategy} className="mt-2 text-xs" />
        <p className="text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
          New chats go to each team member in turn.
        </p>
      </div>

      <Card className="p-4">
        <div className="flex items-start justify-between gap-4">
          <div>
            <div className="flex items-center gap-2 text-sm font-medium text-ui-text dark:text-ui-dark-text">
              <MessageCircle className="h-4 w-4 text-ui-green-dark dark:text-emerald-300" />
              Welcome message
            </div>
            <p className="mt-0.5 text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
              Send a first response automatically when a new contact opens a chat.
            </p>
          </div>
          <label className="relative inline-flex cursor-pointer items-center">
            <input
              type="checkbox"
              className="peer sr-only"
              checked={data.welcome_message_enabled}
              onChange={(e) => setData('welcome_message_enabled', e.target.checked)}
            />
            <div className="h-6 w-11 rounded-full bg-gray-200 transition peer-checked:bg-ui-green peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-ui-green/20 dark:bg-slate-700" />
            <div className="absolute left-1 top-1 h-4 w-4 rounded-full bg-white transition peer-checked:translate-x-5" />
          </label>
        </div>
        <div className="mt-4 space-y-2">
          <textarea
            value={data.welcome_message_body}
            onChange={(e) => setData('welcome_message_body', e.target.value)}
            disabled={!data.welcome_message_enabled}
            rows={4}
            className="block w-full rounded-2xl border-gray-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-ui-green focus:ring-ui-green/15 disabled:cursor-not-allowed disabled:bg-gray-50 disabled:text-ui-text-muted dark:border-slate-600 dark:bg-slate-900 dark:text-ui-dark-text dark:disabled:bg-slate-950 dark:disabled:text-ui-dark-text-muted"
            placeholder="Hi {{name}}, thanks for messaging us. Our team will reply shortly."
          />
          <InputError message={errors.welcome_message_enabled} className="mt-2 text-xs" />
          <InputError message={errors.welcome_message_body} className="mt-2 text-xs" />
          <p className="text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
            Available placeholders: {'{{name}}'}, {'{{phone}}'}, {'{{workspace}}'}.
          </p>
          <div className="rounded-2xl border border-emerald-100 bg-emerald-50/70 p-3 text-sm text-emerald-950 dark:border-emerald-400/20 dark:bg-emerald-400/10 dark:text-emerald-100">
            {data.welcome_message_body
              .replaceAll('{{name}}', 'Prashant')
              .replaceAll('{{phone}}', '+91 99887 76655')
              .replaceAll('{{workspace}}', account?.name || 'Your business')}
          </div>
        </div>
      </Card>

      <Card className="p-4">
        <div className="flex items-start justify-between gap-4">
          <div>
            <div className="flex items-center gap-2 text-sm font-medium text-ui-text dark:text-ui-dark-text">
              <Clock className="h-4 w-4 text-ui-green-dark dark:text-emerald-300" />
              Auto-close inactive chats
            </div>
            <p className="mt-0.5 text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
              Keep the inbox clean by closing conversations that have no activity for a set number
              of hours.
            </p>
          </div>
          <label className="relative inline-flex cursor-pointer items-center">
            <input
              type="checkbox"
              className="peer sr-only"
              checked={data.auto_close_conversations_enabled}
              onChange={(e) => setData('auto_close_conversations_enabled', e.target.checked)}
            />
            <div className="h-6 w-11 rounded-full bg-gray-200 transition peer-checked:bg-ui-green peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-ui-green/20 dark:bg-slate-700" />
            <div className="absolute left-1 top-1 h-4 w-4 rounded-full bg-white transition peer-checked:translate-x-5" />
          </label>
        </div>
        <div className="mt-4 max-w-xs space-y-2">
          <label className="text-xs font-medium text-ui-text dark:text-ui-dark-text">
            Inactive hours before close
          </label>
          <input
            type="number"
            min={1}
            max={720}
            value={data.auto_close_after_hours}
            disabled={!data.auto_close_conversations_enabled}
            onChange={(e) => setData('auto_close_after_hours', Number(e.target.value))}
            className="block h-9 w-full rounded-btn border-gray-200 bg-white px-3 text-sm shadow-sm focus:border-ui-green focus:ring-ui-green/15 disabled:cursor-not-allowed disabled:bg-gray-50 disabled:text-ui-text-muted dark:border-slate-600 dark:bg-slate-900 dark:text-ui-dark-text"
          />
          <InputError message={errors.auto_close_conversations_enabled} className="mt-2 text-xs" />
          <InputError message={errors.auto_close_after_hours} className="mt-2 text-xs" />
        </div>
      </Card>

      <div className="flex items-center justify-end gap-4 border-t border-gray-100 pt-4 dark:border-slate-700">
        <Button type="submit" disabled={processing}>
          {processing ? 'Saving...' : 'Save settings'}
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
  );
}
