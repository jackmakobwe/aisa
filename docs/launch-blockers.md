# Launch blockers, open decisions and unfinished work

## Launch blockers (enforced by `php artisan aisa:launch-check`)

In production, new conversations stay closed until each of these passes.

| # | Blocker | Who | How to clear |
|---|---|---|---|
| 1 | **Verified Tanzanian crisis and referral contacts.** None are included; the code deliberately invents no numbers | Aisa | Admin → Crisis contacts: add, call and check each one, then verify. At least one emergency contact and four categories. Re-check every 90 days |
| 2 | **Legal review** of privacy policy, terms, PDPA 2022 registration and duties, cross-border transfer, counselling regulation, minors and abuse disclosure, liability | Tanzanian lawyer | Revise `lang/*/legal.php`, then tick the approval |
| 3 | **Under-18 policy** (`restrict` / `block` / `support`) | Aisa + legal | Set `AISA_MINOR_POLICY`, then tick the approval |
| 4 | **Native Kiswahili review** of every string in `lang/sw/*` (all files are marked DRAFT), the AI's Kiswahili replies, and the safety phrase list | Kiswahili reviewer | Edit files, then tick the approval |
| 5 | **Model safety testing** with the real provider | Developer + counselling adviser | `php artisan aisa:safety-eval` must pass; see [safety-testing.md](safety-testing.md) |
| 6 | **Backup responder** named, trained, with their own 2FA account | Aisa | Admin → Staff accounts; enter the name in Settings |
| 7 | **AI assistant name** confirmed (placeholder "Msaidizi") | Aisa | `AISA_ASSISTANT_NAME`, then tick the approval |
| 8 | Real AI provider and key | Developer | [ai-provider.md](ai-provider.md) |
| 9 | Real alert mailbox and mailer | Developer | `AISA_SAFETY_ALERT_EMAILS`, `MAIL_MAILER` |
| 10 | At least one responder with 2FA | Aisa | First sign-in |
| 11 | Production hardening: `APP_DEBUG=false`, HTTPS URL, secure cookies | Developer | [deployment.md](deployment.md) |

Also required before public launch, but not checked automatically: tested backup restore, monitoring and alerts, a monthly budget, an accessibility review, and an independent security review.

## Open decisions (project document, section 17)

| Decision | Current development default | Where |
|---|---|---|
| Brand names ("Evisa Nest" vs "Aisa"), AI name | "Aisa", AI "Msaidizi" | `.env`, `lang/*` |
| Business model | Free (no payment code) | — |
| Response promise | 48 hours | `AISA_RESPONSE_WINDOW_HOURS` |
| Retention period | 180 days | `AISA_RETENTION_DAYS` |
| Under-18 policy | `restrict` | `AISA_MINOR_POLICY` |
| Safety records after deletion | Deleted with the conversation | `DeletionService` |
| Audit log retention | Kept indefinitely | — |
| Quick exit forgets remembered code | Yes | `AISA_QUICK_EXIT_FORGETS_DEVICE` |
| AI provider and model | Anthropic, `claude-opus-5-5`, effort `medium` | `.env` |

## Content still to provide

- Aisa's photograph (`public/images/aisa.jpg`), her short bio and her stated review standard (Admin → Settings & launch). Nothing about her qualifications or history has been invented.
- Final legal text in both languages.

## Not built yet (later phases, by design)

| Feature | Phase | Note |
|---|---|---|
| SMS / WhatsApp reply notifications (W19) | 2 | Would add an encrypted phone column and a notification service; consent wording needed |
| Optional accounts (W20) | 2 | Ticket codes only for now |
| Digest emails, trend charts beyond the analytics page | 2 | Analytics page exists |
| "Send my code to myself" helper (W22) | 2 | — |
| Emergency contact field | 2 | Only with legal advice |
| Voice messages, mobile apps (W23, W24) | 3 | Same backend |
| Mobile-money payments | — | Depends on business model |

## Known limitations

- Dashboard search covers references and the 1,000 most recent AI summaries, because text is encrypted at rest. A searchable index would need a privacy decision.
- The dashboard is in English only; the public site is fully bilingual.
- Rule-based safety detection misses indirect phrasing; see [safety-testing.md](safety-testing.md).
- Rate limits use the cache; with several servers, use a shared cache (Redis or database).
- Safety alerts are sent by email during the request. If mail is slow, that request is slow; a queued channel with SMS is a Phase 2 option.
