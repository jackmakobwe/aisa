# Security checklist

Status: ✅ implemented and tested · 🟡 implemented, needs production configuration or review · ⬜ not done

| Area | Control | Status | Where / test |
|---|---|---|---|
| Transport | HTTPS everywhere, HSTS on secure requests | 🟡 | `SecurityHeaders`; configure TLS at the web server, `APP_URL=https://...` |
| Headers | CSP (nonce scripts, `frame-ancestors 'none'`, no `unsafe-eval`), X-Frame-Options, nosniff, Referrer-Policy `no-referrer`, Permissions-Policy, COOP | ✅ | `PublicPagesTest::test_security_headers...` |
| Caching | `Cache-Control: no-store` on HTML/JSON | ✅ | same test |
| CSRF | Laravel token on every form and `fetch` | ✅ | `CsrfProtectionTest` |
| XSS | Blade escaping of all user, AI and staff text; no `{!! !!}` except the server-generated 2FA QR SVG and four translated sentences containing a link (`register`, `choose-help`, article photo credit), where every inserted value is passed through `e()` and the sentence itself comes from the lang files | ✅ | `test_user_message_html_is_escaped`, `test_reply_body_is_escaped_for_the_user` |
| SQL injection | Eloquent and query builder with bindings only | ✅ | — |
| Validation | Form Requests; length limits; control characters stripped | ✅ | `test_message_validation` |
| Ticket codes | 140-bit CSPRNG codes, keyed HMAC storage, plaintext never stored | ✅ | `TicketCredentialServiceTest`, `test_plaintext_code_is_never_stored` |
| Enumeration | Same error for malformed, unknown and blocked codes; lockout after 5 failures per IP for 15 min; 30 lookups per hour per IP | ✅ | `test_wrong_malformed_and_blocked_codes...`, `test_repeated_guessing...` |
| Access control | Ticket access only via server session; no ids in public URLs | ✅ | `test_one_session_cannot_reach_another_ticket` |
| Session fixation | Session regenerated on ticket grant, login and 2FA | ✅ | — |
| Sessions | Encrypted payload, HTTP-only, SameSite=Lax; `SESSION_SECURE_COOKIE=true` in production | 🟡 | `.env` |
| Admin auth | bcrypt passwords (min 12, mixed case, number, HaveIBeenPwned check), login throttling, inactive accounts refused | ✅ | `AdminAuthTest` |
| 2FA | TOTP mandatory for every account, per-session challenge, replay prevention, hashed single-use recovery codes | ✅ | `AdminAuthTest` |
| Idle timeout | 30 min (configurable) | ✅ | `test_idle_sessions_are_signed_out` |
| Roles | `responder` vs `admin` gates on every route | ✅ | `test_responders_cannot_use_admin_only_areas` |
| Public accounts (optional) | Separate `members` table and `member` guard: a member session never passes staff auth. Email encrypted with HMAC lookup; bcrypt passwords (min 10, letters and numbers, HaveIBeenPwned check); email confirmation required before an account can hold conversations; sign-up, sign-in and reset never reveal whether an email has an account; 5 failed sign-ins per email and IP lock for 15 min, plus 10 requests/min per IP; session regenerated on sign-in; Quick exit and sign-out end the session; ownership checked on every conversation opened from an account | ✅ | `MemberAccountTest` |
| Google sign-in | Socialite with state check; only Google-confirmed emails; an unconfirmed password sign-up for the same address loses its password when Google links it (prevents account pre-hijacking); routes 404 until configured | ✅ | `MemberAccountTest` |
| Staff registration | None; staff accounts via `aisa:create-admin` or by an admin | ✅ | `test_there_is_no_public_registration` |
| Default credentials | None in production; the dev seeder refuses to run in production and generates a random password | ✅ | `DatabaseSeeder` |
| Password reset | Signed token, expiring, same response for unknown emails, 2FA still required | ✅ | `test_password_reset_does_not_reveal...` |
| Audit | Views, searches, replies, notes, flags, deletions, settings, sign-ins | ✅ | `test_viewing_a_conversation_is_audited` |
| Encryption at rest | Personal text columns encrypted with `APP_KEY` | ✅ | `test_message_text_is_encrypted_at_rest` |
| Database encryption | Disk or volume encryption on the database server | 🟡 | Hosting |
| Secrets | `.env` only; `.env.example` has placeholders; API key server-side only | ✅ | — |
| Logging | No message text in logs; AI errors logged as codes; secrets not flashed to session | ✅ | `bootstrap/app.php`, `AnthropicProvider` |
| Prompt injection | Untrusted input framing, structured outputs, validation, independent rules | ✅ | `test_model_saying_no_risk_cannot_override_the_rules` |
| AI failures | Timeouts, retries, graceful message, retry button, daily cap | ✅ | `test_ai_failure_...`, `test_daily_ai_cap...` |
| Rate limiting | Messages (8/min per conversation, 30/min per IP), starts (10/h), contact (5/h), admin (120/min) | ✅ | `test_message_endpoint_is_rate_limited`, `test_contact_form_is_rate_limited` |
| Spam | Honeypot on contact form | ✅ | `test_contact_form...` |
| Mock in production | Refused at boot | ✅ | `test_mock_provider_is_refused_in_production` |
| Launch gate | Intake closed in production until blockers clear | ✅ | `test_launch_gate_closes_intake_in_production` |
| Dependencies | `composer audit` / `npm audit` clean at install | 🟡 | Re-run on every deploy |
| Backups | Daily encrypted DB backups, tested restore | ⬜ | [deployment.md](deployment.md) |
| Monitoring | Uptime, error and cost alerts | ⬜ | [deployment.md](deployment.md) |
| Penetration test | Independent review before public launch | ⬜ | Recommended |
| File uploads | None in this version | n/a | — |
