<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;

final class HttpResponse
{
    public function __construct(
        public readonly int $status,
        public readonly array $headers,
        public readonly string $body
    ) {
    }

    public function header(string $name): ?string
    {
        $values = $this->headers[strtolower($name)] ?? [];

        return $values[0] ?? null;
    }

    public function location(): ?string
    {
        return $this->header('location');
    }

    public function csrfToken(): string
    {
        if (preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $this->body, $matches) !== 1) {
            throw new RuntimeException('На странице не найден CSRF-токен.');
        }

        return $matches[1];
    }

    public function catCardCount(): int
    {
        return substr_count($this->body, 'class="cat-card ');
    }

    public function catNames(): array
    {
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($this->body);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new \DOMXPath($document);
        $nodes = $xpath->query(
            '//article[contains(concat(" ", normalize-space(@class), " "), " cat-card ")]//h3'
        );

        if ($nodes === false) {
            return [];
        }

        $names = [];

        foreach ($nodes as $node) {
            $names[] = trim($node->textContent);
        }

        return $names;
    }

    public function selectHtml(string $id): string
    {
        $pattern = '/<select[^>]*id="' . preg_quote($id, '/') . '"[^>]*>(.*?)<\/select>/s';

        if (preg_match($pattern, $this->body, $matches) !== 1) {
            throw new RuntimeException('На странице не найден select#' . $id . '.');
        }

        return $matches[1];
    }
}
