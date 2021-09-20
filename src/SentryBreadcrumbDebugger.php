<?php
declare(strict_types=1);

namespace Szemul\DebuggerSentryBridge;

use Sentry\Breadcrumb;
use Sentry\State\HubInterface;
use Szemul\Database\Debugging\DatabaseCompleteEvent;
use Szemul\Debugger\DebuggerInterface;
use Szemul\Debugger\Event\DebugEventInterface;
use Szemul\Debugger\Event\HttpResponseReceivedEvent;

class SentryBreadcrumbDebugger implements DebuggerInterface, SentryOperationConstants
{
    public function __construct(private HubInterface $hub, private MetadataFormatter $metadataFormatter)
    {
    }

    /**
     * @return array<string,mixed>|null
     */
    public function __debugInfo(): ?array
    {
        return [
            'hub'               => '*** Instance of ' . get_class($this->hub),
            'metadataFormatter' => $this->metadataFormatter,
        ];
    }

    public function handleEvent(DebugEventInterface $event): void
    {
        $breadcrumb = $this->convertEventToBreadcrumb($event);

        if (null === $breadcrumb) {
            return;
        }

        $this->hub->addBreadcrumb($breadcrumb);
    }

    protected function convertEventToBreadcrumb(DebugEventInterface $event): ?Breadcrumb
    {
        return match (true) {
            $event instanceof DatabaseCompleteEvent     => $this->convertDatabaseCompleteEvent($event),
            $event instanceof HttpResponseReceivedEvent => $this->convertHttpResponseReceivedEvent($event),
            default                                     => null,
        };
    }

    protected function convertDatabaseCompleteEvent(DatabaseCompleteEvent $event): Breadcrumb
    {
        return new Breadcrumb(
            $event->isSuccessful() ? Breadcrumb::LEVEL_INFO : Breadcrumb::LEVEL_ERROR,
            Breadcrumb::TYPE_DEFAULT,
            self::OPERATION_DB_QUERY,
            $event->getStartEvent()->getQuery(),
            $this->metadataFormatter->getDatabaseMetaData($event),
            $event->getStartEvent()->getTimestamp(),
        );
    }

    protected function convertHttpResponseReceivedEvent(HttpResponseReceivedEvent $event): Breadcrumb
    {
        $requestSentEvent = $event->getRequestSentEvent();
        $request          = $requestSentEvent->getRequest();

        return new Breadcrumb(
            $event->isSuccessful() ? Breadcrumb::LEVEL_INFO : Breadcrumb::LEVEL_ERROR,
            Breadcrumb::TYPE_HTTP,
            self::OPERATION_HTTP_REQUEST,
            $request->getMethod() . ' ' . $request->getUri(),
            $this->metadataFormatter->getHttpRequestMetadata($event),
            $event->getRequestSentEvent()->getTimestamp(),
        );
    }
}
