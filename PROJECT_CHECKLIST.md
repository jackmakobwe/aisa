# Project checklist

Tracks the project document's requirements (IDs from sections 7 and 8). ✅ done and tested · 🟡 done, needs review or content · ⬜ later phase · ❌ blocked on an external decision

## Public website (section 7)

| ID | Requirement | Status |
|---|---|---|
| W1 | Language choice and toggle on every page | ✅ |
| W2 | Free text in English, Kiswahili or mixed | ✅ |
| W3 | AI first layer with a few gentle follow-ups | ✅ (mock tested; real model untested) |
| W4 | Category and urgency, shown to staff | ✅ |
| W5 | Choose your help (AI-only or Talk to Aisa) | ✅ |
| W6 | Long random code shown once, copy and print | ✅ |
| W7 | Return page, rate limited, lockout | ✅ |
| W8 | Remember this device, off by default, forget option | ✅ |
| W9 | Optional name | ✅ |
| W10 | Quick exit | ✅ |
| W11 | Get help now on every page, verified contacts | 🟡 page done; **contacts not verified** |
| W12 | Not therapy / medical / emergency statement | ✅ |
| W13 | Consent before first message | ✅ |
| W14 | Age statement and under-18 handling | 🟡 configurable; **policy not approved** |
| W15 | Report / contact form | ✅ |
| W16 | Deletion request via code | ✅ |
| W17 | About, How it works, Privacy, Terms in both languages | 🟡 **legal and Kiswahili review pending** |
| W18 | Satisfaction rating | ✅ |
| W20 | Optional accounts (email + password, Google), My conversations | ✅ (Google needs an OAuth client; legal review pending) |
| W19, W21, W22 | SMS/WhatsApp, richer history, send-code helper | ⬜ Phase 2 |
| W23–W24 | Voice, mobile apps | ⬜ Phase 3 |

## Dashboard (section 8)

| Section | Status |
|---|---|
| Secure login + 2FA, timeout, attempt limits, access log | ✅ |
| Overview strip | ✅ |
| Queue with category, urgency, language, summary, waiting time; sortable | ✅ |
| Conversation view with AI draft, edit and approve | ✅ |
| Filters | ✅ |
| Safety panel above everything, with timer | ✅ |
| Tools: search, saved responses, follow up, mark answered, block | ✅ |
| Private notes | ✅ |
| Reply guardrails | ✅ |
| Alerts to Aisa on risk | ✅ (email; real mailbox needed) |
| Data tools: export (JSON, admin), delete, retention | ✅ |
| Analytics | ✅ basic |
| Digest summaries, team roles beyond admin/responder | ⬜ Phase 2 |

## Cross-cutting

- [x] Bilingual interface (Laravel lang files)
- [x] Encryption of personal text at rest
- [x] Audit log
- [x] Launch gate + `aisa:launch-check`
- [x] Retention pruning (scheduled)
- [x] Mock provider clearly labelled; refused in production
- [x] 182 automated tests passing
- [x] Safety evaluation command and shared test set (`aisa:safety-eval`)
- [x] Automated browser and axe accessibility check (25 pages, 0 violations)
- [x] Educational articles (6, EN/SW) with topic filter; latest three on the home page
- [ ] Counsellor review of the articles (`AISA_ARTICLES_REVIEWED`)
- [ ] Native Kiswahili review
- [ ] Legal review
- [ ] Verified crisis contacts
- [ ] Real-model safety testing
- [ ] Manual screen-reader and real-device testing
- [ ] Backups, monitoring, staging environment
