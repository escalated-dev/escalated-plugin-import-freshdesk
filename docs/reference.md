# Reference

Imports tickets, contacts, agents, departments (groups), tags, custom fields, and full conversation history from Freshdesk into Escalated. Uses adaptive date-windowing to work around Freshdesk's 300-page (30,000 record) hard limit per query.

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
