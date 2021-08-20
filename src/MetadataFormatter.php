<?php
declare(strict_types=1);

namespace Szemul\DebuggerSentryBridge;

use JetBrains\PhpStorm\ArrayShape;
use Szemul\Database\Debugging\DatabaseCompleteEvent;

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
}
