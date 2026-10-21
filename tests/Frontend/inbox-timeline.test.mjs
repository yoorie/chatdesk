import assert from 'node:assert/strict';
import test from 'node:test';
import {
  countLoadedMessages,
  resolveMessageCounts,
  visibleTimelineMessages,
} from '../../resources/js/Pages/WhatsApp/Conversations/inbox/timeline.ts';

const inbound = { id: 10, direction: 'inbound', type: 'text', text_body: 'Hi', created_at: '2026-10-17T10:00:00Z' };
const audit = (id, event_type, meta = {}) => ({
  id: `audit-${id}`,
  direction: 'inbound',
  type: 'automation_status',
  text_body: 'raw',
  payload: { event_type, meta: { status_kind: 'automation', inbound_message_id: 10, ...meta } },
  created_at: '2026-10-17T10:00:01Z',
});

test('a single inbound message with no AI assistant shows no system lines', () => {
  const thread = [
    inbound,
    audit(1, 'automation_running'),
    audit(2, 'ai_replying'),
    audit(3, 'automation_skipped', { ai_result: { reason: 'no_active_autopilot_agent' } }),
  ];
  assert.deepEqual(visibleTimelineMessages(thread), [inbound]);
});

test('a sent auto-reply shows one friendly line', () => {
  const thread = [inbound, audit(1, 'automation_running'), audit(2, 'ai_replying'), audit(3, 'automation_completed')];
  const visible = visibleTimelineMessages(thread);
  assert.equal(visible.length, 2);
  assert.equal(visible[1].text_body, 'AI replied');
});

test('duplicate failures for the same message collapse into one line', () => {
  const visible = visibleTimelineMessages([inbound, audit(1, 'automation_failed'), audit(2, 'automation_failed')]);
  assert.equal(visible.length, 2);
  assert.equal(visible[1].id, 'audit-2');
  assert.equal(visible[1].text_body, "Auto-reply couldn't be sent");
});

test('other timeline events (e.g. bot paused) are left alone', () => {
  const paused = { ...audit(5, 'bot_paused'), payload: { event_type: 'bot_paused', meta: {} }, text_body: 'Bot paused for this chat' };
  assert.deepEqual(visibleTimelineMessages([inbound, paused]), [inbound, paused]);
});

test('message counts include inbound and ignore system events, notes and reactions', () => {
  const counts = countLoadedMessages([
    inbound,
    audit(1, 'automation_running'),
    { id: 'note-1', direction: 'inbound', type: 'internal_note', text_body: 'n', created_at: '' },
    { id: 11, direction: 'outbound', type: 'reaction', text_body: null, created_at: '' },
  ]);
  assert.deepEqual(counts, { received: 1, sent: 0, total: 1 });
});

test('server counts win when higher, loaded counts when newer messages arrived', () => {
  assert.deepEqual(resolveMessageCounts({ received: 5, sent: 2 }, { received: 1, sent: 3, total: 4 }), {
    received: 5,
    sent: 3,
    total: 8,
  });
  assert.deepEqual(resolveMessageCounts(undefined, { received: 1, sent: 0, total: 1 }), {
    received: 1,
    sent: 0,
    total: 1,
  });
});
