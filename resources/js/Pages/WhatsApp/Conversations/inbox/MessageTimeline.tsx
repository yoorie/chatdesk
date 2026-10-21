import { friendlyError } from '@/lib/friendly';
import {
  AlertCircle,
  ClipboardList,
  File as FileIcon,
  Image as ImageIcon,
  Lock,
  MapPin,
  MessageSquare,
  Mic,
  Phone,
  Reply,
  Zap,
} from 'lucide-react';
import type { Dispatch, MutableRefObject, SetStateAction } from 'react';
import { Fragment } from 'react';
import { ChatAudioPlayer } from './ChatAudioPlayer';
import { ChatVideoCard } from './ChatVideoCard';
import {
  callTranscriptSpeaker,
  canReactToMessage,
  HOVER_REACTIONS,
  isCallTranscriptMessage,
  messageStatusIcon,
  timelineBadgeForMessage,
} from './helpers';
import { type ChatMessage, type MediaPreview } from './types';

interface Props {
  activeDisplayMessages: ChatMessage[];
  activeConversationLoading: boolean;
  formatDateKey: (value: string | null) => string;
  formatDateLabel: (value: string | null) => string;

  formatExactDateTime: (value: string | null) => string;
  formatTime: (value: string | null) => string;
  setReaction: (message: ChatMessage, reaction: string) => void;
  setReplyTo: Dispatch<SetStateAction<ChatMessage | null>>;
  messageReactions: Record<string, string>;
  activeMessageReactions: Record<string, string>;
  setMediaPreview: Dispatch<SetStateAction<MediaPreview | null>>;

  retryMessage: (message: ChatMessage) => void;
  messagesEndRef: MutableRefObject<HTMLDivElement | null>;
}

export function MessageTimeline({
  activeDisplayMessages,
  activeConversationLoading,
  formatDateKey,
  formatDateLabel,

  formatExactDateTime,
  formatTime,
  setReaction,
  setReplyTo,
  messageReactions,
  activeMessageReactions,
  setMediaPreview,

  retryMessage,
  messagesEndRef,
}: Props) {
  return (
    <div className="chat-bg min-h-0 flex-1 overflow-y-auto px-4 py-3 sm:px-6">
      <div className="mx-auto max-w-2xl space-y-3">
        {activeDisplayMessages.length === 0 && activeConversationLoading ? (
          <div className="py-16 text-center">
            <span className="mx-auto mb-3 block h-8 w-8 animate-spin rounded-full border-2 border-ui-green/30 border-t-ui-green" />
            <p className="text-sm font-medium text-ui-text dark:text-ui-dark-text">
              Loading messages
            </p>
          </div>
        ) : activeDisplayMessages.length === 0 ? (
          <div className="py-16 text-center">
            <MessageSquare className="mx-auto mb-3 h-10 w-10 text-gray-300" />
            <p className="text-sm font-medium text-ui-text dark:text-ui-dark-text">
              No messages loaded
            </p>
            <p className="mt-1 text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
              Messages for this chat will appear here.
            </p>
          </div>
        ) : (
          activeDisplayMessages.map((message, index) => {
            const previousMessage = activeDisplayMessages[index - 1];
            const showDateSeparator =
              !previousMessage ||
              formatDateKey(previousMessage.created_at) !== formatDateKey(message.created_at);
            const dateSeparator = showDateSeparator ? (
              <div className="flex justify-center">
                <span className="rounded-full bg-white/80 px-3 py-1 text-[11px] font-medium text-ui-text-muted shadow-sm dark:bg-slate-800/90 dark:text-ui-dark-text-muted">
                  {formatDateLabel(message.created_at)}
                </span>
              </div>
            ) : null;

            if (message.type === 'automation_status') {
              // Timeline events are a single quiet line, not a coloured badge.
              const eventType = String(message.payload?.event_type ?? '');
              const isFailed = eventType.endsWith('_failed');

              return (
                <Fragment key={message.id}>
                  {dateSeparator}
                  <div className="flex justify-center px-2">
                    <div
                      className={`inline-flex max-w-[92%] items-center gap-1.5 text-[11px] sm:max-w-[70%] ${
                        isFailed
                          ? 'text-red-600/80 dark:text-red-300/80'
                          : 'text-ui-text-muted dark:text-ui-dark-text-muted'
                      }`}
                    >
                      {isFailed ? (
                        <AlertCircle className="h-3 w-3 shrink-0" aria-hidden />
                      ) : (
                        <Zap className="h-3 w-3 shrink-0 text-ui-green" aria-hidden />
                      )}
                      <span className="truncate">{message.text_body}</span>
                      <span
                        className="text-[10px] opacity-75"
                        title={formatExactDateTime(message.created_at)}
                      >
                        · {formatTime(message.created_at)}
                      </span>
                    </div>
                  </div>
                </Fragment>
              );
            }

            if (message.type === 'internal_note') {
              const author = message.payload?.created_by?.name ?? 'Team note';
              return (
                <Fragment key={message.id}>
                  {dateSeparator}
                  <div className="flex justify-center px-2">
                    <div className="max-w-[92%] rounded-lg border border-ui-border bg-white px-3 py-2 text-xs text-ui-text shadow-sm dark:border-ui-dark-border dark:bg-slate-800 dark:text-ui-dark-text sm:max-w-[70%]">
                      <div className="mb-1 flex items-center gap-1.5 font-semibold text-ui-text dark:text-ui-dark-text">
                        <Lock className="h-3.5 w-3.5 text-ui-text-muted dark:text-ui-dark-text-muted" />
                        Internal note
                      </div>
                      <p className="whitespace-pre-wrap break-words">{message.text_body}</p>
                      <div className="mt-1.5 flex items-center justify-between gap-3 text-[10px] text-ui-text-muted dark:text-ui-dark-text-muted">
                        <span className="truncate">{author}</span>
                        <span title={formatExactDateTime(message.created_at)}>
                          {formatTime(message.created_at)}
                        </span>
                      </div>
                    </div>
                  </div>
                </Fragment>
              );
            }
            const outbound = message.direction === 'outbound';
            const isCallTranscript = isCallTranscriptMessage(message);
            const mediaPayload = message.payload?.[message.type] ?? {};
            const text =
              message.text_body ||
              (message.type === 'template'
                ? 'Template message'
                : message.type === 'location'
                  ? 'Location shared'
                  : '');
            const mediaUrl =
              message.payload?.media?.local_url ||
              message.payload?.link ||
              message.payload?.url ||
              mediaPayload?.link ||
              mediaPayload?.url;
            const mediaId =
              message.payload?.media?.id || message.payload?.media_id || mediaPayload?.id;
            const filename =
              message.payload?.filename ||
              mediaPayload?.filename ||
              message.payload?.document?.filename ||
              'Attachment';
            const timelineBadge = timelineBadgeForMessage(message);
            return (
              <Fragment key={message.id}>
                {dateSeparator}
                <div className={`flex ${outbound ? 'justify-end' : 'justify-start'}`}>
                  <div
                    className={`group relative max-w-[82%] rounded-lg px-4 py-2.5 text-sm shadow-sm ${isCallTranscript ? 'ring-1 ring-emerald-200 dark:ring-emerald-500/30' : ''} ${outbound ? 'bg-[#DCF8C6] text-ui-text dark:bg-[#005C4B] dark:text-white' : 'bg-white text-ui-text dark:bg-[#202C33] dark:text-ui-dark-text'}`}
                  >
                    <div
                      className={`inbox-message-actionbar pointer-events-none absolute top-1 z-20 flex items-center gap-1 overflow-x-auto rounded-full bg-white/95 px-1.5 py-1 opacity-0 shadow-pop ring-1 ring-gray-100 transition group-hover:pointer-events-auto group-hover:opacity-100 dark:bg-slate-800/95 dark:ring-slate-700 ${outbound ? 'right-1' : 'left-1'}`}
                      onClick={(event) => event.stopPropagation()}
                      onMouseDown={(event) => event.preventDefault()}
                    >
                      {canReactToMessage(message) && (
                        <>
                          {HOVER_REACTIONS.map((reaction) => (
                            <button
                              key={reaction}
                              type="button"
                              onClick={() => setReaction(message, reaction)}
                              className="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full text-base transition hover:bg-gray-100 dark:hover:bg-slate-700"
                              aria-label={`React ${reaction}`}
                            >
                              {reaction}
                            </button>
                          ))}
                          <span className="h-5 w-px bg-gray-100 dark:bg-slate-700" />
                        </>
                      )}
                      <button
                        type="button"
                        onClick={() => setReplyTo(message)}
                        className="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full text-ui-text-muted transition hover:bg-gray-100 hover:text-ui-text dark:text-ui-dark-text-muted dark:hover:bg-slate-700 dark:hover:text-ui-dark-text"
                        aria-label="Reply"
                      >
                        <Reply className="h-3.5 w-3.5" />
                      </button>
                    </div>
                    {(messageReactions[String(message.id)] ||
                      activeMessageReactions[String(message.id)]) && (
                      <span
                        className={`absolute -bottom-3 flex h-6 min-w-6 items-center justify-center rounded-full bg-white px-1.5 text-sm shadow-sm ring-1 ring-gray-100 dark:bg-slate-800 dark:ring-slate-700 ${outbound ? 'left-2' : 'right-2'}`}
                      >
                        {messageReactions[String(message.id)] ||
                          activeMessageReactions[String(message.id)]}
                      </span>
                    )}
                    {message.reply_to && (
                      <div className="mb-2 border-l-2 border-ui-green bg-white/45 px-2 py-1 text-[11px] text-ui-text-muted dark:bg-black/10 dark:text-ui-dark-text-muted">
                        {message.reply_to}
                      </div>
                    )}
                    {timelineBadge &&
                      (() => {
                        const TimelineIcon = timelineBadge.icon;
                        return (
                          <div
                            className={`mb-2 inline-flex items-center gap-1.5 rounded-full px-2 py-1 text-[10px] font-semibold uppercase tracking-wide ${timelineBadge.classes}`}
                          >
                            <TimelineIcon className="h-3 w-3" />
                            {timelineBadge.label}
                          </div>
                        );
                      })()}
                    {isCallTranscript && (
                      <div
                        className={`mb-2 inline-flex items-center gap-1.5 rounded-full px-2 py-1 text-[10px] font-semibold uppercase tracking-wide ${outbound ? 'bg-white/50 text-emerald-800 dark:bg-white/10 dark:text-emerald-100' : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-200'}`}
                      >
                        <Phone className="h-3 w-3" />
                        Call transcript · {callTranscriptSpeaker(message)}
                      </div>
                    )}
                    {message.type === 'interactive' && (
                      <div className="mb-2 rounded-lg bg-white/50 p-3 text-xs ring-1 ring-black/5 dark:bg-black/10 dark:ring-white/10">
                        <div className="mb-1 flex items-center gap-2 font-semibold">
                          <ClipboardList className="h-4 w-4" />
                          {message.payload?.interactive_type === 'flow'
                            ? 'Form'
                            : message.payload?.interactive_type === 'list'
                              ? 'List message'
                              : 'Reply buttons'}
                        </div>
                        {message.payload?.header_text && (
                          <div className="mb-1 font-medium">{message.payload.header_text}</div>
                        )}
                        <div className="whitespace-pre-wrap">{message.text_body}</div>
                        {message.payload?.buttons && (
                          <div className="mt-2 flex flex-wrap gap-1">
                            {message.payload.buttons.map((button: any) => (
                              <span
                                key={button.id || button.text}
                                className="rounded-full bg-ui-green-soft px-2 py-1 text-[11px] font-medium text-ui-green-dark dark:bg-ui-green/10 dark:text-ui-green"
                              >
                                {button.text}
                              </span>
                            ))}
                          </div>
                        )}
                        {message.payload?.interactive?.action?.sections?.[0]?.rows && (
                          <div className="mt-2 space-y-1">
                            {message.payload.interactive.action.sections[0].rows
                              .slice(0, 4)
                              .map((row: any) => (
                                <div
                                  key={row.id}
                                  className="rounded-md bg-white/60 px-2 py-1 dark:bg-slate-900/50"
                                >
                                  {row.title}
                                </div>
                              ))}
                          </div>
                        )}
                        {message.payload?.flow_id && (
                          <div className="mt-2 rounded-md bg-white/60 px-2 py-1 dark:bg-slate-900/50">
                            WhatsApp form
                          </div>
                        )}
                      </div>
                    )}
                    {message.type === 'image' && mediaUrl && (
                      <button
                        type="button"
                        onClick={() =>
                          setMediaPreview({
                            type: 'image',
                            url: mediaUrl,
                            title: filename,
                            filename,
                          })
                        }
                        className="mb-2 block overflow-hidden rounded-md text-left"
                      >
                        <img
                          src={mediaUrl}
                          alt={filename}
                          className="max-h-64 w-full object-cover"
                        />
                      </button>
                    )}
                    {message.type === 'image' && !mediaUrl && (
                      <div className="mb-2 flex min-h-32 items-center justify-center rounded-md bg-white/50 px-3 py-6 text-center text-xs ring-1 ring-black/5 dark:bg-black/10 dark:ring-white/10">
                        <div>
                          <ImageIcon className="mx-auto mb-2 h-6 w-6 text-ui-text-muted dark:text-ui-dark-text-muted" />
                          <div className="font-medium">Image received</div>
                          {mediaId && (
                            <div className="mt-1 max-w-48 truncate text-[10px] opacity-70">
                              Meta media ID: {mediaId}
                            </div>
                          )}
                        </div>
                      </div>
                    )}
                    {message.type === 'sticker' && mediaUrl && (
                      <button
                        type="button"
                        onClick={() =>
                          setMediaPreview({
                            type: 'sticker',
                            url: mediaUrl,
                            title: 'Sticker',
                            filename: 'Sticker',
                          })
                        }
                        className="mb-2 block w-fit overflow-hidden rounded-md text-left"
                      >
                        <img
                          src={mediaUrl}
                          alt="Sticker"
                          className="max-h-40 max-w-40 object-contain"
                        />
                      </button>
                    )}
                    {message.type === 'sticker' && !mediaUrl && (
                      <div className="mb-2 flex min-h-28 items-center justify-center rounded-md bg-white/50 px-3 py-5 text-center text-xs ring-1 ring-black/5 dark:bg-black/10 dark:ring-white/10">
                        <div>
                          <ImageIcon className="mx-auto mb-2 h-6 w-6 text-ui-text-muted dark:text-ui-dark-text-muted" />
                          <div className="font-medium">Sticker received</div>
                          {mediaId && (
                            <div className="mt-1 max-w-48 truncate text-[10px] opacity-70">
                              Meta media ID: {mediaId}
                            </div>
                          )}
                        </div>
                      </div>
                    )}
                    {message.type === 'video' && mediaUrl && (
                      <ChatVideoCard
                        src={mediaUrl}
                        title={filename}
                        outbound={outbound}
                        onPreview={() =>
                          setMediaPreview({
                            type: 'video',
                            url: mediaUrl,
                            title: filename,
                            filename,
                          })
                        }
                      />
                    )}
                    {message.type === 'video' && !mediaUrl && (
                      <div className="mb-2 flex items-center gap-2 rounded-md bg-white/50 px-3 py-3 text-xs ring-1 ring-black/5 dark:bg-black/10 dark:ring-white/10">
                        <ImageIcon className="h-4 w-4" />
                        <span className="min-w-0 flex-1 truncate">
                          Video received{mediaId ? ` (${mediaId})` : ''}
                        </span>
                      </div>
                    )}
                    {message.type === 'audio' && mediaUrl && (
                      <ChatAudioPlayer
                        src={mediaUrl}
                        title={filename}
                        outbound={outbound}
                        voice={Boolean(message.payload?.voice || mediaPayload?.voice)}
                        onPreview={() =>
                          setMediaPreview({
                            type: 'audio',
                            url: mediaUrl,
                            title: filename,
                            filename,
                          })
                        }
                      />
                    )}
                    {message.type === 'audio' && !mediaUrl && (
                      <div className="mb-2 flex items-center gap-2 rounded-md bg-white/50 px-3 py-3 text-xs ring-1 ring-black/5 dark:bg-black/10 dark:ring-white/10">
                        <Mic className="h-4 w-4" />
                        <span className="min-w-0 flex-1 truncate">
                          {mediaPayload?.voice ? 'Voice message received' : 'Audio received'}
                          {mediaId ? ` (${mediaId})` : ''}
                        </span>
                      </div>
                    )}
                    {message.type === 'document' && mediaUrl && (
                      <button
                        type="button"
                        onClick={() =>
                          setMediaPreview({
                            type: 'document',
                            url: mediaUrl,
                            title: filename,
                            filename,
                          })
                        }
                        className="mb-2 flex w-full items-center gap-2 rounded-md bg-white/50 px-3 py-2 text-left text-xs ring-1 ring-black/5 dark:bg-black/10 dark:ring-white/10"
                      >
                        <FileIcon className="h-4 w-4" />
                        <span className="truncate">{filename}</span>
                      </button>
                    )}
                    {message.type === 'document' && !mediaUrl && (
                      <div className="mb-2 flex items-center gap-2 rounded-md bg-white/50 px-3 py-3 text-xs ring-1 ring-black/5 dark:bg-black/10 dark:ring-white/10">
                        <FileIcon className="h-4 w-4" />
                        <span className="min-w-0 flex-1 truncate">
                          {filename}
                          {mediaId ? ` (${mediaId})` : ''}
                        </span>
                      </div>
                    )}
                    {message.type === 'location' && (
                      <div className="mb-2 flex items-center gap-2 rounded-md bg-white/50 px-3 py-2 text-xs ring-1 ring-black/5 dark:bg-black/10 dark:ring-white/10">
                        <MapPin className="h-4 w-4" />
                        <span>{message.payload?.name || text}</span>
                      </div>
                    )}
                    {text && <p className="whitespace-pre-wrap break-words">{text}</p>}
                    <div
                      className={`mt-1.5 flex items-center justify-end gap-1 text-[10px] ${outbound ? 'text-gray-600 dark:text-emerald-100' : 'text-ui-text-muted dark:text-ui-dark-text-muted'}`}
                    >
                      <span title={formatExactDateTime(message.created_at)}>
                        {formatTime(message.created_at)}
                      </span>
                      {outbound && (
                        <span className="inline-flex items-center gap-1">
                          {messageStatusIcon(message)}
                          {message.status === 'failed' && (
                            <button
                              type="button"
                              onClick={() => retryMessage(message)}
                              className="ml-1 rounded px-1 text-[10px] font-semibold text-red-600 transition hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-500/10"
                            >
                              Try again
                            </button>
                          )}
                        </span>
                      )}
                    </div>
                    {outbound && message.status === 'failed' && (
                      <p className="mt-1 text-right text-[11px] leading-4 text-red-600 dark:text-red-300">
                        {friendlyError(message.error_message, 'This message was not delivered.')}
                      </p>
                    )}
                  </div>
                </div>
              </Fragment>
            );
          })
        )}
        <div ref={messagesEndRef} />
      </div>
    </div>
  );
}
