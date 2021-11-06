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

    public function testDebugInfoWithNoData(): void
    {
        $sut      = new SentryTracingState();
        $expected = [
            'transaction' => null,
            'span'        => null,
        ];

        $this->assertEquals($expected, $sut->__debugInfo());
    }

    public function testDebugInfoWithData(): void
    {
        $span        = $this->getSpan();
        $transaction = $this->getTransaction();

        $sut      = new SentryTracingState();
        $sut->setSpan($span);
        $sut->setTransaction($transaction);

        $expected = [
            'transaction' => '** Instance of ' . get_class($transaction),
            'span'        => '** Instance of ' . get_class($span),
        ];

        $this->assertEquals($expected, $sut->__debugInfo());
    }

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
