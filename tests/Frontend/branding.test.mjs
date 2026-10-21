import assert from 'node:assert/strict';
import test from 'node:test';
import { formatPageTitle, hexToRgb } from '../../resources/js/lib/branding.ts';

test('branding is consistent for default, custom and already branded page titles', () => {
    assert.equal(formatPageTitle('Inbox'), 'Inbox - ChatDesk');
    assert.equal(formatPageTitle('Inbox', 'Acme'), 'Inbox - Acme');
    assert.equal(formatPageTitle('Inbox - ChatDesk'), 'Inbox - ChatDesk');
    assert.equal(formatPageTitle('ChatDesk | WhatsApp platform'), 'ChatDesk | WhatsApp platform');
    assert.equal(formatPageTitle(''), 'ChatDesk');
});

test('brand colors accept six-digit hex and reject unsafe or malformed input', () => {
    assert.equal(hexToRgb('#00A548'), '0 165 72');
    assert.equal(hexToRgb('#053A35'), '5 58 53');
    assert.equal(hexToRgb('red; background:url(example)'), null);
    assert.equal(hexToRgb(undefined), null);
});
