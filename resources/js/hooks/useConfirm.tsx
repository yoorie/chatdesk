import { createContext, lazy, Suspense, ReactNode, useCallback, useContext, useState } from 'react';

const ConfirmationDialog = lazy(() => import('@/Components/UI/ConfirmationDialog'));

export interface ConfirmOptions {
  title: string;
  message: string;
  confirmText?: string;
  cancelText?: string;
  variant?: 'danger' | 'warning' | 'info';
}

export interface ConfirmState extends ConfirmOptions {
  show: boolean;
  onConfirm: () => void;
  onCancel: () => void;
}

interface ConfirmContextType {
  confirm: (options: ConfirmOptions) => Promise<boolean>;
}

const ConfirmContext = createContext<ConfirmContextType | null>(null);

/**
 * Global confirmation hook
 *
 * Usage:
 * const confirm = useConfirm();
 *
 * const handleDelete = async () => {
 *   const confirmed = await confirm({
 *     title: 'Delete Item',
 *     message: 'Are you sure?',
 *     variant: 'danger'
 *   });
 *   if (confirmed) {
 *     // Proceed with deletion
 *   }
 * };
 */
export function useConfirm() {
  const context = useContext(ConfirmContext);
  if (!context) {
    throw new Error('useConfirm must be used within ConfirmProvider');
  }
  return context.confirm;
}

export function ConfirmProvider({ children }: { children: ReactNode }) {
  const [state, setState] = useState<ConfirmState | null>(null);

  const confirm = useCallback((options: ConfirmOptions): Promise<boolean> => {
    return new Promise((resolve) => {
      setState({
        ...options,
        show: true,
        onConfirm: () => {
          setState(null);
          resolve(true);
        },
        onCancel: () => {
          setState(null);
          resolve(false);
        },
      });
    });
  }, []);

  return (
    <ConfirmContext.Provider value={{ confirm }}>
      {children}
      {state && (
        <Suspense fallback={null}>
          <ConfirmationDialog
            show={state.show}
            onClose={state.onCancel}
            onConfirm={state.onConfirm}
            title={state.title}
            message={state.message}
            confirmText={state.confirmText}
            cancelText={state.cancelText}
            variant={state.variant}
          />
        </Suspense>
      )}
    </ConfirmContext.Provider>
  );
}
