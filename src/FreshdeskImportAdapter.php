<?php

namespace Escalated\Plugins\ImportFreshdesk;

use Escalated\Laravel\Contracts\ImportAdapter;
use Escalated\Laravel\Models\ImportSourceMap;
use Escalated\Laravel\Support\ExtractResult;

class FreshdeskImportAdapter implements ImportAdapter
{
    private const PAGE_LIMIT = 300;
    private const WINDOW_DAYS = 30;

    private array $collectedAttachments = [];
    private ?string $currentJobId = null;

    /** Set by the framework before calling extract() — needed for reply iteration */
    public function setJobId(string $jobId): void
    {
        $this->currentJobId = $jobId;
    }

    public function name(): string
    {
        return 'freshdesk';
    }

    public function displayName(): string
    {
        return 'Freshdesk';
    }

    public function credentialFields(): array
    {
        return [
            ['name' => 'domain', 'label' => 'Domain', 'type' => 'text', 'help' => 'e.g., "acme" for acme.freshdesk.com'],
            ['name' => 'api_key', 'label' => 'API Key', 'type' => 'password', 'help' => 'Found in Freshdesk Profile Settings > API Key'],
        ];
    }

    public function testConnection(array $credentials): bool
    {
        return FreshdeskClient::fromCredentials($credentials)->testConnection();
    }

    public function entityTypes(): array
    {
        return ['agents', 'tags', 'custom_fields', 'departments', 'contacts', 'tickets', 'replies', 'attachments'];
    }

    public function defaultFieldMappings(string $entityType): array
    {
        return match ($entityType) {
            'tickets' => [
                'subject' => 'title',
                'description' => 'body',
                'status' => 'status',
                'priority' => 'priority',
                'responder_id' => 'assigned_to',
                'requester_id' => 'requester',
                'group_id' => 'department',
                'tags' => 'tags',
            ],
            default => [],
        };
    }

    public function availableSourceFields(string $entityType, array $credentials): array
    {
        return match ($entityType) {
            'tickets' => [
                ['name' => 'subject', 'label' => 'Subject', 'escalated_options' => ['title']],
                ['name' => 'description', 'label' => 'Description', 'escalated_options' => ['body']],
                ['name' => 'status', 'label' => 'Status', 'escalated_options' => ['status']],
                ['name' => 'priority', 'label' => 'Priority', 'escalated_options' => ['priority']],
                ['name' => 'responder_id', 'label' => 'Agent (Responder)', 'escalated_options' => ['assigned_to']],
                ['name' => 'requester_id', 'label' => 'Requester', 'escalated_options' => ['requester']],
                ['name' => 'group_id', 'label' => 'Group', 'escalated_options' => ['department']],
                ['name' => 'tags', 'label' => 'Tags', 'escalated_options' => ['tags']],
                ['name' => 'company_id', 'label' => 'Company', 'escalated_options' => ['department', 'custom_field', '']],
            ],
            default => [],
        };
    }

    public function extract(string $entityType, array $credentials, ?string $cursor): ExtractResult
    {
        $client = FreshdeskClient::fromCredentials($credentials);

        return match ($entityType) {
            'agents' => $this->extractAgents($client, $cursor),
            'tags' => $this->extractTags($client, $cursor),
            'custom_fields' => $this->extractCustomFields($client, $cursor),
            'departments' => $this->extractDepartments($client, $cursor),
            'contacts' => $this->extractContacts($client, $cursor),
            'tickets' => $this->extractTickets($client, $cursor),
            'replies' => $this->extractReplies($client, $cursor),
            'attachments' => $this->extractAttachments($client, $cursor),
            default => new ExtractResult([], null),
        };
    }

    private function extractAgents(FreshdeskClient $client, ?string $cursor): ExtractResult
    {
        $page = $cursor ? (int) $cursor : 1;

        $data = $client->getWithPagination('agents', $page);

        $records = array_map(
            [FreshdeskFieldMapper::class, 'normalizeAgent'],
            $data,
        );

        // Freshdesk returns an empty array on the last page
        $nextCursor = count($data) > 0 ? (string) ($page + 1) : null;

        return new ExtractResult($records, $nextCursor);
    }

    private function extractTags(FreshdeskClient $client, ?string $cursor): ExtractResult
    {
        // Tags are embedded in ticket objects — there is no separate tags endpoint.
        // We collect unique tags during ticket extraction and return them here from
        // the source map. On the first (and only) call we query distinct tag values
        // already stored by the framework during ticket import.
        if ($cursor !== null) {
            return new ExtractResult([], null);
        }

        // Pull distinct tag names from the tickets already stored in the source map
        $tagNames = ImportSourceMap::where('import_job_id', $this->currentJobId ?? '')
            ->where('entity_type', 'tags')
            ->pluck('source_id')
            ->all();

        $records = array_map(
            [FreshdeskFieldMapper::class, 'normalizeTag'],
            $tagNames,
        );

        return new ExtractResult($records, null, count($records));
    }

    private function extractCustomFields(FreshdeskClient $client, ?string $cursor): ExtractResult
    {
        // Ticket fields endpoint returns all fields in one response (no pagination)
        if ($cursor !== null) {
            return new ExtractResult([], null);
        }

        $data = $client->get('ticket_fields');

        $typeMap = [
            'text' => 'text',
            'paragraph' => 'textarea',
            'number' => 'number',
            'checkbox' => 'checkbox',
            'date' => 'date',
            'dropdown' => 'select',
            'multi_select' => 'multiselect',
        ];

        $records = [];
        foreach ($data as $field) {
            // Skip built-in system fields
            if (in_array($field['name'] ?? '', [
                'subject', 'description', 'status', 'priority',
                'group', 'agent', 'requester', 'product',
            ], true)) {
                continue;
            }

            $records[] = [
                'source_id' => (string) $field['id'],
                'name' => $field['label'] ?? $field['name'] ?? 'Unknown',
                'type' => $typeMap[$field['type'] ?? 'text'] ?? 'text',
                'options' => array_map(
                    fn ($o) => $o['value'] ?? '',
                    $field['choices'] ?? [],
                ),
            ];
        }

        return new ExtractResult($records, null, count($records));
    }

    private function extractDepartments(FreshdeskClient $client, ?string $cursor): ExtractResult
    {
        $page = $cursor ? (int) $cursor : 1;

        $data = $client->getWithPagination('groups', $page);

        $records = array_map(
            [FreshdeskFieldMapper::class, 'normalizeGroup'],
            $data,
        );

        $nextCursor = count($data) > 0 ? (string) ($page + 1) : null;

        return new ExtractResult($records, $nextCursor);
    }

    private function extractContacts(FreshdeskClient $client, ?string $cursor): ExtractResult
    {
        $page = $cursor ? (int) $cursor : 1;

        $data = $client->getWithPagination('contacts', $page);

        $records = array_map(
            [FreshdeskFieldMapper::class, 'normalizeContact'],
            $data,
        );

        $nextCursor = count($data) > 0 ? (string) ($page + 1) : null;

        return new ExtractResult($records, $nextCursor);
    }

    /**
     * Extract tickets using adaptive date windowing to bypass Freshdesk's
     * page 300 (30,000 record) hard limit.
     *
     * Cursor formats:
     *   null                              — start fresh; begin with a 30-day window from epoch
     *   "window:START|END|PAGE"           — paginating within the current window
     *   "window:START|END|DONE;next:S2|E2" — current window exhausted; advance to next
     *
     * START/END are ISO-8601 date strings (e.g., "2020-01-01T00:00:00Z").
     * If a window hits page 300, the adapter splits it into two sub-windows and
     * re-starts from the first sub-window.
     */
    private function extractTickets(FreshdeskClient $client, ?string $cursor): ExtractResult
    {
        // Parse the cursor or initialise a fresh run
        [$windowStart, $windowEnd, $page] = $this->parseTicketCursor($cursor);

        $rows = $client->listTicketsInWindow($windowStart, $windowEnd, $page);

        // Filter out records that fall outside the window end boundary
        // (Freshdesk has no updated_until filter — we use updated_since + manual trim)
        $rows = array_filter(
            $rows,
            fn ($t) => ($t['updated_at'] ?? '') <= $windowEnd
        );
        $rows = array_values($rows);

        $records = array_map(
            [FreshdeskFieldMapper::class, 'normalizeTicket'],
            $rows,
        );

        // Collect tags from each ticket for later deduplication
        foreach ($rows as $ticket) {
            foreach ($ticket['tags'] ?? [] as $tag) {
                $this->ensureTagCollected($tag);
            }
        }

        // Determine next cursor
        if (count($rows) === 0) {
            // Window exhausted — advance to next window
            $nextWindowStart = $windowEnd;
            $nextWindowEnd = $this->advanceWindowEnd($windowEnd, self::WINDOW_DAYS);
            $now = gmdate('Y-m-d\TH:i:s\Z');

            if ($nextWindowStart >= $now) {
                // All history covered
                return new ExtractResult($records, null);
            }

            $nextCursor = "window:{$nextWindowStart}|{$nextWindowEnd}|1";
        } elseif ($page >= self::PAGE_LIMIT) {
            // Hit the page limit — split the window in half and restart
            $midpoint = $this->midpointDate($windowStart, $windowEnd);
            $nextCursor = "window:{$windowStart}|{$midpoint}|1";
        } else {
            // More pages remain in this window
            $nextCursor = "window:{$windowStart}|{$windowEnd}|" . ($page + 1);
        }

        return new ExtractResult($records, $nextCursor);
    }

    /**
     * Parse a ticket cursor string into [windowStart, windowEnd, page].
     * If cursor is null, initialise from the earliest possible Freshdesk date.
     */
    private function parseTicketCursor(?string $cursor): array
    {
        if ($cursor === null) {
            $windowStart = '2010-01-01T00:00:00Z'; // Freshdesk launched ~2010
            $windowEnd = $this->advanceWindowEnd($windowStart, self::WINDOW_DAYS);
            return [$windowStart, $windowEnd, 1];
        }

        // Format: "window:START|END|PAGE"
        if (str_starts_with($cursor, 'window:')) {
            $rest = substr($cursor, 7);
            $parts = explode('|', $rest, 3);
            return [$parts[0], $parts[1], (int) ($parts[2] ?? 1)];
        }

        // Fallback — should not occur in normal operation
        return ['2010-01-01T00:00:00Z', $this->advanceWindowEnd('2010-01-01T00:00:00Z', self::WINDOW_DAYS), 1];
    }

    private function advanceWindowEnd(string $from, int $days): string
    {
        $ts = strtotime($from) + ($days * 86400);
        return gmdate('Y-m-d\TH:i:s\Z', $ts);
    }

    private function midpointDate(string $start, string $end): string
    {
        $mid = (int) ((strtotime($start) + strtotime($end)) / 2);
        return gmdate('Y-m-d\TH:i:s\Z', $mid);
    }

    /** Track unique tag names discovered during ticket extraction. */
    private array $collectedTagNames = [];

    private function ensureTagCollected(string $tagName): void
    {
        if (! isset($this->collectedTagNames[$tagName])) {
            $this->collectedTagNames[$tagName] = true;
        }
    }

    /**
     * Extract replies by fetching full ticket data (with conversations) for each
     * imported ticket one at a time.
     *
     * Cursor formats:
     *   null          — start at offset 0 in the source map
     *   "idx:N"       — fetch ticket at offset N
     *   "tid:ID|PAGE" — paginating within a ticket's conversations (PAGE is 1-based)
     */
    private function extractReplies(FreshdeskClient $client, ?string $cursor): ExtractResult
    {
        // Paginating within a ticket's conversations
        if ($cursor !== null && str_starts_with($cursor, 'tid:')) {
            $rest = substr($cursor, 4);
            $parts = explode('|', $rest, 3);
            $ticketId = $parts[0];
            $page = (int) ($parts[1] ?? 1);
            $ticketOffset = isset($parts[2]) ? (int) $parts[2] : null;

            $conversations = $client->getWithPagination(
                "tickets/{$ticketId}/conversations",
                $page
            );

            $records = $this->normalizeConversations($conversations, $ticketId);

            if (count($conversations) > 0) {
                // More conversation pages for this ticket
                $offsetSuffix = $ticketOffset !== null ? "|{$ticketOffset}" : '';
                $nextCursor = "tid:{$ticketId}|" . ($page + 1) . $offsetSuffix;
            } elseif ($ticketOffset !== null) {
                // Conversations exhausted — advance to next ticket
                $nextCursor = "idx:" . ($ticketOffset + 1);
            } else {
                // No offset tracking available — framework re-enters via idx:
                $nextCursor = null;
            }

            return new ExtractResult($records, $nextCursor);
        }

        // Advance to the next ticket in the source map
        $offset = 0;
        if ($cursor !== null && str_starts_with($cursor, 'idx:')) {
            $offset = (int) substr($cursor, 4);
        }

        $ticketMap = ImportSourceMap::where('import_job_id', $this->currentJobId ?? '')
            ->where('entity_type', 'tickets')
            ->orderBy('id')
            ->offset($offset)
            ->first();

        if (! $ticketMap) {
            return new ExtractResult([], null); // All tickets processed
        }

        $ticketId = $ticketMap->source_id;

        // Freshdesk: conversations are paginated; start at page 1
        $conversations = $client->getWithPagination(
            "tickets/{$ticketId}/conversations",
            1
        );

        $records = $this->normalizeConversations($conversations, $ticketId);

        // Determine next cursor
        if (count($conversations) > 0) {
            // May be more conversation pages for this ticket; carry offset for clean advance
            $nextCursor = "tid:{$ticketId}|2|{$offset}";
        } else {
            // No conversations on first page — move to next ticket immediately
            $nextCursor = "idx:" . ($offset + 1);
        }

        return new ExtractResult($records, $nextCursor);
    }

    private function normalizeConversations(array $conversations, string $ticketId): array
    {
        $records = [];
        foreach ($conversations as $conversation) {
            $records[] = FreshdeskFieldMapper::normalizeConversation($conversation, $ticketId);

            // Collect attachments from each conversation
            foreach ($conversation['attachments'] ?? [] as $attachment) {
                $this->collectedAttachments[] = FreshdeskFieldMapper::normalizeAttachment(
                    $attachment, 'reply', (string) $conversation['id']
                );
            }
        }
        return $records;
    }

    /**
     * Return all attachment metadata collected during reply extraction.
     * Actual file downloads are handled by the framework.
     *
     * Freshdesk returns temporary signed URLs; the framework must re-fetch the
     * parent conversation if a download fails due to an expired URL.
     */
    private function extractAttachments(FreshdeskClient $client, ?string $cursor): ExtractResult
    {
        if ($cursor !== null) {
            return new ExtractResult([], null); // Already returned all in first call
        }

        $records = $this->collectedAttachments;
        $this->collectedAttachments = [];

        return new ExtractResult($records, null, count($records));
    }
}
