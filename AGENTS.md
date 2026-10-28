# AGENTS.md — PayGuard

Rules for the coding agent working on this repository. Read `docs/PRD.md`, `docs/plan-de-trabajo.md`, `docs/arquitectura.md` and `docs/base-de-datos.md` before starting any task.

## Project
PayGuard is a WhatsApp assistant that helps small businesses in Latin America detect fake digital-wallet payments (Yape and Plin). A cashier forwards a payment screenshot or a voice note to PayGuard's WhatsApp number; PayGuard reads it with Meta's models and compares it with the real payment notification captured by an Android companion app.

Built from scratch for the Meta Global AI Developer Hackathon (Oct 26 - Nov 4, 2026).

## Stack
- Laravel 13, **PHP 8.3** (local: Laragon on Windows; server: Ubuntu 24.04, PHP 8.3-FPM, Nginx).
- MySQL 8. Queue driver: `database`, worker managed by Supervisor.
- Admin panel: React + Tailwind (Vite) inside the same Laravel app. Auth with Sanctum on the same domain.
- Mobile app: Flutter (Android only) in `mobile/`.
- WhatsApp Cloud API (official). Meta Model API at `https://api.meta.ai/v1` (OpenAI-compatible): Muse Spark for image understanding and structured output, Muse Voice Transcribe for audio.

## Code style
- Clean, simple and readable code. Small classes with one responsibility; thin controllers; business logic in `app/Services`.
- **No emojis** in code, comments, logs, commit messages or docs written by the agent. The only exception: WhatsApp replies to cashiers may start with one status symbol (✅, ⏳, ❌ or ⚠).
- Code identifiers, database names and comments in **English**. Everything the user sees (panel, app, WhatsApp replies) in **Spanish**, short and clear.
- Comments only where the reason is not obvious. No commented-out code.
- Validation with Form Requests. Responses from the panel API in a consistent JSON shape.
- Nothing hardcoded: tunable values live in `config/payguard.php` and read from `.env` when they are secrets or environment-specific.

## Rules
- Compatible with PHP 8.3. Do not run `composer update` for unrelated packages.
- **Never edit an existing migration.** Always create a new one, so `php artisan migrate` is enough.
- Never commit secrets. `.env`, keys, tokens and uploaded media stay out of git.
- Every business query must be scoped by `business_id`. A business must never see another business's data.
- Do the heavy work (downloads, Meta API calls, replies) in queued jobs. The WhatsApp webhook must answer 200 fast.
- Verify the WhatsApp signature (`X-Hub-Signature-256`) on every webhook call and ignore duplicated `wa_message_id`s.
- The final verification result is decided by matching against real received payments, never only by the model's opinion of the image.
- Do not use Yape or Plin logos or official assets anywhere.
- On Windows, never create two files whose names differ only in letter case.
- Add or update tests for the logic you change (parsers, matcher, webhook). Run the test suite before finishing.

## Git workflow
- Work on the branch named in the task. Never commit directly to `main`.
- Commit with clear messages in English (`feat:`, `fix:`, `refactor:`, `docs:`, `test:`). Small, focused commits.
- Push only the working branch. **Never merge into `main` and never push to `main`**: the owner tests locally and merges himself.
- When a task is finished, report: what changed, files touched, migrations added, how to test it, and anything left pending.
