<?php
declare(strict_types=1);

namespace Szemul\DebuggerSentryBridge\Test;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\LegacyMockInterface;
use Mockery\MockInterface;
use Sentry\Tracing\Span;
use Sentry\Tracing\Transaction;
use Sentry\Tracing\TransactionContext;
use Szemul\DebuggerSentryBridge\SentryTracingState;
use PHPUnit\Framework\TestCase;

class SentryTracingStateTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function testSpanHandling(): void
    {
        $state = new SentryTracingState();

        $span = $this->getSpan();

        $state->setSpan($span);

        $this->assertSame($span, $state->getSpan());
    }

    public function testTransactionHandling(): void
    {
        $state = new SentryTracingState();

        $transaction = $this->getTransaction();

        $state->setTransaction($transaction);

        $this->assertSame($transaction, $state->getTransaction());
    }

    private function getSpan(): Span|MockInterface|LegacyMockInterface
    {
        return Mockery::mock(Span::class);
    }

    private function getTransaction(): Transaction
    {
        return new Transaction(new TransactionContext());
    }
}
