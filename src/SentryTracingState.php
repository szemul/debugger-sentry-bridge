<?php
declare(strict_types=1);

namespace Szemul\DebuggerSentryBridge;

use Sentry\Tracing\Span;
use Sentry\Tracing\Transaction;

class SentryTracingState
{
    private ?Transaction $transaction = null;
    private ?Span        $span        = null;

    /**
     * @return array<string,mixed>|null
     */
    public function __debugInfo(): ?array
    {
        return [
            'transaction' => null === $this->transaction ? null : '** Instance of ' . get_class($this->transaction),
            'span'        => null === $this->span ? null : '** Instance of ' . get_class($this->span),
        ];
    }

    public function getTransaction(): ?Transaction
    {
        return $this->transaction;
    }

    public function setTransaction(?Transaction $transaction): static
    {
        $this->transaction = $transaction;

        return $this;
    }

    public function getSpan(): ?Span
    {
        return $this->span;
    }

    public function setSpan(?Span $span): static
    {
        $this->span = $span;

        return $this;
    }
}
