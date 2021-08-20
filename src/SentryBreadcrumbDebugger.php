<?php
declare(strict_types=1);

namespace Szemul\DebuggerSentryBridge;

use Sentry\Breadcrumb;
use Sentry\State\HubInterface;
use Szemul\Database\Debugging\DatabaseCompleteEvent;
use Szemul\Debugger\DebuggerInterface;
use Szemul\Debugger\Event\DebugEventInterface;

class SentryBreadcrumbDebugger implements DebuggerInterface
{
    public function __construct(private HubInterface $hub, private MetadataFormatter $metadataFormatter)
    {
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
        if ($event instanceof DatabaseCompleteEvent) {
            return new Breadcrumb(
                $event->isSuccessful() ? Breadcrumb::LEVEL_DEBUG : Breadcrumb::LEVEL_ERROR,
                Breadcrumb::TYPE_DEFAULT,
                'db.query',
                $event->getStartEvent()->getQuery(),
                $this->metadataFormatter->getDatabaseMetaData($event),
                $event->getStartEvent()->getTimestamp(),
            );
        }

        return null;
    }
}
