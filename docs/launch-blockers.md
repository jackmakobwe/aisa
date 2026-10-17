# Launch blockers, open decisions and unfinished work

## Launch blockers (enforced by `php artisan aisa:launch-check`)

In production, new conversations stay closed until each of these passes.

| # | Blocker | Who | How to clear |
|---|---|---|---|
| 1 | **Verified Tanzanian crisis and referral contacts.** 53 entries researched online on 2026-10-17 can be imported with `php artisan aisa:import-contacts --update`: national short codes 112, 115, 114, 116, 199 (TCRA numbering plan), regional referral hospitals in 27 of 31 regions (45 with phone numbers from their official websites), legal aid offices (TAWLA, LHRC, WLAC), Gender Desks, One Stop Centres and social welfare. They arrive unverified, each with a note of its source and what to check. Mount Meru, Maweni, Amana, Tanga and Mirembe still need phone numbers | Aisa | Admin → Crisis contacts: add, call and check each one, then verify. At least one emergency contact and four categories. Re-check every 90 days |
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
| 12 | **AI reference notes reviewed** (60 drafts in `resources/knowledge`: the service itself, wellbeing, parenting and health, money and business, career) | Counsellor, health worker, financial educator | Correct each note, set `reviewed: true`, then `AISA_KNOWLEDGE_REVIEWED=true`. Check with `php artisan aisa:knowledge` |

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
| Audience and country scope (Tanzania first, or international from the start) | Tanzania first: Kiswahili, TSh examples, Tanzanian regulators and referrals | `Prompts`, `resources/knowledge`, crisis contacts |
| Mobile app, WhatsApp and push notifications (Firebase) | Website first; later phases | — |

## Content still to provide

- Aisa's photograph (`public/images/aisa.jpg`), her short bio and her stated review standard (Admin → Settings & launch). Nothing about her qualifications or history has been invented.
- Final legal text in both languages.
- Approval of the six article photos (`config/articles.php`, files in `public/images/articles`). They are real photographs from Tanzania (five) and Kigali, Rwanda (the counselling photo), from Wikimedia Commons, under CC0, CC BY and CC BY-SA, each credited with a licence link and marked as cropped. The periods photo is cropped to a booklet in a pupil's hands, so no child's face is shown. The licences do not include model releases. Check that showing these people beside the topics is acceptable, or replace them with photos you own or have permission for. Each article says the people shown are not connected to the topic.
- Legal review of optional accounts (privacy notice section 6), including whether inactive accounts should expire.
- For "Continue with Google": a Google Cloud OAuth client on the production domain (`GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`) and a neutral OAuth consent-screen name. Google only accepts `localhost` or a real domain as a redirect, not a LAN IP address.
- Review of the Mama Care page (`lang/*/mama.php`) by a qualified health worker (midwife or nurse), and of its Kiswahili by a native speaker. It covers pregnancy at a young age, danger signs in pregnancy and after birth, obstetric fistula, newborn care, feelings, birth spacing, return to school and safety. The page shows a draft notice until `AISA_MAMA_CARE_REVIEWED=true`. Check especially the postnatal visit schedule, the danger-sign lists, and the statements on free fistula repair and on young mothers returning to school.
- Approval of the six Mama Care photos (`config/mama.php`, files in `public/images/mama`). All are real photographs taken in Tanzania, from Wikimedia Commons: three public domain (USAID, President's Malaria Initiative) and three CC BY-SA (credited, linked, marked as cropped). Two concerns to check. First, whether the public-domain status of the contractor photo (VectorWorks/PMI) is acceptable. Second, whether showing identifiable people beside topics such as young pregnancy and fistula is acceptable, even with the page's note that they are not connected to the topics. Photos taken with consent for Aisa would be safest.
- Review of the six educational articles (`lang/*/articles.php`) by a qualified counsellor or health worker, and of the Kiswahili by a native speaker. They are general information written for development; each article shows a draft notice until `AISA_ARTICLES_REVIEWED=true`. Order, topics and which three appear on the home page are set in `config/articles.php`.

## Not built yet (later phases, by design)

| Feature | Phase | Note |
|---|---|---|
| SMS / WhatsApp reply notifications (W19) | 2 | Would add an encrypted phone column and a notification service; consent wording needed |
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
