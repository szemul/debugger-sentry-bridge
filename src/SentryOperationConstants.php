<?php
declare(strict_types=1);

namespace Szemul\DebuggerSentryBridge;

interface SentryOperationConstants
{
    public const OPERATION_DB_QUERY     = 'db.query';
    public const OPERATION_HTTP_REQUEST = 'http.request';
}
