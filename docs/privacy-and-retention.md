# Privacy and retention: implementation notes

These notes describe what the code does. They are not legal advice; the legal review in [launch-blockers.md](launch-blockers.md) is still required.

## What is stored

| Data | Where | Protection |
|---|---|---|
| Message text | `messages.body` | Encrypted (AES-256-CBC, `APP_KEY`) |
| AI summaries and drafts | `ai_analyses.summary`, `draft_reply` | Encrypted |
| Optional display name | `tickets.display_name` | Encrypted; never sent to the AI |
| Ticket code | `tickets.code_hash` | HMAC-SHA256 with `AISA_TICKET_PEPPER`; plaintext never stored or logged |
| Private notes, flag outcomes | `internal_notes.body`, `safety_flags.outcome` | Encrypted |
| Contact form message and reply address | `contact_submissions` | Encrypted |
| Age group, locale, consent version | `consent_records` | Plain (not identifying) |
| Staff 2FA secret and recovery codes | `users` | Encrypted; recovery codes also hashed |
| Sessions | `sessions` | Payload encrypted (`SESSION_ENCRYPT=true`); **no IP or user agent** stored |
| Audit log | `audit_logs` | Identifiers and outcomes only, never message content; staff IP addresses |

**Not collected:** phone, email, location, or visitor IP addresses alongside conversations. Rate limiting uses SHA-256 hashes of IPs in the cache, which expire.

`APP_KEY` protects all encrypted columns. Back it up securely and separately from the database. Losing it makes conversations unreadable; leaking it together with the database exposes them. Rotating it needs `APP_PREVIOUS_KEYS` (Laravel's key rotation).

## Retention

- `AISA_RETENTION_DAYS` (default 180, an **open decision**). `aisa:prune` runs daily and deletes tickets whose `last_activity_at` is older than that, with all linked records.
- Conversations with an **unresolved** safety flag are kept until a person resolves the flag.
- Whether safety records must be kept longer after deletion is a legal question. Until answered, they are deleted with the conversation (`DeletionService`).
- The audit log is not pruned automatically. Decide its retention period during legal review.

## Deletion

- People request deletion from the conversation page with an explicit confirmation. No new messages can be added while it is pending.
- A responder completes it in **Deletion requests**. The ticket and everything linked to it are deleted in one transaction, and only the reference, status and processor are kept.
- Admins can also delete a conversation directly (for example abusive content); this requires a reason and typing the reference.

## Sharing with third parties

- **AI provider:** conversation text only (see [ai-provider.md](ai-provider.md)).
- **Mail provider:** safety alerts contain reference, level and reasons, but no content. Staff password-reset emails.
- No analytics, advertising, fonts or CDNs are loaded on public pages. `Referrer-Policy: no-referrer` stops this site appearing in other sites' logs.

## Shared devices and Quick exit

- "Remember this device" is off by default. When chosen, it stores the code in an encrypted, HTTP-only cookie for 30 days, and "Forget it on this device" removes it.
- Quick exit (header button on every public page):
  1. Clears the page and replaces the browser location with a neutral site (`AISA_QUICK_EXIT_URL`), so Back does not return here.
  2. Ends the server session in the background (`sendBeacon`), and by default also forgets the remembered code (`AISA_QUICK_EXIT_FORGETS_DEVICE`).
  3. Clears the unsent draft kept in `sessionStorage`.
- Pages are sent with `Cache-Control: no-store`, so the browser does not redisplay them from cache.
- **It cannot** erase history entries for pages visited before, server or network logs, or anything already seen. The How it works page says this plainly.

## Dashboard access

Two-factor sign-in is mandatory. Roles are `responder` and `admin`. Every conversation view, search, reply, note, flag action, deletion, settings change and sign-in is audited. Accounts can be deactivated instantly, and sessions end after 30 minutes idle.
