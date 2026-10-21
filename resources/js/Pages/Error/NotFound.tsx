import { Head, usePage } from '@inertiajs/react';
import { Inbox, Search, Users } from 'lucide-react';
import ErrorFrame, { defaultBackAction, defaultDashboardAction } from './ErrorFrame';

export default function NotFound() {
  const path = usePage().url.split('?')[0];
  const isInboxPath = path.includes('/conversations');
  const isContactPath = path.includes('/contacts');
  const title = isInboxPath
    ? 'Conversation not found'
    : isContactPath
      ? 'Contact not found'
      : 'Page not found';
  const description = isInboxPath
    ? 'This conversation may have been deleted, moved to another workspace, or may not be available to your role.'
    : isContactPath
      ? 'This contact may have been deleted, moved to another workspace, or may not be available to your role.'
      : 'This page may have moved, been deleted, or may not be available for your workspace.';
  const actions = [defaultBackAction(), defaultDashboardAction()];

  if (isInboxPath && typeof route !== 'undefined') {
    actions.push({
      label: 'Inbox',
      href: route('app.whatsapp.conversations.index'),
      icon: Inbox,
      primary: true,
    });
  }

  if (isContactPath && typeof route !== 'undefined') {
    actions.push({
      label: 'Contacts',
      href: route('app.contacts.index'),
      icon: Users,
      primary: true,
    });
  }

  return (
    <>
      <Head title="Page not found" />
      <ErrorFrame
        code="404"
        title={title}
        description={description}
        icon={Search}
        accent="green"
        actions={actions}
      />
    </>
  );
}
