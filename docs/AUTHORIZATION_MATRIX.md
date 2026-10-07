# Authorization matrix (Phase 12–13)

**Venue = building. Workspace = unit (bookable).**

| Action | Guest | User | Owner (own) | Owner (other) | Admin |
|--------|-------|------|-------------|-----------------|-------|
| List published venues/units | ✓ | ✓ | ✓ | ✓ | ✓ |
| View published venue / unit | ✓ | ✓ | ✓ | ✓ | ✓ |
| View draft/archived | ✗ | ✗ | own only | ✗ | ✓ |
| Create venue | ✗ | ✗ | ✓ | ✗ | ✓ (pick owner) |
| Edit venue / map pin | ✗ | ✗ | own | ✗ | ✓ |
| Transfer venue owner | ✗ | ✗ | ✗ | ✗ | ✓ (confirm + audit) |
| Add/edit unit | ✗ | ✗ | own venue | ✗ | ✓ |
| Manage hours / exceptions / pause | ✗ | ✗ | own | ✗ | ✓ |
| Archive unit (no future pending/confirmed) | ✗ | ✗ | own | ✗ | ✓ |
| Archive unit with future pending/confirmed | ✗ | ✗ | blocked | ✗ | blocked |
| Book a published unit (within open hours) | ✗ | ✓ | ✓ | ✓ | ✓ |
| Manage offers (unit or venue scope) | ✗ | ✗ | own | ✗ | ✓ |
| Reports / ledger / payouts | ✗ | ✗ | own totals | ✗ | all |
| Public map markers JSON | ✓ (published only) | ✓ | ✓ | ✓ | ✓ |

Suspended owners: venues/units hidden from public (`owner.is_active`), existing bookings/payments retained.

**Availability:** closures/pauses that conflict with future pending/confirmed bookings require explicit acknowledgment; bookings are never auto-cancelled.

See also `WorkspacePolicy`, `VenuePolicy`, `BookingPolicy`, `OfferPolicy`, and README Roles.
