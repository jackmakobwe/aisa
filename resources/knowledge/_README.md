# AI reference notes (knowledge base)

The AI assistant reads the notes in this folder when they match what a person wrote, and uses them to ground its answer. They are never shown to the person word for word.

## Rules for every note

- One topic per file, in the folder for its module: `wellbeing`, `parenting`, `money`, `career`.
- Header between `---` lines: `id` (unique), `title`, `keywords` (comma separated, English **and** Kiswahili, including common spellings and Sheng), `reviewed` (`true` / `false`).
- Plain, practical, accurate, and suitable for Tanzania. Short paragraphs or `-` lists.
- **No phone numbers, prices of specific products, or names of specific companies, apps, lenders or investments in the note text.** Brand names may appear in `keywords` only, so people who mention them are matched. Public regulators and official bodies (Bank of Tanzania, CMSA, TRA, BRELA, HESLB, VETA) may be named. Verified help contacts live in Admin → Crisis contacts and are shown by the site, not written by the AI.
- Health, parenting, emotional and money notes must be checked by a suitably qualified reviewer (health worker, counsellor, financial educator). After checking, set `reviewed: true`.
- When every note says `reviewed: true`, set `AISA_KNOWLEDGE_REVIEWED=true`. Until then `php artisan aisa:launch-check` reports a blocker.
- Files whose names start with `_` (like this one) are ignored.

Check which notes match a message with `php artisan aisa:knowledge "your test message"`.
