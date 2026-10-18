# TODO

Optional items beyond the build plan's required scope, parked to build later.

## Later

- [ ] **AI topic ideas button.** A "Suggest topics" button on the Write screen that asks the AI for fresh ideas in the publication's niche (via `NewsletterWriter::suggestTopics`). Rate-limit it per user and record `ai_usage` as kind `topics`.
- [ ] **Bring your own AI key.** The plan's cost fallback (decision 3). A buyer pastes their own Anthropic or OpenAI key (stored with the encrypted cast). Their generations use that key, bill to them, and skip the weekly cap and fair-use ceiling. Keep the platform key as the default.
- [ ] **Reseller code API.** Give resellers (OTO 12) a token so their own checkout can fetch a redemption code automatically after each sale. Needs `laravel/sanctum` (first-party, optional in the plan). Must respect the reseller's code quota and stop working if their licence is refunded.
