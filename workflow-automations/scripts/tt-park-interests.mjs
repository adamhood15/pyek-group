// Verified Typhoon Texas park targeting. Houston and Austin share the TT audience,
// so each park maps to exactly one Mailchimp interest (group option).
// Category "Park Preference" in the TT audience (verified in Mailchimp). To re-verify: GET /lists/{TT list id}/interest-categories, then
// GET /lists/{TT list id}/interest-categories/{category_id}/interests.
// Until both IDs are set for a park, TT deployments for that park fail.
export const PARK_INTERESTS = {
  houston: { category_id: "0dd4c78350", interest_id: "e0d7836961" },
  austin: { category_id: "0dd4c78350", interest_id: "ee1f214d05" }
};
