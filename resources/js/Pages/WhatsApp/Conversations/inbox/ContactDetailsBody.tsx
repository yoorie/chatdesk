import { friendlyStatus } from '@/lib/friendly';
import { Badge } from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import { ExternalLink, Plus, RotateCcw } from 'lucide-react';
import type { Dispatch, SetStateAction } from 'react';
import { ContactGalleryPanel } from './ContactGalleryPanel';
import { contactDisplayPhone, INBOX_LABELS, labelClasses } from './helpers';
import {
  type ChatMessage,
  type ContactGallery,
  type Conversation,
  type MediaPreview,
} from './types';

interface Props {
  contactTab: 'media' | 'profile' | 'notes' | 'activity' | 'orders';
  activeLabels: string[];
  activeConversation: Conversation;
  activeAgentName: string;
  activeMediaCount: number;

  noteMode: boolean;
  messageDraft: string;
  setNoteMode: Dispatch<SetStateAction<boolean>>;
  setMessageDraft: Dispatch<SetStateAction<string>>;
  sendInlineMessage: () => void;
  activeNotes: ChatMessage[];
  activeGallery: ContactGallery;
  galleryLoadingId: number | null;
  setMediaPreview: Dispatch<SetStateAction<MediaPreview | null>>;
  activeInboundCount: number;
  lastInboundAt: string | null;
  activeOutboundCount: number;
  lastOutboundAt: string | null;
}

export function ContactDetailsBody({
  contactTab,
  activeLabels,
  activeConversation,
  activeAgentName,
  activeMediaCount,

  noteMode,
  messageDraft,
  setNoteMode,
  setMessageDraft,
  sendInlineMessage,
  activeNotes,
  activeGallery,
  galleryLoadingId,
  setMediaPreview,
  activeInboundCount,
  lastInboundAt,
  activeOutboundCount,
  lastOutboundAt,
}: Props) {
  return (
    <div className="min-h-0 flex-1 overflow-y-auto p-4">
      {contactTab === 'profile' && (
        <div className="space-y-4">
          <div>
            <div className="mb-1.5 text-[10px] font-medium uppercase tracking-wider text-ui-text-muted dark:text-ui-dark-text-muted">
              Tags
            </div>
            <div className="flex flex-wrap gap-1">
              {activeLabels.length > 0 ? (
                activeLabels.map((labelId) => {
                  const label = INBOX_LABELS.find((item) => item.id === labelId);
                  return label ? (
                    <span
                      key={label.id}
                      className={`rounded-md px-2 py-0.5 text-[11px] font-medium ${labelClasses(label.color)}`}
                    >
                      {label.label}
                    </span>
                  ) : null;
                })
              ) : (
                <span className="text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
                  No labels
                </span>
              )}
            </div>
          </div>
          <dl className="space-y-3 text-xs">
            <div className="flex justify-between gap-3">
              <dt className="text-ui-text-muted dark:text-ui-dark-text-muted">Phone</dt>
              <dd className="max-w-[160px] truncate text-right font-medium text-ui-text dark:text-ui-dark-text">
                {contactDisplayPhone(activeConversation)}
              </dd>
            </div>
            <div className="flex justify-between gap-3">
              <dt className="text-ui-text-muted dark:text-ui-dark-text-muted">Email</dt>
              <dd className="max-w-[160px] truncate text-right font-medium text-ui-text dark:text-ui-dark-text">
                {activeConversation.contact.email || '-'}
              </dd>
            </div>
            <div className="flex justify-between gap-3">
              <dt className="text-ui-text-muted dark:text-ui-dark-text-muted">Company</dt>
              <dd className="max-w-[160px] truncate text-right font-medium text-ui-text dark:text-ui-dark-text">
                {activeConversation.contact.company || '-'}
              </dd>
            </div>
            <div className="flex justify-between gap-3">
              <dt className="text-ui-text-muted dark:text-ui-dark-text-muted">Source</dt>
              <dd className="text-right font-medium capitalize text-ui-text dark:text-ui-dark-text">
                {activeConversation.contact.ctwa
                  ? 'Click-to-WhatsApp ad'
                  : (activeConversation.contact.source || 'WhatsApp').replaceAll('_', ' ')}
              </dd>
            </div>
            <div className="flex justify-between gap-3">
              <dt className="text-ui-text-muted dark:text-ui-dark-text-muted">Contact status</dt>
              <dd className="font-medium capitalize text-ui-text dark:text-ui-dark-text">
                {activeConversation.contact.status || 'active'}
              </dd>
            </div>
            <div className="flex justify-between gap-3">
              <dt className="text-ui-text-muted dark:text-ui-dark-text-muted">Status</dt>
              <dd>
                <Badge variant={activeConversation.status === 'open' ? 'success' : 'default'}>
                  {friendlyStatus(activeConversation.status)}
                </Badge>
              </dd>
            </div>
            <div className="flex justify-between gap-3">
              <dt className="text-ui-text-muted dark:text-ui-dark-text-muted">Assigned</dt>
              <dd className="font-medium text-ui-text dark:text-ui-dark-text">{activeAgentName}</dd>
            </div>
            <div className="flex justify-between gap-3">
              <dt className="text-ui-text-muted dark:text-ui-dark-text-muted">Channel</dt>
              <dd className="font-medium text-ui-text dark:text-ui-dark-text">
                {activeConversation.connection.name}
              </dd>
            </div>
            <div className="flex justify-between gap-3">
              <dt className="text-ui-text-muted dark:text-ui-dark-text-muted">Messages received</dt>
              <dd className="font-medium text-ui-text dark:text-ui-dark-text">
                {activeInboundCount}
              </dd>
            </div>
            <div className="flex justify-between gap-3">
              <dt className="text-ui-text-muted dark:text-ui-dark-text-muted">Messages sent</dt>
              <dd className="font-medium text-ui-text dark:text-ui-dark-text">
                {activeOutboundCount}
              </dd>
            </div>
            <div className="flex justify-between gap-3">
              <dt className="text-ui-text-muted dark:text-ui-dark-text-muted">Media</dt>
              <dd className="font-medium text-ui-text dark:text-ui-dark-text">
                {activeMediaCount}
              </dd>
            </div>
            <div className="flex justify-between gap-3">
              <dt className="text-ui-text-muted dark:text-ui-dark-text-muted">Last activity</dt>
              <dd className="text-right font-medium text-ui-text dark:text-ui-dark-text">
                {activeConversation.last_message_at
                  ? new Date(activeConversation.last_message_at).toLocaleString()
                  : '-'}
              </dd>
            </div>
          </dl>
          {activeConversation.contact.ctwa && (
            <div className="rounded-lg border border-emerald-100 bg-emerald-50 p-3 text-xs text-emerald-950 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-100">
              <div className="mb-2 flex items-center justify-between gap-2">
                <span className="font-semibold">Click-to-WhatsApp Ad</span>
                {activeConversation.contact.ctwa.source_url && (
                  <a
                    href={activeConversation.contact.ctwa.source_url}
                    target="_blank"
                    rel="noreferrer"
                    className="inline-flex items-center gap-1 text-emerald-700 hover:underline dark:text-emerald-300"
                  >
                    Open <ExternalLink className="h-3 w-3" />
                  </a>
                )}
              </div>
              {activeConversation.contact.ctwa.headline && (
                <p className="font-medium">{activeConversation.contact.ctwa.headline}</p>
              )}
              {activeConversation.contact.ctwa.body && (
                <p className="mt-1 leading-relaxed text-emerald-800 dark:text-emerald-200">
                  {activeConversation.contact.ctwa.body}
                </p>
              )}
              <div className="mt-2 space-y-1 text-[11px] text-emerald-700 dark:text-emerald-300">
                {activeConversation.contact.ctwa.source_id && (
                  <div>Ad/Post ID: {activeConversation.contact.ctwa.source_id}</div>
                )}
                {activeConversation.contact.ctwa.ctwa_clid && (
                  <div className="truncate">
                    Click ID: {activeConversation.contact.ctwa.ctwa_clid}
                  </div>
                )}
                {activeConversation.contact.ctwa.captured_at && (
                  <div>
                    Captured:{' '}
                    {new Date(activeConversation.contact.ctwa.captured_at).toLocaleString()}
                  </div>
                )}
              </div>
            </div>
          )}
          {activeConversation.contact.notes && (
            <div className="rounded-lg border border-gray-100 bg-gray-50 p-3 text-xs text-ui-text dark:border-ui-dark-border dark:bg-slate-800 dark:text-ui-dark-text">
              <div className="mb-1 text-[10px] font-medium uppercase tracking-wider text-ui-text-muted dark:text-ui-dark-text-muted">
                Contact notes
              </div>
              <p className="whitespace-pre-wrap break-words">{activeConversation.contact.notes}</p>
            </div>
          )}
        </div>
      )}
      {contactTab === 'notes' && (
        <div className="space-y-3">
          <textarea
            value={noteMode ? messageDraft : ''}
            onChange={(event) => {
              setNoteMode(true);
              setMessageDraft(event.target.value);
            }}
            rows={3}
            placeholder="Add internal note..."
            className="w-full resize-none rounded-btn border border-gray-200 bg-white px-3 py-2 text-sm dark:border-ui-dark-border dark:bg-slate-800"
          />
          <Button
            size="sm"
            onClick={() => {
              setNoteMode(true);
              sendInlineMessage();
            }}
            disabled={!noteMode || !messageDraft.trim()}
          >
            <Plus className="h-4 w-4" /> Add note
          </Button>
          <div className="rounded-lg border border-amber-100 bg-amber-50 p-3 text-xs text-amber-900 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200">
            Internal notes are saved to this conversation and visible to your team only.
          </div>
          <div className="space-y-2">
            {activeNotes.length === 0 ? (
              <div className="rounded-lg border border-dashed border-gray-200 p-4 text-center text-xs text-ui-text-muted dark:border-ui-dark-border dark:text-ui-dark-text-muted">
                No saved notes yet.
              </div>
            ) : (
              [...activeNotes].reverse().map((note) => (
                <div
                  key={note.id}
                  className="rounded-lg border border-gray-100 bg-white p-3 text-xs shadow-sm dark:border-ui-dark-border dark:bg-slate-800"
                >
                  <p className="whitespace-pre-wrap break-words text-ui-text dark:text-ui-dark-text">
                    {note.text_body}
                  </p>
                  <div className="mt-2 flex items-center justify-between gap-2 text-[10px] text-ui-text-muted dark:text-ui-dark-text-muted">
                    <span className="truncate">
                      {note.payload?.created_by?.name ?? 'Team note'}
                    </span>
                    <span>{new Date(note.created_at).toLocaleString()}</span>
                  </div>
                </div>
              ))
            )}
          </div>
        </div>
      )}
      {contactTab === 'media' && (
        <ContactGalleryPanel
          gallery={activeGallery}
          loading={galleryLoadingId === activeConversation.id}
          onPreview={(item) => {
            if (!item.url) return;
            setMediaPreview({
              type: item.type,
              url: item.url,
              title: item.filename || item.title || `${item.type} message`,
              filename: item.filename || item.title || undefined,
            });
          }}
        />
      )}
      {contactTab === 'activity' && (
        <div className="space-y-4 text-xs">
          {[
            ['Conversation opened', activeConversation.last_message_at],
            [
              `${activeInboundCount} ${activeInboundCount === 1 ? 'message' : 'messages'} received`,
              lastInboundAt,
            ],
            [
              `${activeOutboundCount} ${activeOutboundCount === 1 ? 'message' : 'messages'} sent`,
              lastOutboundAt,
            ],
            [`Assigned to ${activeAgentName}`, activeConversation.last_message_at],
          ].map(([item, time]) => (
            <div key={item} className="flex gap-3">
              <span className="mt-0.5 flex h-6 w-6 items-center justify-center rounded-full bg-gray-100 text-gray-600 dark:bg-slate-800 dark:text-slate-300">
                <RotateCcw className="h-3 w-3" />
              </span>
              <div>
                <div className="font-medium text-ui-text dark:text-ui-dark-text">{item}</div>
                <div className="text-ui-text-muted dark:text-ui-dark-text-muted">
                  {time ? new Date(String(time)).toLocaleString() : '-'}
                </div>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
