# Escalated Plugin: Import Freshdesk

**Website:** [escalated.dev](https://escalated.dev)

Imports tickets, contacts, agents, departments (groups), tags, custom fields, and full conversation history from Freshdesk into Escalated. Uses adaptive date-windowing to work around Freshdesk's 300-page (30,000 record) hard limit per query.

## Features

- Imports agents, contacts, departments (groups), tags, and custom ticket fields
- Imports tickets with status and priority mapping
- Imports all conversations (replies and private notes) with attachment metadata
- Adaptive date-windowing for ticket extraction bypasses Freshdesk's 30,000-record page limit
- Windows split in half automatically when the 300-page limit is approached
- Cursor-based pagination allows resumable imports
- Automatic rate-limit handling with `Retry-After` header support and retry on 429/5xx
- Maps Freshdesk statuses (Open, Waiting, Resolved, Closed) to Escalated equivalents
- Maps Freshdesk priorities (Low, Medium, High, Urgent) to Escalated equivalents
- Tags collected from ticket records (no separate Freshdesk tags endpoint needed)

## Configuration

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `domain` | text | Yes | Your Freshdesk subdomain, e.g. `acme` for `acme.freshdesk.com`. |
| `api_key` | password | Yes | API key from Freshdesk Profile Settings. |

## Hooks

### Filters
- `import.adapters` — Registers the Freshdesk import adapter with the Escalated import system.

## Entity Import Order

`agents` > `tags` > `custom_fields` > `departments` > `contacts` > `tickets` > `replies` > `attachments`

## Installation

```bash
npm install @escalated-dev/plugin-import-freshdesk
```

## License

MIT
