function Skeleton({ className = '' }: { className?: string }) {
  return <div className={`ui-skeleton ${className}`} />;
}

export function ConversationSkeleton() {
  return (
    <div className="p-4 border-b border-gray-200 dark:border-gray-800">
      <div className="flex items-start gap-3">
        <Skeleton className="h-10 w-10 rounded-full" />
        <div className="flex-1 space-y-2">
          <div className="flex items-center justify-between">
            <Skeleton className="h-4 w-32" />
            <Skeleton className="h-4 w-16" />
          </div>
          <Skeleton className="h-3 w-full" />
          <Skeleton className="h-3 w-2/3" />
        </div>
      </div>
    </div>
  );
}
