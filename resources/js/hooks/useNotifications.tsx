import { useConfirm } from './useConfirm';
import { useToast } from './useToast';

/**
 * Combined notifications hook
 *
 * Provides easy access to both toast notifications and confirmations.
 *
 * Usage:
 * const { toast, confirm } = useNotifications();
 *
 * toast.success('Item saved!');
 * toast.error('Something went wrong');
 *
 * const confirmed = await confirm({
 *   title: 'Delete Item',
 *   message: 'Are you sure?',
 *   variant: 'danger'
 * });
 */
export function useNotifications() {
  const { addToast } = useToast();
  const confirm = useConfirm();

  return {
    toast: {
      success: (message: string, description?: string) => {
        addToast({
          title: message,
          description,
          variant: 'success',
        });
      },
      error: (message: string, description?: string) => {
        addToast({
          title: message,
          description,
          variant: 'error',
        });
      },
      warning: (message: string, description?: string) => {
        addToast({
          title: message,
          description,
          variant: 'warning',
        });
      },
      info: (message: string, description?: string) => {
        addToast({
          title: message,
          description,
          variant: 'info',
        });
      },
    },
    confirm,
  };
}
