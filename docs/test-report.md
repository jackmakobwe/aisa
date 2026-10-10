# Test report

**Run:** 10 October 2026, `php artisan test` on PHP 8.2.12 (Windows, XAMPP), SQLite in memory, fake/mock AI provider.
**Result:** 152 passed, 0 failed, 700 assertions, about 23 s. `vendor/bin/pint --test`: passed.

| Suite | Tests | Covers |
|---|---|---|
| `Unit/Safety/RuleBasedSafetyClassifierTest` | 47 | 35 fictional risk messages (EN, SW, Sheng, mixed, injection wrapper), 10 safe look-alikes, severity, normalisation |
| `Unit/Safety/SafetyTestSetTest` | 3 | Shared test set (`resources/safety/test-set.json`): every direct case caught by rules, no safe case flagged, coverage of EN/SW/mixed × direct/indirect/safe |
| `Unit/TicketCredentialServiceTest` | 4 | Code format and ≥128-bit entropy, uniqueness over 2,000 codes, normalisation, keyed hash |
| `Feature/PublicPagesTest` | 19 | Every public page in EN and SW with Quick exit and Get help now; language switch; verified-only contacts; dev banner; security headers; draft legal notice; contact form (encrypted, honeypot, rate limit) |
| `Feature/ConversationFlowTest` | 28 | No processing before consent; decline discards; validation; full flow; encryption at rest; JSON follow-ups; chronological, identifier-free model history; AI failure and retry; daily cap; sanitising bad model output; Talk to Aisa (agreement, code, draft); AI steps back; capacity pause; AI-only plus optional code; under-18 restrict and block; deletion requests; HTML escaping; rate limit; leave; Quick exit; rating; production launch gate; mock refused in production; mock labelling; flags never auto-resolve |
| `Feature/TicketAccessTest` | 9 | Plaintext code never stored; return in any format; generic errors; lockout; cross-ticket isolation; routes need access; remember-device opt-in and forget; remembered code; human reply visible, notes hidden |
| `Feature/SafetyWorkflowTest` | 7 | Rules flag, notice and alert; model-only flag; model cannot override rules; unknown-type flag; escalation without duplicates; alerts without content; flagged users bypass capacity pause |
| `Feature/Admin/AdminAuthTest` | 14 | Guest redirects; no registration; failed login audited; throttling; inactive accounts; forced 2FA setup; per-session 2FA; TOTP replay; recovery codes; idle timeout; deactivation; role separation; password-reset privacy; strong passwords |
| `Feature/Admin/AdminWorkflowTest` | 18 | All dashboard pages render; admin-only audited export; views audited; drafts never auto-sent; reply needs approval; reply escaped; follow-up requeues; filters and search; notes; flag resolution rules; panel ordering; manual flag; deletion cascade; retention keeps unresolved safety cases; blocking; crisis contact verification; settings and launch check; typed-reference delete |
| `Feature/CsrfProtectionTest` | 1 | POST without a token is rejected |
| `Feature/SafetyEvaluationCommandTest` | 2 | `aisa:safety-eval` passes when the model catches indirect cases, fails on misses and model errors, saves nothing |

## Manual checks performed

Against MariaDB 10.4 with the `aisa-database` session driver, via HTTP:

- Public flow in Kiswahili: start → consent → conversation → crisis message (flag, notice, alert recorded) → Talk to Aisa → code → return in a new browser with lowercase, dash-free code → lockout after 5 wrong codes.
- Admin: login → forced 2FA challenge → all 14 dashboard pages returned 200.
- `sessions` rows contain no IP or user agent.
- `npm run build` succeeds (CSS about 9 KB and JS about 29 KB, gzipped).

## Browser and accessibility check

Run with headless Chromium (Playwright 1.49) and axe-core 4.10 (WCAG 2.0/2.1 A and AA rules) against the running app on MariaDB, at 1440px and 375px:

- **25 pages** (all public pages in EN and SW, consent, conversation, choose help, your code, admin login, overview, queue, conversation, safety, analytics, settings): **0 axe violations**, no horizontal overflow at 375px, and Quick exit plus Get help visible in the header at every width.
- **UI behaviour (all passed):** consent button disabled until agreed; Enter sends via `fetch` and appends messages without a reload; composer clears; the safety notice appears inline after a crisis message; Copy puts the exact code on the clipboard; an unsent draft survives a language switch; Quick exit leaves the site and the conversation is closed afterwards.
- The strict CSP blocked Playwright's own `eval`-based helper, confirming the policy is enforced. No CSP violations came from the app.
- Fixed during this check: admin overview overflowed by 193px on phones; invalid `<dl>` structure in overview tiles; links distinguishable by colour only (links are now underlined site-wide); Kiswahili header buttons wrapping on small phones (short label "Msaada" with the full accessible name).

Not covered: real screen-reader testing (NVDA, TalkBack, VoiceOver), Safari and iOS, low-end Android devices, and slow-network timing.

## Not yet tested

- **The real AI model.** No API key was configured, so `AnthropicProvider` has not made a live call. Its request and response mapping follows the official SDK; run the model safety test set in [safety-testing.md](safety-testing.md) before launch.
- **Manual assistive-technology and device testing** (see above).
- **Real mail delivery** (tests use the array mailer, development uses the log mailer).
- **Load, backup restore and penetration testing.**
