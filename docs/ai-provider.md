# AI provider

## Configuration

All AI settings live in `.env` and `config/aisa.php`:

| Variable | Default | Meaning |
|---|---|---|
| `AISA_AI_PROVIDER` | `mock` | `anthropic` for a real model, `mock` for development (refused in production) |
| `ANTHROPIC_API_KEY` | (empty) | API key; server-side only |
| `AISA_AI_MODEL` | `claude-opus-5-5` | Model id |
| `AISA_AI_EFFORT` | `medium` | Reasoning effort (`low`/`medium`/`high`). Lower is cheaper and faster; test quality before lowering |
| `AISA_AI_TIMEOUT` | `45` | Seconds per request |
| `AISA_AI_MAX_RETRIES` | `1` | SDK retries on 429/5xx/connection errors |
| `AISA_AI_REFUSAL_FALLBACK` | `true` | If the model declines on policy grounds, the API retries on its default fallback model (beta `server-side-fallback-2026-07-01`). Set `false` to disable |
| `AISA_AI_DAILY_REQUEST_CAP` | `2000` | Hard cap on model requests per day across the site |
| `AISA_ASSISTANT_NAME` | `Msaidizi` | Name the AI uses. **Open decision**; must not be "Aisa" |

With `provider=anthropic` and no key, the app fails at boot with a clear message.

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
