/**
 * Maps the campaign form's "Who gets it" choice to what the server expects.
 * "selected" is a form-only audience for contacts picked on the Contacts page: it is
 * sent as recipient_type "contacts" limited to recipient_filters.contact_ids.
 */
export type CampaignAudience = 'contacts' | 'selected' | 'segment' | 'custom';

export function campaignAudiencePayload(
  audience: string,
  filters: Record<string, unknown> | null | undefined,
  selectedContactIds: number[]
): { recipient_type: string; recipient_filters: Record<string, unknown> } {
  if (audience === 'selected') {
    return {
      recipient_type: 'contacts',
      recipient_filters: { contact_ids: [...selectedContactIds] },
    };
  }

  const rest: Record<string, unknown> = { ...(filters ?? {}) };
  delete rest.contact_ids;
  if (audience !== 'segment') delete rest.segment_ids;

  return { recipient_type: audience, recipient_filters: rest };
}
