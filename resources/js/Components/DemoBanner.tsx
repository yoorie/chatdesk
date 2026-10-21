import { usePage } from '@inertiajs/react';
import { FlaskConical } from 'lucide-react';

/** Small fixed badge shown on every page when DEMO_MODE=true. */
export default function DemoBanner() {
  const demo = (usePage().props as any).demo as { enabled?: boolean } | null;
  if (!demo?.enabled) return null;
  return (
    <div
      role="status"
      title="Changes are disabled. Data resets automatically."
      className="pointer-events-none fixed bottom-3 left-3 z-[60] inline-flex max-w-[calc(100vw-1.5rem)] items-center gap-1.5 rounded-full bg-[#047857] px-3 py-1.5 text-xs font-semibold text-white shadow-lg"
    >
      <FlaskConical className="h-3.5 w-3.5" aria-hidden="true" />
      Demo mode · changes disabled
    </div>
  );
}
