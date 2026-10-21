import assert from 'node:assert/strict';
import test from 'node:test';
import { usageStatus } from '../../resources/js/lib/usageStatus.ts';

test('count limits at the plan allowance are normal, not a warning', () => {
    const status = usageStatus(1, 1, 'count');
    assert.equal(status.summary, '1 of 1 used');
    assert.equal(status.level, 'ok');
    assert.equal(status.note, null);
});

test('count limits only warn when actually over the limit', () => {
    assert.equal(usageStatus(2, 3, 'count').level, 'ok');
    const over = usageStatus(3, 2, 'count');
    assert.equal(over.level, 'over');
    assert.equal(over.summary, '3 of 2 used');
    assert.match(over.note, /Over your plan limit/);
});

test('consumable limits warn when nearly used up or exhausted', () => {
    assert.equal(usageStatus(100, 1000, 'consumable').level, 'ok');
    const near = usageStatus(900, 1000, 'consumable');
    assert.equal(near.level, 'near');
    assert.match(near.note, /Almost used up/);
    assert.equal(usageStatus(1000, 1000, 'consumable').level, 'over');
});

test('unlimited limits never warn', () => {
    for (const limit of [-1, 9999, 9999999, undefined, null]) {
        const status = usageStatus(5000, limit, 'consumable');
        assert.equal(status.unlimited, true);
        assert.equal(status.level, 'ok');
    }
});
