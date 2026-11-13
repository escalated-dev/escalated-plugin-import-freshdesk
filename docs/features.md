# Features

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
