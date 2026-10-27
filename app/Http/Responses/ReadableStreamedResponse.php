<?php

namespace App\Http\Responses;

use Closure;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReadableStreamedResponse extends StreamedResponse
{
    private ?string $rendered = null;

    public function __construct(private readonly Closure $renderer, string $filename)
    {
        parent::__construct(function (): void {
            echo $this->renderedBody();
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function getContent(): string|false
    {
        return $this->renderedBody();
    }

    private function renderedBody(): string
    {
        if ($this->rendered === null) {
            $this->rendered = ($this->renderer)();
        }

        return $this->rendered;
    }
}
