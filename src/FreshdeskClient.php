<?php

namespace Escalated\Plugins\ImportFreshdesk;

use Illuminate\Support\Facades\Http;

class FreshdeskClient
{
    private string $baseUrl;
    private string $authHeader;

    public function __construct(string $domain, string $apiKey)
    {
        $this->baseUrl = "https://{$domain}.freshdesk.com/api/v2";
        // Freshdesk Basic auth: API key as username, "X" as password
        $this->authHeader = base64_encode("{$apiKey}:X");
    }

    public static function fromCredentials(array $credentials): static
    {
        return new static(
            $credentials['domain'],
            $credentials['api_key'],
        );
    }

    /**
     * Test connection by fetching the current agent profile.
     */
    public function testConnection(): bool
    {
        $response = $this->get('agents/me');
        return isset($response['id']);
    }

    /**
     * Make an authenticated GET request with rate limit handling.
     */
    public function get(string $endpoint, array $query = []): array
    {
        $url = str_starts_with($endpoint, 'http') ? $endpoint : "{$this->baseUrl}/{$endpoint}";

        return $this->request($url, $query);
    }

    /**
     * Paginate a list endpoint, returning one page at a time.
     * Freshdesk uses page/per_page query params; returns an empty array on the last page.
     */
    public function getWithPagination(string $endpoint, int $page = 1, int $perPage = 100): array
    {
        return $this->get($endpoint, [
            'page' => $page,
            'per_page' => $perPage,
        ]);
    }

    /**
     * Fetch a single page of tickets filtered by updated_since date range.
     *
     * Freshdesk has a hard limit of 300 pages (30,000 records) per query. To work
     * around this, ticket extraction partitions the full history into date windows
     * using the updated_since filter, paginating within each window independently.
     */
    public function listTicketsInWindow(string $startDate, string $endDate, int $page = 1): array
    {
        return $this->get('tickets', [
            'updated_since' => $startDate,
            'order_by' => 'updated_at',
            'order_type' => 'asc',
            'page' => $page,
            'per_page' => 100,
            // updated_until is not an official filter — callers must check updated_at <= $endDate
            // and stop paginating when records fall outside the window.
            '_end_date_hint' => $endDate, // Not sent to API — stripped in request()
        ]);
    }

    private function request(string $url, array $query = [], int $retries = 3): array
    {
        // Strip internal hint keys before sending to the API
        unset($query['_end_date_hint']);

        for ($attempt = 1; $attempt <= $retries; $attempt++) {
            $response = Http::withHeaders([
                'Authorization' => "Basic {$this->authHeader}",
                'Accept' => 'application/json',
            ])->timeout(30)->get($url, $query);

            if ($response->status() === 429) {
                $retryAfter = (int) $response->header('Retry-After', 60);
                sleep(min($retryAfter, 120));
                continue;
            }

            if ($response->successful()) {
                return $response->json();
            }

            if ($response->status() >= 500 && $attempt < $retries) {
                sleep(2 ** $attempt);
                continue;
            }

            throw new \RuntimeException(
                "Freshdesk API error ({$response->status()}): " . $response->body()
            );
        }

        throw new \RuntimeException('Freshdesk API request failed after retries.');
    }
}
