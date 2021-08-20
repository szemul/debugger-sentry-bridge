<?php
declare(strict_types=1);

namespace Szemul\DebuggerSentryBridge;

use Sentry\State\HubInterface;
use Sentry\Tracing\SpanContext;
use Sentry\Tracing\SpanStatus;
use Sentry\Tracing\Transaction;
use Szemul\Database\Debugging\DatabaseCompleteEvent;
use Szemul\Debugger\DebuggerInterface;
use Szemul\Debugger\Event\DebugEventInterface;

class SentryTracingDebugger implements DebuggerInterface
{
    private ?Transaction $transaction;

    public function __construct(private HubInterface $hub, private MetadataFormatter $metadataFormatter)
    {
        $this->transaction = $this->hub->getTransaction();
    }

    public function handleEvent(DebugEventInterface $event): void
    {
        if (null === $this->transaction) {
            // No transaction, nothing to log to
            return;
        }

        if ($event instanceof DatabaseCompleteEvent) {
            $this->transaction->startChild($this->getDatabaseSpanContext($event));
        }
    }

    protected function getDatabaseSpanContext(DatabaseCompleteEvent $event): SpanContext
    {
        $spanContext = new SpanContext();
        $spanContext->setOp('db.query');
        $startEvent = $event->getStartEvent();
        $spanContext->setStartTimestamp($startEvent->getTimestamp());
        $spanContext->setEndTimestamp($event->getTimestamp());
        $spanContext->setDescription($startEvent->getQuery());
        $spanContext->setData($this->metadataFormatter->getDatabaseMetaData($event));
        $spanContext->setStatus($event->isSuccessful() ? SpanStatus::ok() : SpanStatus::unknownError());

        return $spanContext;
    }
}
