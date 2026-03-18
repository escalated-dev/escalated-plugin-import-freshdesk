# Freshdesk Import

Import tickets, contacts, agents, departments (groups), tags, custom fields, and full conversation history from Freshdesk into Escalated. The adapter uses adaptive date-windowing to work around Freshdesk's 300-page (30,000 record) hard limit per query, splitting date ranges automatically when a window fills up.

## Installation

```bash
# Install via Composer
composer require escalated/escalated-plugin-import-freshdesk
```

## Configuration

Credentials are entered through the Escalated import wizard UI. The following fields are required:

| Field | Description |
|---|---|
| `domain` | Your Freshdesk subdomain (e.g. `acme` for `acme.freshdesk.com`) |
| `api_key` | API key — found in **Freshdesk Profile Settings > API Key** |

## Features

- Imports agents, contacts, departments (groups), tags, and custom ticket fields
- Imports tickets with status and priority mapping
- Imports all conversations (replies and private notes) with attachment metadata
- Adaptive date-windowing for ticket extraction bypasses Freshdesk's 30,000-record page limit
- Windows split in half automatically when the 300-page limit is approached
- Cursor-based pagination allows resumable imports — safe to restart after failures
- Automatic rate-limit handling: respects `Retry-After` headers and retries on 429/5xx
- Maps Freshdesk statuses (2=Open, 3=Waiting, 4=Resolved, 5=Closed) to Escalated equivalents
- Maps Freshdesk priorities (1=Low, 2=Medium, 3=High, 4=Urgent) to Escalated equivalents
- Tags are collected from ticket records (no separate Freshdesk tags endpoint needed)
- Attachment metadata collected during reply extraction; temporary signed URLs handled by framework

## Hooks

### Filters

- `import.adapters` — Registers the `FreshdeskImportAdapter` with the Escalated import system

## Entity Types Imported

`agents` → `tags` → `custom_fields` → `departments` → `contacts` → `tickets` → `replies` → `attachments`

## Requirements

- Escalated >= 0.6.0
- Freshdesk account with API access
