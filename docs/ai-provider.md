# AI provider

## Configuration

All AI settings live in `.env` and `config/aisa.php`:

| Variable | Default | Meaning |
|---|---|---|
| `AISA_AI_PROVIDER` | `mock` | `anthropic` for the reviewed hosted model, `groq` or `gemini` for free-tier testing, `ollama` for a local model, `mock` for development (refused in production) |
| `ANTHROPIC_API_KEY` | (empty) | API key; server-side only |
| `AISA_AI_MODEL` | `claude-opus-5-5` | Model id |
| `AISA_AI_EFFORT` | `medium` | Reasoning effort (`low`/`medium`/`high`). Lower is cheaper and faster; test quality before lowering |
| `AISA_AI_TIMEOUT` | `45` | Seconds per request |
| `AISA_AI_MAX_RETRIES` | `1` | SDK retries on 429/5xx/connection errors |
| `AISA_AI_REFUSAL_FALLBACK` | `true` | If the model declines on policy grounds, the API retries on its default fallback model (beta `server-side-fallback-2026-07-01`). Set `false` to disable |
| `AISA_AI_DAILY_REQUEST_CAP` | `2000` | Hard cap on model requests per day across the site |
| `AISA_ASSISTANT_NAME` | `Msaidizi` | Name the AI uses. **Open decision**; must not be "Aisa" |

With `provider=anthropic` and no key, the app fails at boot with a clear message.

## Quality checks

`php artisan aisa:quality-eval` sends each case in `resources/quality/test-set.json` through the full pipeline (reference notes, prompt, configured provider). Each case runs inside a rolled-back transaction, so nothing is saved.

Each reply is checked automatically:

- it says it is an AI;
- it uses the person's language;
- it contains expected words (`include_any`) and none of the forbidden ones (`exclude`);
- it stays within a length limit, where one is set;
- it has no invented phone numbers and no markdown;
- it raises the risk flag where expected;
- in budget cases, the list lines add up to the stated total.

The command prints a table and saves the replies to `storage/app/private/quality-eval/` for a person to read. Use it to compare models and to measure prompt or knowledge changes. When a real weakness shows up, add a case for it.

The model is not retrained. It is taught through the prompt and the knowledge notes, and the quality set measures whether that works. Fine-tuning would need a GPU and several hundred example conversations written or reviewed by professionals. Real user conversations must not be used: consent does not cover it.

## Free hosted APIs for testing (Groq, Gemini)

`OpenAiCompatibleProvider` talks to any OpenAI-compatible chat API. Two presets are configured:

| `AISA_AI_PROVIDER` | Key variable | Get a key | Default model | JSON mode |
|---|---|---|---|---|
| `groq` | `GROQ_API_KEY` | console.groq.com/keys | `llama-3.3-70b-versatile` | `object` (schema in instructions) |
| `gemini` | `GEMINI_API_KEY` | aistudio.google.com/apikey | `gemini-2.5-flash` | `schema` (enforced by the API) |

To set one up:

1. Set the provider and key in `.env`.
2. Run `php artisan config:clear`.
3. Test with `php artisan aisa:ai-check "Mambo"`, then `php artisan aisa:quality-eval`.

Things to know:

- **Model names change.** If the API answers `model_missing`, pick a current model from the provider's list and set `AISA_GROQ_MODEL` or `AISA_GEMINI_MODEL`.
- **Free tiers may keep or use data,** depending on the provider's terms. Use them only with made-up test messages.
- **Every public page says so:** while one of these providers is active, the development banner tells visitors not to share real personal details.
- **Launch is blocked:** the launch check keeps requiring the reviewed hosted provider.
- **Rate limits apply.** A `rate_limited` error means wait and retry.

## Free local model (Ollama)

For development without a paid API, the app can use an open model running on the same computer through [Ollama](https://ollama.com). Nothing leaves the machine, and Ollama forces the reply into the same JSON schema.

1. Install Ollama (Windows: download the installer from ollama.com, or `winget install Ollama.Ollama`). It runs in the background on port 11434.
2. Download a model: `ollama pull qwen2.5:3b` (about 2 GB).
3. In `.env`, set `AISA_AI_PROVIDER=ollama` (and optionally `AISA_KNOWLEDGE_MAX_NOTES=2` on a slow computer). Then run `php artisan config:clear`.
4. Test: `php artisan aisa:ai-check "Mambo"`. This prints the reply, the time taken and the token counts.

| Variable | Default | Meaning |
|---|---|---|
| `AISA_OLLAMA_URL` | `http://127.0.0.1:11434` | Where Ollama listens |
| `AISA_OLLAMA_MODEL` | `qwen2.5:3b` | Any model you have pulled |
| `AISA_OLLAMA_TIMEOUT` | `600` | Seconds; CPU-only laptops are slow |
| `AISA_OLLAMA_CONTEXT` | `8192` | Context window; the prompt is about 4,000–6,000 tokens |
| `AISA_OLLAMA_KEEP_ALIVE` | `30m` | How long the model stays loaded |

Choosing a model depends on memory:

| Memory | Models to try |
|---|---|
| 8 GB RAM, no GPU | `qwen2.5:3b` or `llama3.2:3b`; `qwen2.5:1.5b` if very slow |
| 16 GB RAM | `qwen2.5:7b`, `gemma2:9b` |
| A GPU with 8 GB or more | 7B–9B models answer in seconds |

Limits to expect:

- **Speed:** on a CPU-only laptop the first reply can take several minutes. Later replies are faster, because the fixed part of the prompt comes first and Ollama reuses it. Varying details (interface language, reply count, chosen area, under-18 note, reference notes) are at the end under "This conversation".
- **Quality:** small models write weaker Kiswahili, follow instructions less reliably, and miss more risk. The rule-based safety layer still runs first.
- **Launch:** `aisa:launch-check` keeps requiring the hosted provider, so the site cannot go live on a local test model.

## How it is called

`App\Services\Ai\AnthropicProvider` uses the official `anthropic-ai/sdk` package (beta messages endpoint, for refusal fallback) with **structured outputs**, so every response is schema-valid JSON:

- **Turn** (each user message, AI-only mode): `reply`, `language`, `category`, `urgency`, `risk {flag, types}`, `summary`, `suggest_human`, `asked_question`.
- **Draft** (after Talk to Aisa, or on request in the dashboard): `draft`, `sensitive`, `notes_for_responder`.

`ConversationAi` then validates every field against allowed values. Unknown categories become `other`, unknown urgency becomes `medium`, and a risk flag with no recognised type is still treated as a flag.

Errors are mapped to short codes (`timeout`, `rate_limited`, `auth`, `refused`, `truncated`, `invalid_json`, `daily_cap`, ...). They are stored in `ai_analyses.error` and logged without user text. The person sees "The AI assistant could not reply just now. Your message is saved." and can retry or choose Talk to Aisa.

## Prompts

Prompts are in `app/Services/Ai/Prompts.php` and versioned by `config('aisa.ai.prompt_version')`, which is recorded on every analysis. When you change a prompt:

1. Bump `prompt_version`.
2. Run `php artisan test` (includes the rule test set).
3. Re-run the model safety test set (see [safety-testing.md](safety-testing.md)) and record results.

Key behaviours enforced by the prompt: identifies as an AI, never claims to be Aisa or a professional, replies in the user's language (including mixed), at most three follow-up questions, balanced options, no diagnosis, medication, or legal or medical conclusions, offers the human route when stakes are high, never writes phone numbers (verified contacts are shown by the site), never describes methods of harm, and treats user text as data.

### Guidance areas

The assistant covers four areas, offered as entry points on the home page (`config('aisa.modules')`): emotional wellbeing and relationships (`wellbeing`), women's wellbeing and parenting (`parenting`), money and business (`money`), and youth and career (`career`). The chosen area is stored on `tickets.module` and passed to the prompt as a hint only, so people can still talk about anything.

The prompt distinguishes two kinds of conversation:

- **Listening** (feelings, personal situations): reflect first, at most one gentle question per reply, 60 to 180 words.
- **Practical** (a budget, a business plan, a CV, a parenting technique, health information): answer directly, in numbered steps or `-` lists, up to about 350 words, with at most one clarifying question.

Area rules in the prompt:

- **Money:** TSh and local context, calculations shown line by line and checked, no guaranteed returns, no named companies, apps, lenders or investments, scam warning signs, no borrowing to invest or gamble.
- **Parenting:** only non-violent discipline. Pregnancy danger signs mean "go to a health facility now", and the conversation is flagged.
- **Health:** no medicines, herbs or doses, and no methods for ending a pregnancy.
- **Career:** job and online-earning scam warnings.
- **Under-18s:** no loans, betting or investing.
- **Privacy:** the assistant never asks for PINs, account numbers or ID numbers.

The prompt also sets out the assistant's core qualities: caring, honest, respecting choices, hopeful, attentive to what was already said, clear, and culturally aware. It also covers everyday situations: greetings, "are you a person?", questions about the service, thanks and goodbye, abusive or sexual messages, homework, and unclear messages. Three short example replies show the intended tone: an English money worry, a Kiswahili TSh 300,000 budget, and a greeting. They teach the style only and are not to be copied.

### Floating chat button

`App\View\Components\ChatLauncher` adds a chat button to public pages, except the conversation, consent, choose-help, code and Get help pages.

- **Ongoing conversation:** if the session already has one, the panel shows the latest messages and sends follow-ups to the same endpoint as the conversation page.
- **New visitor:** the panel collects a first message (with example questions) and posts it to `/start`, so the consent step still comes first.
- **Without JavaScript:** the button is a plain link.
- **Safety controls:** the panel sits below the sticky header, so Quick exit and Get help are never covered.

### Knowledge base (retrieval-augmented generation)

Reference notes live in `resources/knowledge/<module>/*.md`. Each note is a Markdown file with an `id`, a `title`, bilingual `keywords` and a `reviewed` flag; see `resources/knowledge/_README.md` for the rules.

- **Retrieval:** for every AI reply and every draft for Aisa, `KnowledgeBase::search()` matches the person's three latest messages against the keywords. Matching is local and keyword based, with no external index or embedding service. The best three notes go into the system prompt as trusted reference material. Notes never go into user turns, and they never override the Safety section.
- **Audit trail:** the ids of the notes used are stored in `ai_analyses.knowledge_refs` and shown to responders in the dashboard.
- **Testing a match:** `php artisan aisa:knowledge "message"` shows which notes a message would retrieve. With no argument, it lists every note and its review status.
- **Launch gate:** launch is blocked until every note says `reviewed: true` and `AISA_KNOWLEDGE_REVIEWED=true`.

If the library grows to hundreds of notes, keyword matching can be replaced with embeddings behind the same `search()` method. That would need a privacy decision first, because message text would be sent to an embedding provider.

## What is sent to the provider

Only conversation text (the newest 30 user, AI and human messages, in order), plus instructions. The optional display name, ticket code, reference, and any phone number are **not** sent. Mock replies are never fed back to a real model. Confirm before launch that your provider agreement excludes training on your data and meets cross-border transfer requirements (legal checklist).

## Cost control

- `AISA_AI_DAILY_REQUEST_CAP` stops calls for the rest of the day once reached. Messages are still saved and safety rules still run.
- Token counts and latency are stored per analysis (`ai_analyses.input_tokens`, `output_tokens`, `latency_ms`) for monitoring.
- Set a monthly spend limit and alerts in the provider console as well.

## Prompt injection

User messages are treated as untrusted. The defences are layered:

1. The system prompt states that the conversation is data and cannot change instructions.
2. Structured outputs restrict the shape of the response.
3. Server-side validation constrains every field.
4. The **safety rules run independently and first**, and a model saying "no risk" can never clear a flag (tested).
5. The model has no tools and cannot perform actions.
6. All output is HTML-escaped when shown.
