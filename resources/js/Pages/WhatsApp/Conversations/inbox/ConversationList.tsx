import { friendlyStatus } from '@/lib/friendly';
import TextInput from '@/Components/TextInput';
import { Badge } from '@/Components/UI/Badge';
import { EmptyState } from '@/Components/UI/EmptyState';
import { ConversationSkeleton } from '@/Components/UI/Skeleton';
import {
  BellRing,
  CheckSquare,
  Clock,
  Edit3,
  MessageSquare,
  Search,
  Star,
  User,
  Wifi,
  WifiOff,
  X,
  Zap,
} from 'lucide-react';
import type { Dispatch, SetStateAction } from 'react';
import {
  conversationModeLabel,
  conversationSlaLabel,
  conversationSourceLabel,
  customerServiceWindowFor,
  getConversationLabels,
  INBOX_LABELS,
  labelClasses,
  slaClasses,
} from './helpers';
import { type Agent, type Conversation } from './types';

interface Props {
  mobileShowDetail: boolean;
  filteredConversations: Conversation[];
  connected: boolean;
  notificationPermission: NotificationPermission;
  requestBrowserNotifications: () => Promise<void>;
  setBulkMode: Dispatch<SetStateAction<boolean>>;
  bulkMode: boolean;
  setSelectedIds: Dispatch<SetStateAction<Set<number>>>;

  setShowNewChat: Dispatch<SetStateAction<boolean>>;
  searchQuery: string;
  setSearchQuery: Dispatch<SetStateAction<string>>;
  setStarredOnly: Dispatch<SetStateAction<boolean>>;
  starredOnly: boolean;
  setAssigneeFilter: Dispatch<SetStateAction<'all' | 'me' | 'unassigned'>>;
  setStatusFilter: Dispatch<SetStateAction<string>>;
  assigneeFilter: 'all' | 'me' | 'unassigned';
  statusFilter: string;
  conversations: Conversation[];
  setLabelFilter: Dispatch<SetStateAction<string>>;
  labelFilter: string;
  selectedIds: Set<number>;
  connections: { id: number; name: string }[] | undefined;
  connectionFilter: number | 'all';
  setConnectionFilter: Dispatch<SetStateAction<number | 'all'>>;

  loading: boolean;

  activeConversation: Conversation | null;
  agents: Agent[];
  replyWindowNow: number;
  toggleSelectedConversation: (conversationId: number) => void;
  setActiveId: Dispatch<SetStateAction<number | null>>;
  setMobileShowDetail: Dispatch<SetStateAction<boolean>>;
  formatRelative: (value: string | null) => string;
}

export function ConversationList({
  mobileShowDetail,
  filteredConversations,
  connected,
  notificationPermission,
  requestBrowserNotifications,
  setBulkMode,
  bulkMode,
  setSelectedIds,

  setShowNewChat,
  searchQuery,
  setSearchQuery,
  setStarredOnly,
  starredOnly,
  setAssigneeFilter,
  setStatusFilter,
  assigneeFilter,
  statusFilter,
  conversations,
  setLabelFilter,
  labelFilter,
  selectedIds,
  connections,
  connectionFilter,
  setConnectionFilter,

  loading,

  activeConversation,
  agents,
  replyWindowNow,
  toggleSelectedConversation,
  setActiveId,
  setMobileShowDetail,
  formatRelative,
}: Props) {
  return (
    <section
      className={`${mobileShowDetail ? 'hidden lg:flex' : 'flex'} min-h-0 min-w-0 w-full flex-col border-r border-gray-100 bg-white dark:border-ui-dark-border dark:bg-slate-900`}
    >
      <div className="flex-shrink-0 border-b border-gray-100 p-4 dark:border-ui-dark-border">
        <div className="mb-3 flex items-center justify-between">
          <div>
            <h1 className="text-base font-semibold text-ui-text dark:text-ui-dark-text">Inbox</h1>
            <p className="text-[11px] text-ui-text-muted dark:text-ui-dark-text-muted">
              {filteredConversations.length} conversations
            </p>
          </div>
          <div className="flex items-center gap-1">
            <span
              className={`mr-1 hidden items-center gap-1 rounded-full px-2 py-1 text-[11px] sm:inline-flex ${connected ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' : 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300'}`}
            >
              {connected ? <Wifi className="h-3 w-3" /> : <WifiOff className="h-3 w-3" />}
              {connected ? 'Live' : 'Polling'}
            </span>
            {notificationPermission !== 'granted' && (
              <button
                type="button"
                onClick={() => void requestBrowserNotifications()}
                className="inline-flex h-9 items-center gap-1 rounded-btn bg-emerald-50 px-2 text-[11px] font-medium text-emerald-700 transition hover:bg-emerald-100 dark:bg-emerald-500/10 dark:text-emerald-300 dark:hover:bg-emerald-500/15"
                title="Enable browser notifications"
              >
                <BellRing className="h-3.5 w-3.5" />
                <span className="hidden sm:inline">Alerts</span>
              </button>
            )}
            <button
              type="button"
              onClick={() => {
                setBulkMode(!bulkMode);
                setSelectedIds(new Set());
              }}
              className={`flex h-9 w-9 items-center justify-center rounded-btn transition ${bulkMode ? 'bg-ui-green-soft text-ui-green-dark dark:bg-ui-green/10 dark:text-ui-green' : 'text-ui-text-muted hover:bg-gray-100 hover:text-ui-text dark:text-ui-dark-text-muted dark:hover:bg-slate-800 dark:hover:text-ui-dark-text'}`}
              title={bulkMode ? 'Exit bulk select' : 'Bulk select'}
            >
              {bulkMode ? <X className="h-4 w-4" /> : <CheckSquare className="h-4 w-4" />}
            </button>
            <button
              type="button"
              onClick={() => setShowNewChat(true)}
              className="flex h-9 w-9 items-center justify-center rounded-btn text-ui-text-muted transition hover:bg-gray-100 hover:text-ui-text dark:text-ui-dark-text-muted dark:hover:bg-slate-800 dark:hover:text-ui-dark-text"
              title="New chat"
            >
              <Edit3 className="h-4 w-4" />
            </button>
          </div>
        </div>
        <div className="relative">
          <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
          <TextInput
            type="search"
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            placeholder="Search name or message..."
            className="pl-9 dark:bg-slate-800"
          />
        </div>
        <div className="mt-3 flex items-center gap-2">
          <select className="flex-1 rounded-btn border-gray-200 px-3 py-1.5 text-xs shadow-sm focus:border-ui-green focus:ring-ui-green/20 dark:border-ui-dark-border dark:bg-slate-800">
            <option>Newest first</option>
            <option>Priority</option>
          </select>
          <button
            type="button"
            onClick={() => setStarredOnly(!starredOnly)}
            className={`flex h-9 w-9 items-center justify-center rounded-btn transition ${starredOnly ? 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-300' : 'text-ui-text-muted hover:bg-gray-100 dark:text-ui-dark-text-muted dark:hover:bg-slate-800'}`}
            aria-label="Starred only"
          >
            <Star className={`h-4 w-4 ${starredOnly ? 'fill-amber-400' : ''}`} />
          </button>
        </div>
        <div className="mt-3 flex items-center gap-2 overflow-x-auto">
          {[
            ['all', 'All'],
            ['mine', 'Mine'],
            ['unassigned', 'Unassigned'],
            ['starred', 'Starred'],
            ['closed', 'Resolved'],
          ].map(([value, label]) => (
            <button
              key={value}
              type="button"
              onClick={() => {
                if (value === 'mine') {
                  setAssigneeFilter('me');
                  setStatusFilter('all');
                  setStarredOnly(false);
                } else if (value === 'unassigned') {
                  setAssigneeFilter('unassigned');
                  setStatusFilter('all');
                  setStarredOnly(false);
                } else if (value === 'starred') {
                  setStarredOnly(true);
                  setAssigneeFilter('all');
                  setStatusFilter('all');
                } else {
                  setStatusFilter(value);
                  setAssigneeFilter('all');
                  setStarredOnly(false);
                }
              }}
              className={`h-7 flex-shrink-0 rounded-full px-2.5 text-[11px] font-medium transition ${(value === 'mine' && assigneeFilter === 'me') || (value === 'unassigned' && assigneeFilter === 'unassigned') || (value === 'starred' && starredOnly) || (value === statusFilter && !starredOnly && assigneeFilter === 'all') ? 'bg-ui-green-soft text-ui-green-dark dark:bg-ui-green/10 dark:text-ui-green' : 'text-ui-text-muted hover:bg-gray-100 dark:text-ui-dark-text-muted dark:hover:bg-slate-800'}`}
            >
              {label}
            </button>
          ))}
        </div>
        <div className="-mx-1 mt-2 flex gap-1 overflow-x-auto px-1">
          {[
            { id: 'all', label: 'All labels', count: filteredConversations.length },
            ...INBOX_LABELS.map((label) => ({
              ...label,
              count: conversations.filter((conversation) =>
                getConversationLabels(conversation).includes(label.id)
              ).length,
            })),
          ].map((label: any) => (
            <button
              key={label.id}
              type="button"
              onClick={() => setLabelFilter(label.id)}
              className={`h-7 flex-shrink-0 rounded-full px-2.5 text-[11px] font-medium transition ${labelFilter === label.id ? 'bg-ui-green-soft text-ui-green-dark dark:bg-ui-green/10 dark:text-ui-green' : 'text-ui-text-muted hover:bg-gray-100 dark:text-ui-dark-text-muted dark:hover:bg-slate-800'}`}
            >
              {label.label}
              {label.count > 0 && <span className="ml-1 opacity-70">{label.count}</span>}
            </button>
          ))}
        </div>
        {bulkMode && (
          <div className="mt-3 flex items-center justify-between rounded-lg bg-gray-50 px-3 py-2 text-xs text-ui-text-muted dark:bg-slate-800 dark:text-ui-dark-text-muted">
            <span>{selectedIds.size} selected</span>
            <button
              type="button"
              onClick={() =>
                setSelectedIds(
                  new Set(filteredConversations.map((conversation) => conversation.id))
                )
              }
              className="font-medium text-ui-green-dark dark:text-ui-green"
            >
              Select visible
            </button>
          </div>
        )}
        <div className="mt-3 grid grid-cols-2 gap-2">
          {connections && connections.length > 0 && (
            <select
              value={connectionFilter}
              onChange={(e) =>
                setConnectionFilter(e.target.value === 'all' ? 'all' : Number(e.target.value))
              }
              className="rounded-btn border-gray-200 px-3 py-1.5 text-xs shadow-sm focus:border-ui-green focus:ring-ui-green/20 dark:border-ui-dark-border dark:bg-slate-800"
            >
              <option value="all">All numbers</option>
              {connections.map((conn) => (
                <option key={conn.id} value={conn.id}>
                  {conn.name}
                </option>
              ))}
            </select>
          )}
          <select
            value={assigneeFilter}
            onChange={(e) => setAssigneeFilter(e.target.value as 'all' | 'me' | 'unassigned')}
            className="rounded-btn border-gray-200 px-3 py-1.5 text-xs shadow-sm focus:border-ui-green focus:ring-ui-green/20 dark:border-ui-dark-border dark:bg-slate-800"
          >
            <option value="all">All assignees</option>
            <option value="me">Mine</option>
            <option value="unassigned">Unassigned</option>
          </select>
        </div>
      </div>

      <div className="min-h-0 flex-1 overflow-y-auto">
        {loading ? (
          <div className="p-2">
            {[...Array(5)].map((_, i) => (
              <ConversationSkeleton key={i} />
            ))}
          </div>
        ) : filteredConversations.length === 0 ? (
          <div className="p-6">
            <EmptyState
              icon={MessageSquare}
              title="No conversations here"
              description="Try a different filter. New WhatsApp messages from customers will show up here."
            />
          </div>
        ) : (
          filteredConversations.map((conversation) => {
            const isActive = activeConversation?.id === conversation.id;
            const isSelected = selectedIds.has(conversation.id);
            const assigneeName = conversation.assigned_to
              ? (agents.find((a) => a.id === conversation.assigned_to)?.name ?? 'Assigned')
              : 'Unassigned';
            const labels = getConversationLabels(conversation);
            const unreadCount = Number(conversation.unread_count ?? 0);
            const sla = conversationSlaLabel(conversation);
            const replyWindow = customerServiceWindowFor(conversation, [], replyWindowNow);
            const sourceLabel = conversationSourceLabel(conversation);
            const modeLabel = conversationModeLabel(conversation);
            return (
              <div
                key={conversation.id}
                className={`flex w-full items-start gap-2 border-b border-gray-50 px-3 py-2.5 text-left transition dark:border-ui-dark-border ${isActive ? 'bg-ui-green-soft/60 dark:bg-emerald-950/40' : 'hover:bg-gray-50 dark:hover:bg-slate-800'}`}
              >
                {bulkMode && (
                  <input
                    type="checkbox"
                    checked={isSelected}
                    onChange={() => toggleSelectedConversation(conversation.id)}
                    className="mt-3 rounded border-gray-300 text-ui-green focus:ring-ui-green/30 dark:border-ui-dark-border dark:bg-slate-800"
                    aria-label={`Select ${conversation.contact.name || conversation.contact.wa_id}`}
                  />
                )}
                <button
                  type="button"
                  onClick={() => {
                    if (bulkMode) {
                      toggleSelectedConversation(conversation.id);
                      return;
                    }
                    setActiveId(conversation.id);
                    setMobileShowDetail(true);
                  }}
                  className="flex min-w-0 flex-1 items-start gap-2.5 text-left"
                >
                  <div className="relative flex-shrink-0">
                    <div className="flex h-9 w-9 items-center justify-center rounded-full bg-ui-green text-sm font-semibold text-white">
                      {conversation.contact.name?.charAt(0).toUpperCase() ||
                        conversation.contact.wa_id.charAt(0)}
                    </div>
                    <span className="absolute bottom-0 right-0 h-3 w-3 rounded-full bg-emerald-500 ring-2 ring-white dark:ring-slate-900" />
                    {conversation.priority === 'urgent' && (
                      <span className="absolute -left-0.5 -top-0.5 h-2.5 w-2.5 rounded-full bg-red-500 ring-2 ring-white dark:ring-slate-900" />
                    )}
                    {unreadCount > 0 && (
                      <span className="absolute -right-1.5 -top-1.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-ui-green px-1 text-[9px] font-bold text-white ring-2 ring-white dark:ring-slate-900">
                        {unreadCount > 9 ? '9+' : unreadCount}
                      </span>
                    )}
                  </div>
                  <div className="min-w-0 flex-1">
                    <div className="flex items-center justify-between gap-2">
                      <span className="truncate text-sm font-semibold text-ui-text dark:text-ui-dark-text">
                        {conversation.contact.name || conversation.contact.wa_id}
                      </span>
                      <span className="flex-shrink-0 text-[11px] text-ui-text-muted dark:text-ui-dark-text-muted">
                        {formatRelative(conversation.last_message_at)}
                      </span>
                    </div>
                    <p className="mt-0.5 truncate text-xs text-ui-text-muted dark:text-ui-dark-text-muted">
                      {conversation.last_message_preview || 'No messages yet'}
                    </p>
                    <div className="mt-1.5 flex min-w-0 items-center gap-1 overflow-hidden">
                      <span
                        className={`inline-flex flex-shrink-0 items-center gap-1 rounded-full px-1.5 py-0.5 text-[10px] font-medium ${slaClasses(sla.tone)}`}
                      >
                        <Clock className="h-2.5 w-2.5" />
                        {sla.label}
                      </span>
                      <span
                        className={`inline-flex flex-shrink-0 items-center gap-1 rounded-full px-1.5 py-0.5 text-[10px] font-medium ${slaClasses(replyWindow.tone)}`}
                        title={replyWindow.detail}
                      >
                        <MessageSquare className="h-2.5 w-2.5" />
                        {replyWindow.state === 'closed'
                          ? 'Template required'
                          : replyWindow.state === 'none'
                            ? 'No window'
                            : replyWindow.label.replace('Reply window ', '')}
                      </span>
                      <span className="min-w-0 truncate rounded-full bg-gray-100 px-1.5 py-0.5 text-[10px] font-medium text-gray-600 dark:bg-slate-800 dark:text-slate-300">
                        {sourceLabel}
                      </span>
                      <span
                        className={`min-w-0 truncate rounded-full px-1.5 py-0.5 text-[10px] font-medium ${conversation.assigned_to ? 'bg-white text-ui-text-muted ring-1 ring-gray-100 dark:bg-slate-900 dark:text-ui-dark-text-muted dark:ring-slate-700' : 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300'}`}
                      >
                        {assigneeName}
                      </span>
                    </div>
                    <div className="mt-1 flex flex-wrap items-center gap-1">
                      {conversation.priority === 'urgent' && (
                        <Star className="h-3 w-3 fill-amber-400 text-amber-500" />
                      )}
                      <Badge
                        variant={
                          conversation.status === 'open'
                            ? 'success'
                            : conversation.status === 'pending'
                              ? 'warning'
                              : 'default'
                        }
                        className="px-2 py-0.5 text-[10px]"
                      >
                        {friendlyStatus(conversation.status)}
                      </Badge>
                      <span
                        className={`inline-flex items-center gap-1 rounded-full px-1.5 py-0.5 text-[10px] font-medium ${conversation.automation_processing ? 'bg-purple-50 text-purple-700 dark:bg-purple-500/10 dark:text-purple-200' : conversation.bot_paused || conversation.handoff_status === 'manual' ? 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300' : conversation.automation_state ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' : 'bg-gray-100 text-gray-600 dark:bg-slate-800 dark:text-slate-300'}`}
                      >
                        {conversation.automation_processing ? (
                          <Zap className="h-2.5 w-2.5" />
                        ) : conversation.bot_paused || conversation.handoff_status === 'manual' ? (
                          <User className="h-2.5 w-2.5" />
                        ) : (
                          <Zap className="h-2.5 w-2.5" />
                        )}
                        {modeLabel}
                      </span>
                      {labels.slice(0, 2).map((labelId) => {
                        const label = INBOX_LABELS.find((item) => item.id === labelId);
                        return label ? (
                          <span
                            key={label.id}
                            className={`rounded-md px-1.5 py-0.5 text-[10px] font-medium ${labelClasses(label.color)}`}
                          >
                            {label.label}
                          </span>
                        ) : null;
                      })}
                    </div>
                  </div>
                </button>
              </div>
            );
          })
        )}
      </div>
    </section>
  );
}
