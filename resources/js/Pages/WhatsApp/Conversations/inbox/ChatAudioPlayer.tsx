import { Pause, Play } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { formatAudioTime } from './audio';

export function ChatAudioPlayer({
  src,
  title,
  outbound,
  voice = false,
  onPreview,
}: {
  src: string;
  title: string;
  outbound: boolean;
  voice?: boolean;
  onPreview?: () => void;
}) {
  const audioRef = useRef<HTMLAudioElement | null>(null);
  const [playing, setPlaying] = useState(false);
  const [duration, setDuration] = useState(0);
  const [currentTime, setCurrentTime] = useState(0);

  useEffect(() => {
    const audio = audioRef.current;
    if (!audio) return;

    const sync = () => setCurrentTime(audio.currentTime || 0);
    const loaded = () => setDuration(Number.isFinite(audio.duration) ? audio.duration : 0);
    const ended = () => setPlaying(false);

    audio.addEventListener('timeupdate', sync);
    audio.addEventListener('loadedmetadata', loaded);
    audio.addEventListener('durationchange', loaded);
    audio.addEventListener('ended', ended);

    return () => {
      audio.removeEventListener('timeupdate', sync);
      audio.removeEventListener('loadedmetadata', loaded);
      audio.removeEventListener('durationchange', loaded);
      audio.removeEventListener('ended', ended);
    };
  }, [src]);

  const toggle = () => {
    const audio = audioRef.current;
    if (!audio) return;

    if (audio.paused) {
      audio
        .play()
        .then(() => setPlaying(true))
        .catch(() => setPlaying(false));
    } else {
      audio.pause();
      setPlaying(false);
    }
  };

  const seek = (value: string) => {
    const audio = audioRef.current;
    if (!audio || !duration) return;

    const nextTime = (Number(value) / 100) * duration;
    audio.currentTime = nextTime;
    setCurrentTime(nextTime);
  };

  const progress = duration ? Math.min(100, Math.max(0, (currentTime / duration) * 100)) : 0;
  const label = voice ? 'Voice message' : title || 'Audio message';

  return (
    <div
      className={`mb-2 min-w-[240px] max-w-[320px] rounded-lg px-3 py-2 ring-1 ${outbound ? 'bg-white/45 ring-emerald-900/10 dark:bg-white/10 dark:ring-white/10' : 'bg-gray-50 ring-gray-100 dark:bg-slate-800 dark:ring-slate-700'}`}
    >
      <audio ref={audioRef} src={src} preload="metadata" className="hidden" />
      <div className="flex items-center gap-3">
        <button
          type="button"
          onClick={toggle}
          className={`flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full transition ${outbound ? 'bg-ui-green text-white hover:bg-ui-green-dark' : 'bg-ui-green-soft text-ui-green-dark hover:bg-ui-green-soft/80 dark:bg-ui-green/15 dark:text-ui-green'}`}
          aria-label={playing ? 'Pause audio' : 'Play audio'}
        >
          {playing ? <Pause className="h-4 w-4" /> : <Play className="h-4 w-4 translate-x-px" />}
        </button>
        <div className="min-w-0 flex-1">
          <div className="flex items-center justify-between gap-2">
            <span className="truncate text-xs font-semibold">{label}</span>
            {onPreview && (
              <button
                type="button"
                onClick={onPreview}
                className="text-[10px] font-medium opacity-70 transition hover:opacity-100"
              >
                Preview
              </button>
            )}
          </div>
          <input
            type="range"
            min="0"
            max="100"
            value={progress}
            onChange={(event) => seek(event.target.value)}
            className="mt-1 h-1.5 w-full cursor-pointer accent-ui-green"
            aria-label="Audio progress"
          />
          <div className="mt-0.5 flex justify-between text-[10px] opacity-70">
            <span>{formatAudioTime(currentTime)}</span>
            <span>{formatAudioTime(duration)}</span>
          </div>
        </div>
      </div>
    </div>
  );
}
