<?php
declare(strict_types=1);

namespace Szemul\DebuggerSentryBridge;

use JetBrains\PhpStorm\ArrayShape;
use Szemul\Database\Debugging\DatabaseCompleteEvent;
use Szemul\Debugger\Event\HttpResponseReceivedEvent;

class MetadataFormatter
{
    /**
     * @return array<string,mixed>
     */
    #[ArrayShape(['backendType'     => 'string',
                  'connectionName'  => 'string',
                  'executionTimeMs' => 'float',
                  'parameters'      => 'mixed[]',
                  'error'           => 'string',
    ])]
    public function getDatabaseMetaData(DatabaseCompleteEvent $event): array
    {
        $startEvent = $event->getStartEvent();
        $metaData   = [
            'backendType'     => $startEvent->getBackendType(),
            'connectionName'  => $startEvent->getConnectionName(),
            'executionTimeMs' => round($event->getRuntime() * 1000, 2),
            'parameters'      => $startEvent->getParams(),
        ];

        if (!$event->isSuccessful()) {
            $metaData['error'] = $event->getException()->getMessage();
        }

        return $metaData;
    }

    /** @return array<string,mixed> */
    public function getHttpRequestMetadata(HttpResponseReceivedEvent $event): array
    {
        $request      = $event->getRequestSentEvent()->getRequest();

        $metaData = [
            'uri'                => (string)$request->getUri(),
            'requestMethod'      => $request->getMethod(),
            'responseCode'       => $event->getStatusCode(),
            'responseBodyLength' => $event->getBodyLength(),
            'executionTimeMs'    => round($event->getRuntime() * 1000, 2),
        ];

        if (!empty($event->getThrowable())) {
            $throwable             = $event->getThrowable();
            $metaData['exception'] = sprintf(
                '%s (%d): %s',
                get_class($throwable),
                $throwable->getCode(),
                $throwable->getMessage(),
            );
        }

        return $metaData;
    }
}
