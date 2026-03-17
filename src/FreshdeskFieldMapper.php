<?php

namespace Escalated\Plugins\ImportFreshdesk;

class FreshdeskFieldMapper
{
    public static function statusMap(): array
    {
        return [
            2 => 'open',
            3 => 'waiting_on_customer',
            4 => 'resolved',
            5 => 'closed',
        ];
    }

    public static function priorityMap(): array
    {
        return [
            1 => 'low',
            2 => 'medium',
            3 => 'high',
            4 => 'urgent',
        ];
    }

    public static function mapStatus(?int $freshdeskStatus): string
    {
        return static::statusMap()[$freshdeskStatus ?? 2] ?? 'open';
    }

    public static function mapPriority(?int $freshdeskPriority): string
    {
        return static::priorityMap()[$freshdeskPriority ?? 2] ?? 'medium';
    }

    /**
     * Normalize a Freshdesk ticket into the standard import format.
     *
     * Tags are embedded directly in the ticket object (no separate endpoint).
     * Description is stored on the ticket and maps to the first reply body.
     */
    public static function normalizeTicket(array $fdTicket): array
    {
        return [
            'source_id' => (string) $fdTicket['id'],
            'title' => $fdTicket['subject'] ?? 'No subject',
            'body' => $fdTicket['description'] ?? '',
            'status' => static::mapStatus($fdTicket['status'] ?? null),
            'priority' => static::mapPriority($fdTicket['priority'] ?? null),
            'requester_source_id' => (string) ($fdTicket['requester_id'] ?? ''),
            'assignee_source_id' => (string) ($fdTicket['responder_id'] ?? ''),
            'department_source_id' => (string) ($fdTicket['group_id'] ?? ''),
            'tag_source_ids' => $fdTicket['tags'] ?? [],
            'metadata' => [
                'freshdesk_id' => $fdTicket['id'],
                'freshdesk_url' => isset($fdTicket['id'])
                    ? "https://app.freshdesk.com/helpdesk/tickets/{$fdTicket['id']}"
                    : null,
            ],
            'created_at' => $fdTicket['created_at'] ?? null,
            'updated_at' => $fdTicket['updated_at'] ?? null,
        ];
    }

    /**
     * Normalize a Freshdesk contact into the standard import format.
     */
    public static function normalizeContact(array $fdContact): array
    {
        return [
            'source_id' => (string) $fdContact['id'],
            'name' => $fdContact['name'] ?? '',
            'email' => $fdContact['email'] ?? '',
            'phone' => $fdContact['phone'] ?? $fdContact['mobile'] ?? null,
            'company_source_id' => isset($fdContact['company_id'])
                ? (string) $fdContact['company_id']
                : null,
            'created_at' => $fdContact['created_at'] ?? null,
            'updated_at' => $fdContact['updated_at'] ?? null,
        ];
    }

    /**
     * Normalize a Freshdesk agent into the standard import format.
     */
    public static function normalizeAgent(array $fdAgent): array
    {
        $contact = $fdAgent['contact'] ?? [];

        return [
            'source_id' => (string) $fdAgent['id'],
            'name' => $contact['name'] ?? '',
            'email' => $contact['email'] ?? '',
            'role' => $fdAgent['type'] ?? 'agent',
            'created_at' => $fdAgent['created_at'] ?? null,
            'updated_at' => $fdAgent['updated_at'] ?? null,
        ];
    }

    /**
     * Normalize a Freshdesk group (maps to department) into the standard import format.
     */
    public static function normalizeGroup(array $fdGroup): array
    {
        return [
            'source_id' => (string) $fdGroup['id'],
            'name' => $fdGroup['name'] ?? 'Unknown',
            'created_at' => $fdGroup['created_at'] ?? null,
            'updated_at' => $fdGroup['updated_at'] ?? null,
        ];
    }

    /**
     * Normalize a Freshdesk conversation (reply/note on a ticket).
     *
     * Conversations are fetched via the individual ticket endpoint
     * (GET /api/v2/tickets/:id?include=conversations).
     */
    public static function normalizeConversation(array $fdConversation, string $ticketSourceId): array
    {
        return [
            'source_id' => (string) $fdConversation['id'],
            'ticket_source_id' => $ticketSourceId,
            'body' => $fdConversation['body'] ?? $fdConversation['body_text'] ?? '',
            'is_internal_note' => (bool) ($fdConversation['private'] ?? false),
            'author_source_id' => (string) ($fdConversation['user_id'] ?? ''),
            'created_at' => $fdConversation['created_at'] ?? null,
            'updated_at' => $fdConversation['updated_at'] ?? null,
        ];
    }

    /**
     * Normalize a Freshdesk attachment into the standard import format.
     *
     * Freshdesk returns temporary signed URLs that may expire; the framework
     * will re-fetch the parent record to get a fresh URL if a download fails.
     */
    public static function normalizeAttachment(array $fdAttachment, string $parentType, string $parentSourceId): array
    {
        return [
            'source_id' => (string) $fdAttachment['id'],
            'parent_type' => $parentType,
            'parent_source_id' => $parentSourceId,
            'filename' => $fdAttachment['name'] ?? 'unknown',
            'mime_type' => $fdAttachment['content_type'] ?? 'application/octet-stream',
            'size' => $fdAttachment['size'] ?? 0,
            'download_url' => $fdAttachment['attachment_url'] ?? '',
        ];
    }

    /**
     * Normalize a tag extracted from a ticket's tag list.
     */
    public static function normalizeTag(string $tagName): array
    {
        return [
            'source_id' => $tagName,
            'name' => $tagName,
        ];
    }
}
