import assert from 'node:assert/strict';
import test from 'node:test';
import { campaignAudiencePayload } from '../../resources/js/lib/campaignAudience.ts';

test('selected contacts are sent as a contacts audience limited to those ids', () => {
    assert.deepEqual(campaignAudiencePayload('selected', {}, [4, 9]), {
        recipient_type: 'contacts',
        recipient_filters: { contact_ids: [4, 9] },
    });
});

test('other audiences never carry selected contact ids', () => {
    assert.deepEqual(campaignAudiencePayload('contacts', { contact_ids: [1] }, [1]), {
        recipient_type: 'contacts',
        recipient_filters: {},
    });
    assert.deepEqual(campaignAudiencePayload('segment', { segment_ids: [3], contact_ids: [1] }, [1]), {
        recipient_type: 'segment',
        recipient_filters: { segment_ids: [3] },
    });
});
