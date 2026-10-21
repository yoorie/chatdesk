# Global Notifications System

This directory contains the global notification system for the application, including toasts, alerts, dialogs, and confirmations.

## Components

### `GlobalFlashHandler`

Automatically converts Laravel flash messages (`flash.success`, `flash.error`, etc.) to toast notifications. This component is included in the root app and requires no additional setup.

### `ConfirmProvider`

Provides global confirmation dialogs throughout the app. Wraps the application in `app.tsx`.

### `Alert`

Inline alert component for displaying messages within pages.

## Hooks

### `useToast()`

Provides toast notification functionality.

```tsx
import { useToast } from '@/hooks/useToast';

const { addToast } = useToast();

addToast({
  title: 'Success',
  description: 'Item saved successfully',
  variant: 'success',
});
```

### `useConfirm()`

Provides global confirmation dialogs.

```tsx
import { useConfirm } from '@/hooks/useConfirm';

const confirm = useConfirm();

const handleDelete = async () => {
  const confirmed = await confirm({
    title: 'Delete Item',
    message: 'Are you sure you want to delete this item?',
    variant: 'danger',
    confirmText: 'Delete',
    cancelText: 'Cancel',
  });

  if (confirmed) {
    // Proceed with deletion
    router.delete(route('items.destroy', { item: id }));
  }
};
```

### `useNotifications()`

Combined hook for both toasts and confirmations.

```tsx
import { useNotifications } from '@/hooks/useNotifications';

const { toast, confirm } = useNotifications();

// Toast notifications
toast.success('Item saved!');
toast.error('Something went wrong');
toast.warning('Please check your input');
toast.info('Processing...');

// Confirmations
const confirmed = await confirm({
  title: 'Delete Item',
  message: 'Are you sure?',
  variant: 'danger',
});
```

## Usage Examples

### Toast Notifications

```tsx
import { useNotifications } from '@/hooks/useNotifications';

function MyComponent() {
  const { toast } = useNotifications();

  const handleSave = async () => {
    try {
      await saveData();
      toast.success('Data saved successfully');
    } catch (error) {
      toast.error('Failed to save data', error.message);
    }
  };

  return <button onClick={handleSave}>Save</button>;
}
```

### Confirmation Dialogs

```tsx
import { useNotifications } from '@/hooks/useNotifications';
import { router } from '@inertiajs/react';

function DeleteButton({ id }: { id: number }) {
  const { confirm } = useNotifications();

  const handleDelete = async () => {
    const confirmed = await confirm({
      title: 'Delete Item',
      message: 'This action cannot be undone.',
      variant: 'danger',
      confirmText: 'Delete',
      cancelText: 'Cancel',
    });

    if (confirmed) {
      router.delete(route('items.destroy', { item: id }));
    }
  };

  return <button onClick={handleDelete}>Delete</button>;
}
```

### Inline Alerts

```tsx
import { Alert } from '@/Components/UI/Alert';

function MyPage() {
  return (
    <div>
      <Alert variant="success" title="Success">
        Your changes have been saved.
      </Alert>

      <Alert variant="error" title="Error" onClose={() => {}}>
        Something went wrong. Please try again.
      </Alert>

      <Alert variant="warning">Please review your input before submitting.</Alert>

      <Alert variant="info" title="Info">
        New features are available.
      </Alert>
    </div>
  );
}
```

## Flash Messages (Laravel Backend)

Flash messages from Laravel are automatically converted to toasts:

```php
// In your controller
return redirect()->back()->with('success', 'Item created successfully');
return redirect()->back()->with('error', 'Something went wrong');
return redirect()->back()->with('warning', 'Please check your input');
return redirect()->back()->with('info', 'Processing...');
```

## Variants

### Toast Variants

- `success` - Green, for successful operations
- `error` - Red, for errors
- `warning` - Yellow, for warnings
- `info` - Blue, for informational messages

### Alert Variants

Same as toast variants.

### Confirmation Variants

- `danger` - Red, for destructive actions
- `warning` - Yellow, for cautionary actions
- `info` - Blue, for informational confirmations
