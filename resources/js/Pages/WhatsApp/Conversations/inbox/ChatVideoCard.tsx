import { Film, Play } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { formatAudioTime } from './audio';

export function ChatVideoCard({
  src,
  title,
  outbound,
  onPreview,
}: {
  src: string;
  title: string;
  outbound: boolean;
  onPreview: () => void;
}) {
  const videoRef = useRef<HTMLVideoElement | null>(null);
  const [duration, setDuration] = useState(0);

  useEffect(() => {
    const video = videoRef.current;
    if (!video) return;

    const loaded = () => setDuration(Number.isFinite(video.duration) ? video.duration : 0);
    video.addEventListener('loadedmetadata', loaded);
    video.addEventListener('durationchange', loaded);

    return () => {
      video.removeEventListener('loadedmetadata', loaded);
      video.removeEventListener('durationchange', loaded);
    };
  }, [src]);

  return (
    <button
      type="button"
      onClick={onPreview}
      className={`mb-2 block w-full min-w-[240px] max-w-[320px] overflow-hidden rounded-lg text-left shadow-sm ring-1 transition hover:scale-[1.01] ${outbound ? 'bg-white/45 ring-emerald-900/10 dark:bg-white/10 dark:ring-white/10' : 'bg-gray-50 ring-gray-100 dark:bg-slate-800 dark:ring-slate-700'}`}
    >
      <div className="relative aspect-video bg-slate-900">
        <video
          ref={videoRef}
          src={src}
          preload="metadata"
          muted
          playsInline
          className="h-full w-full object-cover"
        />
        <div className="absolute inset-0 flex items-center justify-center bg-black/20">
          <span className="flex h-12 w-12 items-center justify-center rounded-full bg-black/55 text-white shadow-lg backdrop-blur-sm">
            <Play className="h-5 w-5 translate-x-px" />
          </span>
        </div>
        {duration > 0 && (
          <span className="absolute bottom-2 right-2 rounded bg-black/65 px-1.5 py-0.5 text-[10px] font-medium text-white">
            {formatAudioTime(duration)}
          </span>
        )}
      </div>
      <div className="flex items-center gap-2 px-3 py-2">
        <Film className="h-4 w-4 flex-shrink-0 opacity-70" />
        <span className="min-w-0 flex-1 truncate text-xs font-semibold">
          {title || 'Video message'}
        </span>
        <span className="text-[10px] font-medium opacity-70">Preview</span>
      </div>
    </button>
  );
}
