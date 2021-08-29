<?php
declare(strict_types=1);

namespace Szemul\DebuggerSentryBridge;

use Sentry\Tracing\Span;
use Sentry\Tracing\Transaction;

class SentryTracingState
{
    private ?Transaction $transaction = null;
    private ?Span        $span        = null;

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
