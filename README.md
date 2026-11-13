<p align="center">
  <a href="https://escalated.dev"><img src="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/logo.png" width="56" height="56" alt="Escalated"></a>
</p>

<h1 align="center">Freshdesk import for Escalated</h1>

<p align="center">Move your Freshdesk helpdesk into Escalated, history included.</p>

<p align="center">
  <a href="https://escalated.dev"><b>Website</b></a> ·
  <a href="https://escalated.dev/docs"><b>Docs</b></a> ·
  <a href="https://escalated.dev/cloud"><b>Cloud</b></a> ·
  <a href="https://github.com/escalated-dev/escalated-plugin-import-freshdesk/releases"><b>Releases</b></a>
</p>

This plugin imports tickets, contacts, agents, groups, tags, custom fields and full conversation history from Freshdesk into Escalated for Laravel. It's for teams switching from Freshdesk, including large accounts that hit Freshdesk's 30,000-record query limit.

## Features

<table>
<tr>
<td width="33%" valign="top">

**What it imports**
- Agents, contacts, groups, tags, custom fields
- Tickets with status and priority mapping
- Replies and private notes with attachment metadata
- Tags collected from ticket records

</td>
<td width="33%" valign="top">

**Large accounts**
- Date windows get past the 30,000-record limit
- Windows split in half near 300 pages
- Resumable imports with cursor pagination

</td>
<td width="33%" valign="top">

**Mapping and limits**
- Retries on 429 and 5xx, honours Retry-After
- Statuses mapped: Open, Waiting, Resolved, Closed
- Priorities mapped: Low, Medium, High, Urgent

</td>
</tr>
</table>

<p align="center"><sub><a href="docs/features.md">Every feature in detail</a></sub></p>

<p align="center">
  <a href="https://www.php.net"><img src="https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP"></a>
  <a href="https://laravel.com"><img src="https://img.shields.io/badge/Laravel-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel"></a>
  <a href="https://getcomposer.org"><img src="https://img.shields.io/badge/Composer-885630?style=for-the-badge&logo=composer&logoColor=white" alt="Composer"></a>
</p>

## Learn more

- [Every feature](docs/features.md)
- [Configuration, hooks and import order](docs/reference.md)
- [Plugin SDK](https://github.com/escalated-dev/escalated-plugin-sdk): how Escalated plugins are built
- [Escalated docs](https://escalated.dev/docs)
- [Escalated](https://github.com/escalated-dev/escalated), the main repo

## License

MIT. Copyright (c) 2026 Escalated.dev. See [LICENSE](LICENSE).
