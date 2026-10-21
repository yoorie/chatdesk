import assert from 'node:assert/strict';
import test from 'node:test';
import { annualSavings } from '../../resources/js/lib/pricing.ts';

test('yearly savings use the actual billed total, not the rounded monthly equivalent', () => {
  assert.deepEqual(annualSavings(99900, 999000), { amount: 199800, percent: 16.7 });
  assert.deepEqual(annualSavings(199900, 1999000), { amount: 399800, percent: 16.7 });
  assert.deepEqual(annualSavings(10000, 96000), { amount: 24000, percent: 20 });
});
test('missing, invalid, free, and more expensive annual prices never advertise a discount', () => {
  for (const yearly of [undefined, null, 0, -1, NaN, Infinity, 120000, 130000])
    assert.equal(annualSavings(10000, yearly), null);
  assert.equal(annualSavings(0, 10000), null);
});
