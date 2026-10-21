import assert from 'node:assert/strict';
import test from 'node:test';
import { RealtimeSubscriptions } from '../../resources/js/realtime/subscriptions.ts';

function fixture() {
    const handlers = new Map();
    const left = [];
    let bindings = 0;
    const subscriptions = new RealtimeSubscriptions({
        private(name) {
            return {
                listen(event, handler) { bindings++; handlers.set(`${name}:${event}`, handler); },
                stopListening(event) { handlers.delete(`${name}:${event}`); },
            };
        },
        leave(name) { left.push(name); },
    });
    return { subscriptions, handlers, left, bindings: () => bindings };
}

test('two components receive events and one cleanup preserves the other subscriber', () => {
    const f = fixture();
    const a = [], b = [];
    const stopA = f.subscriptions.subscribe('account.1', '.message', (data) => a.push(data));
    const stopB = f.subscriptions.subscribe('account.1', '.message', (data) => b.push(data));
    assert.equal(f.bindings(), 1);
    f.handlers.get('account.1:.message')('first');
    stopA();
    f.handlers.get('account.1:.message')('second');
    assert.deepEqual(a, ['first']);
    assert.deepEqual(b, ['first', 'second']);
    assert.deepEqual(f.left, []);
    stopB();
    assert.deepEqual(f.left, ['account.1']);
});

test('channel remains joined until every event subscription is removed', () => {
    const f = fixture();
    const first = f.subscriptions.subscribe('account.1', '.message', () => {});
    f.subscriptions.subscribe('account.1', '.call', () => {});
    first();
    assert.deepEqual(f.left, []);
    f.subscriptions.clear();
    assert.deepEqual(f.left, ['account.1']);
    assert.equal(f.handlers.size, 0);
});

test('cleanup from an old subscription cannot leave a newly subscribed channel', () => {
    const f = fixture();
    const stale = f.subscriptions.subscribe('account.1', '.message', () => {});
    f.subscriptions.unsubscribe('account.1');
    f.subscriptions.subscribe('account.1', '.message', () => {});
    stale();
    assert.equal(f.handlers.size, 1);
    assert.deepEqual(f.left, ['account.1']);
});

test('dispose leaves channels before the socket closes and ignores late component cleanups', () => {
    const f = fixture();
    const stop = f.subscriptions.subscribe('account.1', '.message', () => {});
    f.subscriptions.dispose();
    assert.deepEqual(f.left, ['account.1']);
    stop();
    f.subscriptions.unsubscribe('account.1');
    assert.deepEqual(f.left, ['account.1']);
    assert.equal(typeof f.subscriptions.subscribe('account.2', '.message', () => {}), 'function');
    assert.equal(f.bindings(), 1);
});

test('a transport error while subscribing or leaving never throws into a component', () => {
    const subscriptions = new RealtimeSubscriptions({
        private() { throw new Error('WebSocket is already in CLOSING or CLOSED state.'); },
        leave() { throw new Error('closed'); },
    });
    const stop = subscriptions.subscribe('account.1', '.message', () => {});
    assert.doesNotThrow(stop);
    assert.doesNotThrow(() => subscriptions.unsubscribe('account.1'));
});
