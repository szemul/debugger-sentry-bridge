<?php
declare(strict_types=1);

namespace Szemul\DebuggerSentryBridge;

use Sentry\Tracing\SpanContext;
use Sentry\Tracing\SpanStatus;
use Szemul\Database\Debugging\DatabaseCompleteEvent;
use Szemul\Debugger\DebuggerInterface;
use Szemul\Debugger\Event\DebugEventInterface;
use Szemul\Debugger\Event\HttpResponseReceivedEvent;

class SentryTracingDebugger implements DebuggerInterface, SentryOperationConstants
{
    public function __construct(
        private MetadataFormatter $metadataFormatter,
        private SentryTracingState $tracingState,
    ) {
    }

    public function handleEvent(DebugEventInterface $event): void
    {
        $parentSpan = $this->tracingState->getSpan() ?? $this->tracingState->getTransaction();

        if (null === $parentSpan) {
            // No parent span, nothing to log to
            return;
        }

        $spanContext = match (true) {
            $event instanceof DatabaseCompleteEvent     => $this->getDatabaseSpanContext($event),
            $event instanceof HttpResponseReceivedEvent => $this->getHttpSpanContext($event),
            default                                     => null,
        };

        if (null !== $spanContext) {
            $parentSpan->startChild($spanContext);
        }
    }

    protected function getDatabaseSpanContext(DatabaseCompleteEvent $event): SpanContext
    {
        $startEvent  = $event->getStartEvent();
        $spanContext = new SpanContext();

        $spanContext->setOp(self::OPERATION_DB_QUERY);
        $spanContext->setStartTimestamp($startEvent->getTimestamp());
        $spanContext->setEndTimestamp($event->getTimestamp());
        $spanContext->setDescription($startEvent->getQuery());
        $spanContext->setData($this->metadataFormatter->getDatabaseMetaData($event));
        $spanContext->setStatus($event->isSuccessful() ? SpanStatus::ok() : SpanStatus::unknownError());

        return $spanContext;
    }

    protected function getHttpSpanContext(HttpResponseReceivedEvent $event): SpanContext
    {
        $requestEvent = $event->getRequestSentEvent();
        $request      = $requestEvent->getRequest();
        $spanContext  = new SpanContext();

        $spanContext->setOp(self::OPERATION_HTTP_REQUEST);
        $spanContext->setStartTimestamp($requestEvent->getTimestamp());
        $spanContext->setEndTimestamp($event->getTimestamp());
        $spanContext->setDescription($request->getMethod() . ' ' . $request->getUri());
        $spanContext->setData($this->metadataFormatter->getHttpRequestMetadata($event));
        $spanContext->setStatus(SpanStatus::createFromHttpStatusCode($event->getStatusCode()));

        return $spanContext;
    }
}
