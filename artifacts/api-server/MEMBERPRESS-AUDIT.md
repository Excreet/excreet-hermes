# MemberPress Replacement Audit
**Date:** 2026-05-15  
**Site:** excreet.com  
**Trigger:** Stop payment issued on $599 MemberPress renewal

---

## 1. Current MemberPress Footprint

### Scale
| Metric | Value |
|--------|-------|
| Members in wp_mepr_members | 100 |
| Active subscriptions (MemberPress) | 0 |
| Paid transactions (MemberPress) | 0 |
| Products defined | 4 |
| Access rules | 6 |

**Key finding:** MemberPress is being used purely for access control and member identity — not for payment processing. Zero transactions have run through it. This makes migration significantly simpler: no billing history to port, no active subscription billing to transfer mid-cycle.

### Membership Products
| Product | Type |
|---------|------|
| Excreet WHealth Membership | General access |
| Excreet Membership — Starter (10 Sessions) | Session pack |
| Excreet Membership — Premium (20 Sessions) | Session pack |
| Unlimited Q&A Add-On — 30 Days | Monthly add-on |

### Protected Pages (Access Rules)
1. Member Intake Form
2. Intake Processing
3. Member Dashboard
4. Single Page: Member Dashboard
5. Single Page: Gut Snapshot
6. Single Page: Welcome Member

### MemberPress API Surface in Mu-Plugins
These are the exact calls that need to be swapped when migrating:

| File | MemberPress Call | Purpose |
|------|-----------------|---------|
| patch-291.php | `new MeprUser(get_current_user_id())` | Get current user membership |
| patch-291.php | `mepr-rules` post type, `_mepr_type/_mepr_content/_mepr_products` meta | Programmatically create access rules |
| patch-293.php | `$mepr_user->active_product_subscriptions('ids')` | Check if user has Premium |
| patch-294.php | `mepr-transaction-completed` action hook | Trigger logic on purchase |
| patch-296.php | `new MeprUser(get_current_user_id())` | Member identity check |
| patch-296.php | `.mepr-login-form-wrapper`, `.mepr-form`, `#mepr_loginform`, `.mepr-submit` CSS | Login form styling |

---

## 2. Candidate Replacements

### A — Paid Memberships Pro (PMPro)
**Cost:** Free core; $297/yr Plus (not needed for current feature set)  
**Stripe:** Built into free core (no paid tier required as of 2024)  
**Official MP migration tool:** Yes — `pmpro-import-users-from-csv` + official MemberPress→PMPro importer  
**License model:** GPL, self-hosted, no per-transaction fees

**API equivalents:**
| MemberPress | PMPro |
|-------------|-------|
| `new MeprUser($id)->active_product_subscriptions('ids')` | `pmpro_hasMembershipLevel($level_id, $user_id)` |
| `mepr-transaction-completed` hook | `pmpro_after_checkout` hook |
| `mepr-rules` post type | PMPro native content restriction (shortcode or admin rules) |
| `.mepr-form`, `.mepr-submit` | `.pmpro_form`, `.pmpro_btn-submit` |

**Session-pack model:** PMPro supports one-time (non-recurring) levels — matches Starter/Premium session packs exactly.

**Verdict:** ✅ Best fit. Free. Official migration path. Clean API. No vendor lock-in.

---

### B — WooCommerce Memberships
**Cost:** $199/yr (woocommerce.com)  
**Stripe:** Via WooCommerce Stripe plugin (free)  
**Official MP migration:** None  
**WooCommerce:** Already installed on site (currently inactive)

**Notes:** Commerce-first model (buy a product → membership granted) is actually a better fit for the session-pack model long-term. WooCommerce handles the product/SKU complexity well. But the migration from MemberPress is manual, and activating WooCommerce adds significant page weight and complexity.

**Verdict:** ⚠️ Better long-term commerce fit, but $199/yr and no migration tool. Revisit at Phase 15+ if a storefront becomes necessary.

---

### C — Restrict Content Pro (RCP)
**Cost:** $99/yr  
**Stripe:** Included  
**Official MP migration:** None  
**Notes:** Clean and developer-friendly. Good option, but costs $99/yr versus PMPro's free core, with no migration advantage.

**Verdict:** ⚠️ Fine choice, but PMPro is strictly better for Excreet's situation.

---

### D — Simple Membership
**Cost:** Free  
**Stripe:** No native integration  
**Notes:** Too basic for session-pack model. No suitable Stripe path. Not recommended.

**Verdict:** ❌ No.

---

## 3. Recommendation: Paid Memberships Pro

PMPro covers 100% of what MemberPress is currently doing, at $0, with an official migration path. The full replacement cost is the engineering time documented below.

---

## 4. Migration Plan

### Phase M1 — Install PMPro (alongside MemberPress, no disruption)
1. Install `paid-memberships-pro` plugin — keep MemberPress active
2. Configure Stripe gateway in PMPro settings
3. Create the 4 membership levels (match MemberPress product names/IDs as closely as possible)

### Phase M2 — Migrate Members
1. Use the official [MemberPress → PMPro migration plugin](https://www.paidmembershipspro.com/add-ons/import-users-from-csv/)
   - Exports members from MemberPress with level assignments
   - Re-imports into PMPro levels
2. With 100 members and 0 active billing subscriptions, this is a one-shot CSV import — no billing migration needed
3. Verify 5 sample accounts manually after import

### Phase M3 — Recreate Access Rules
Recreate the 6 protected pages in PMPro's access rules admin. PMPro uses the same post-ID-based rule model as MemberPress.

| Rule | MemberPress product to map |
|------|---------------------------|
| Member Intake Form | Excreet WHealth Membership |
| Intake Processing | Excreet WHealth Membership |
| Member Dashboard | Excreet WHealth Membership |
| Single Page: Member Dashboard | Excreet WHealth Membership |
| Single Page: Gut Snapshot | Excreet WHealth Membership |
| Single Page: Welcome Member | Excreet WHealth Membership |

### Phase M4 — Update Mu-Plugin Patches
4 patches need surgical updates. Estimated effort: ~2 hours.

**patch-291.php** (member identity + programmatic rules)
```php
// BEFORE
if ( ! class_exists( 'MeprUser' ) ) return;
$user = new MeprUser( get_current_user_id() );
$active = $user->active_product_subscriptions( 'ids' );

// AFTER
$level = pmpro_getMembershipLevelForUser( get_current_user_id() );
$is_member = ! empty( $level ) && $level->id > 0;
```

Rule creation (patch-291's `excreet_291_create_mepr_rules`): Replace `mepr-rules` post creation with `pmpro_addMembershipLevel()` or use PMPro's admin UI — the programmatic rule creation in patch-291 can be removed entirely and replaced with a one-time admin setup.

**patch-293.php** (premium membership gate)
```php
// BEFORE
$mepr_user = new MeprUser( $user_id );
$active = $mepr_user->active_product_subscriptions( 'ids' );
if ( ! in_array( $premium_id, $active ) ) return;

// AFTER
if ( ! pmpro_hasMembershipLevel( EXCREET_PMPro_PREMIUM_LEVEL_ID, $user_id ) ) return;
```

**patch-294.php** (purchase hook)
```php
// BEFORE
add_action( 'mepr-transaction-completed', 'excreet_294_on_purchase', 10, 1 );

// AFTER
add_action( 'pmpro_after_checkout', 'excreet_294_on_purchase', 10, 1 );
// Note: pmpro_after_checkout passes $user_id and $morder object (not a MeprTransaction)
// Update callback signature accordingly
```

**patch-296.php** (login form CSS)
```css
/* BEFORE */
.mepr-login-form-wrapper, .mepr-form, #mepr_loginform { ... }
.mepr-form label, #mepr_loginform label { ... }
.mepr-form .mepr-submit, #mepr_loginform .mepr-submit { ... }

/* AFTER */
.pmpro_form, #pmpro_form { ... }
.pmpro_form label { ... }
.pmpro_form input[type="submit"], .pmpro_btn-submit { ... }
```

### Phase M5 — Cutover
1. Verify all 6 pages are gated correctly under PMPro (test with a free test account)
2. Verify Stripe checkout flow end-to-end
3. Verify Hermes: Ministry and HCC both receive correct `memberId` (WordPress `get_current_user_id()` — unchanged)
4. Deactivate MemberPress plugin
5. Do not delete MemberPress — keep inactive for 30 days in case of data rollback

---

## 5. Hermes Integration Impact

**No changes to the Hermes API server or database.** The `memberId` passed in all Hermes requests is the WordPress `user_id` integer — this is a native WP concept independent of any membership plugin. PMPro uses the same user IDs.

The only changes are in the 4 mu-plugin PHP files listed in Phase M4 above.

**Ministry chat history** (`ministry_chat_history` PostgreSQL table, keyed by `member_id`) — unaffected. Member IDs remain the same after migration.

**HCC results** (stored by WordPress user ID) — unaffected.

---

## 6. Risk Assessment

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| Members lose access during cutover | Low | High | Run PMPro alongside MemberPress; do cutover at off-peak time |
| CSS regressions on login page | Low | Medium | PMPro classes are well-documented; visual QA before go-live |
| Stripe checkout breaks | Low | High | Test full checkout in PMPro staging before deactivating MemberPress |
| Purchase hook (patch-294) misses events | Low | Medium | PMPro's `pmpro_after_checkout` is well-tested; verify callback signature |
| Member accounts not imported | Low | Low | 0 active billing subscriptions = no mid-cycle disruption risk |

**Overall migration risk: LOW.** Zero active paid subscriptions means there is no live billing to interrupt. Worst case is a brief access issue for existing members, fixable in minutes by re-assigning a PMPro level.

---

## 7. Cost Comparison

| Plugin | Annual Cost | Payment fees |
|--------|------------|--------------|
| MemberPress Basic | $179/yr | 0% (Stripe direct) |
| MemberPress Plus (current) | $299/yr | 0% |
| **PMPro (recommended)** | **$0** | **0%** |
| WooCommerce Memberships | $199/yr | 0% |
| Restrict Content Pro | $99/yr | 0% |

**Annual savings vs current MemberPress Plus: $299/yr.**

---

## 8. Suggested Timeline

| Week | Work |
|------|------|
| Week 1 | Phase M1: Install PMPro, configure Stripe, create levels |
| Week 1 | Phase M2: Member migration (CSV import, 30-min task) |
| Week 1 | Phase M3: Recreate 6 access rules |
| Week 2 | Phase M4: Update 4 mu-plugin patches + deploy |
| Week 2 | Phase M5: Cutover QA + deactivate MemberPress |
