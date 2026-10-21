import {
  ExternalLink,
  File as FileIcon,
  FileText,
  Film,
  Image as ImageIcon,
  Link2,
  Mic,
  Play,
} from 'lucide-react';
import { useState } from 'react';
import { formatFileSize } from './helpers';
import { type ContactGallery, type GalleryItem } from './types';

export function ContactGalleryPanel({
  gallery,
  loading,
  onPreview,
}: {
  gallery: ContactGallery;
  loading: boolean;
  onPreview: (item: GalleryItem) => void;
}) {
  const [activeTab, setActiveTab] = useState<'media' | 'documents' | 'links'>('media');
  const tabs = [
    { id: 'media', label: 'Media', count: gallery.counts.media, icon: ImageIcon },
    { id: 'documents', label: 'Docs', count: gallery.counts.documents, icon: FileText },
    { id: 'links', label: 'Links', count: gallery.counts.links, icon: Link2 },
  ] as const;
  const items =
    activeTab === 'media'
      ? gallery.media
      : activeTab === 'documents'
        ? gallery.documents
        : gallery.links;

  return (
    <div className="space-y-3">
      <div className="grid grid-cols-3 gap-1 rounded-lg bg-gray-50 p-1 dark:bg-slate-800">
        {tabs.map(({ id, label, count, icon: Icon }) => (
          <button
            key={id}
            type="button"
            onClick={() => setActiveTab(id)}
            className={`flex items-center justify-center gap-1.5 rounded-md px-2 py-1.5 text-[11px] font-semibold transition ${activeTab === id ? 'bg-white text-ui-text shadow-sm dark:bg-slate-900 dark:text-ui-dark-text' : 'text-ui-text-muted hover:text-ui-text dark:text-ui-dark-text-muted dark:hover:text-ui-dark-text'}`}
          >
            <Icon className="h-3.5 w-3.5" />
            <span>{label}</span>
            <span className="rounded bg-gray-100 px-1 text-[10px] dark:bg-slate-700">{count}</span>
          </button>
        ))}
      </div>

      {loading ? (
        <div className="space-y-2">
          {[0, 1, 2].map((item) => (
            <div
              key={item}
              className="h-14 animate-pulse rounded-lg bg-gray-100 dark:bg-slate-800"
            />
          ))}
        </div>
      ) : items.length === 0 ? (
        <div className="rounded-lg border border-dashed border-gray-200 p-5 text-center dark:border-ui-dark-border">
          <div className="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-gray-50 text-ui-text-muted dark:bg-slate-800 dark:text-ui-dark-text-muted">
            {activeTab === 'links' ? (
              <Link2 className="h-4 w-4" />
            ) : activeTab === 'documents' ? (
              <FileText className="h-4 w-4" />
            ) : (
              <ImageIcon className="h-4 w-4" />
            )}
          </div>
          <p className="mt-2 text-xs font-medium text-ui-text dark:text-ui-dark-text">
            No {activeTab === 'documents' ? 'documents' : activeTab} yet
          </p>
          <p className="mt-1 text-[11px] text-ui-text-muted dark:text-ui-dark-text-muted">
            Shared items from this chat will appear here.
          </p>
        </div>
      ) : activeTab === 'media' ? (
        <div className="grid grid-cols-3 gap-2">
          {items.map((item) => {
            const title = item.filename || item.title || `${item.type} message`;
            return (
              <button
                key={item.id}
                type="button"
                onClick={() => item.url && onPreview(item)}
                disabled={!item.url}
                className="group relative aspect-square overflow-hidden rounded-lg bg-gray-100 text-left ring-1 ring-gray-100 transition hover:ring-ui-green/40 disabled:cursor-not-allowed dark:bg-slate-800 dark:ring-slate-700"
                title={title}
              >
                {item.url && ['image', 'sticker'].includes(item.type) ? (
                  <img
                    src={item.url}
                    alt={title}
                    className="h-full w-full object-cover transition group-hover:scale-105"
                  />
                ) : (
                  <span className="flex h-full w-full items-center justify-center text-ui-text-muted dark:text-ui-dark-text-muted">
                    {item.type === 'video' ? (
                      <Film className="h-5 w-5" />
                    ) : item.type === 'audio' ? (
                      <Mic className="h-5 w-5" />
                    ) : (
                      <ImageIcon className="h-5 w-5" />
                    )}
                  </span>
                )}
                {item.type === 'video' && (
                  <span className="absolute bottom-1 right-1 rounded bg-black/60 p-1 text-white">
                    <Play className="h-3 w-3" />
                  </span>
                )}
                {!item.url && (
                  <span className="absolute inset-x-1 bottom-1 truncate rounded bg-black/55 px-1 py-0.5 text-[9px] text-white">
                    Not downloaded
                  </span>
                )}
              </button>
            );
          })}
        </div>
      ) : activeTab === 'documents' ? (
        <div className="space-y-2">
          {items.map((item) => (
            <button
              key={item.id}
              type="button"
              onClick={() => item.url && onPreview(item)}
              disabled={!item.url}
              className="flex w-full items-center gap-3 rounded-lg border border-gray-100 bg-white p-3 text-left transition hover:border-ui-green/40 disabled:cursor-not-allowed dark:border-ui-dark-border dark:bg-slate-800"
            >
              <span className="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-md bg-gray-50 text-ui-text-muted dark:bg-slate-900 dark:text-ui-dark-text-muted">
                <FileIcon className="h-4 w-4" />
              </span>
              <span className="min-w-0 flex-1">
                <span className="block truncate text-xs font-semibold text-ui-text dark:text-ui-dark-text">
                  {item.filename || item.title || 'Document'}
                </span>
                <span className="block text-[11px] text-ui-text-muted dark:text-ui-dark-text-muted">
                  {item.file_size ? formatFileSize(item.file_size) : item.mime_type || 'Document'}
                  {item.created_at ? ` · ${new Date(item.created_at).toLocaleDateString()}` : ''}
                </span>
              </span>
              <ExternalLink className="h-3.5 w-3.5 flex-shrink-0 text-ui-text-muted" />
            </button>
          ))}
        </div>
      ) : (
        <div className="space-y-2">
          {items.map((item) => (
            <a
              key={item.id}
              href={item.url || '#'}
              target="_blank"
              rel="noreferrer"
              className="flex items-start gap-3 rounded-lg border border-gray-100 bg-white p-3 text-left transition hover:border-ui-green/40 dark:border-ui-dark-border dark:bg-slate-800"
            >
              <span className="mt-0.5 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-md bg-gray-50 text-ui-text-muted dark:bg-slate-900 dark:text-ui-dark-text-muted">
                <Link2 className="h-4 w-4" />
              </span>
              <span className="min-w-0 flex-1">
                <span className="block truncate text-xs font-semibold text-ui-text dark:text-ui-dark-text">
                  {item.title || item.url}
                </span>
                <span className="block truncate text-[11px] text-ui-text-muted dark:text-ui-dark-text-muted">
                  {item.url}
                </span>
                {item.created_at && (
                  <span className="mt-1 block text-[10px] text-ui-text-muted dark:text-ui-dark-text-muted">
                    {new Date(item.created_at).toLocaleString()}
                  </span>
                )}
              </span>
            </a>
          ))}
        </div>
      )}
    </div>
  );
}
