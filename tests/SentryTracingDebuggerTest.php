<?php
declare(strict_types=1);

namespace Szemul\DebuggerSentryBridge\Test;

use Exception;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Stream;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\LegacyMockInterface;
use Mockery\MockInterface;
use Sentry\Tracing\Span;
use Sentry\Tracing\SpanContext;
use Sentry\Tracing\SpanStatus;
use Szemul\Database\Debugging\DatabaseCompleteEvent;
use Szemul\Database\Debugging\DatabaseStartEvent;
use Szemul\Debugger\Event\DebugEventInterface;
use Szemul\Debugger\Event\HttpRequestSentEvent;
use Szemul\Debugger\Event\HttpResponseReceivedEvent;
use Szemul\DebuggerSentryBridge\MetadataFormatter;
use Szemul\DebuggerSentryBridge\SentryTracingDebugger;
use PHPUnit\Framework\TestCase;
use Szemul\DebuggerSentryBridge\SentryTracingState;
use Throwable;

class SentryTracingDebuggerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const DB_BACKEND_TYPE    = 'testType';
    private const DB_CONNECTION_NAME = 'testConnection';
    private const DB_QUERY           = 'testQuery';
    private const DB_PARAMS          = [
        'testParam' => 'value',
    ];
    private const HTTP_METHOD        = 'GET';
    private const HTTP_URL           = 'https://example.com/test';
    private const HTTP_RESPONSE_CODE = 200;
    private const HTTP_RESPONSE_BODY = 'testBody';

    private Span|MockInterface|LegacyMockInterface $span;
    private SentryTracingDebugger                  $sut;
    private SentryTracingState                     $tracingState;

    protected function setUp(): void
    {
        parent::setUp();

        $this->span         = Mockery::mock(Span::class);
        $this->tracingState = new SentryTracingState();
        $this->sut          = new SentryTracingDebugger(new MetadataFormatter(), $this->tracingState);

        $this->tracingState->setSpan($this->span); // @phpstan-ignore-line
    }

    public function testSuccessfulDatabaseEvent(): void
    {
        $event = $this->getDatabaseCompleteEvent();
        $this->expectDatabaseChildAdded(SpanStatus::ok());

        $this->sut->handleEvent($event);
    }

    public function testFailedDatabaseEvent(): void
    {
        $errorMessage = 'Test exception';
        $event        = $this->getDatabaseCompleteEvent(new Exception($errorMessage));

        $this->expectDatabaseChildAdded(SpanStatus::unknownError(), $errorMessage);

        $this->sut->handleEvent($event);
    }

    public function testSuccessfulHttpResponseEvent(): void
    {
        $event = $this->getHttpResponseReceivedEvent();
        $this->expectHttpResponseChildAdded(SpanStatus::ok());

        $this->sut->handleEvent($event);
    }

    public function testFailedHttpResponseEvent(): void
    {
        $exception = new Exception('Test exception');
        $event     = $this->getHttpResponseReceivedEvent($exception);

        $this->expectHttpResponseChildAdded(SpanStatus::ok(), $this->getExceptionMessageForException($exception));

        $this->sut->handleEvent($event);
    }

    public function testOtherEvent_shouldNotBeHandled(): void
    {
        $this->sut->handleEvent($this->getMockEvent());

        // Noop assert to avoid phpunit complaining
        $this->assertTrue(true);
    }

    public function testNoSpanOrTransaction_shouldNotBeHandled(): void
    {
        $state = new SentryTracingState();
        $sut   = new SentryTracingDebugger(new MetadataFormatter(), $state);

        $sut->handleEvent($this->getMockEvent());

        // Noop assert to avoid phpunit complaining
        $this->assertTrue(true);
    }

    private function getMockEvent(): DebugEventInterface|MockInterface|LegacyMockInterface
    {
        return Mockery::mock(DebugEventInterface::class);
    }

    private function getDatabaseCompleteEvent(?Throwable $exception = null): DatabaseCompleteEvent
    {
        return new DatabaseCompleteEvent(
            new DatabaseStartEvent(self::DB_BACKEND_TYPE, self::DB_CONNECTION_NAME, self::DB_QUERY, self::DB_PARAMS),
            $exception,
        );
    }

    private function getHttpResponseReceivedEvent(?Throwable $exception = null): HttpResponseReceivedEvent
    {
        $streamResource = fopen('php://temp', 'w+');
        fwrite($streamResource, self::HTTP_RESPONSE_BODY);
        fseek($streamResource, 0);

        return new HttpResponseReceivedEvent(
            new HttpRequestSentEvent(new Request(self::HTTP_METHOD, self::HTTP_URL)),
            new Response(self::HTTP_RESPONSE_CODE, body: new Stream($streamResource)),
            $exception,
        );
    }

    private function expectDatabaseChildAdded(SpanStatus $status, ?string $errorMessage = null): void
    {
        // @phpstan-ignore-next-line
        $this->span->shouldReceive('startChild')
            ->once()
            ->with(Mockery::on(function (SpanContext $spanContext) use ($status, $errorMessage) {
                $this->assertSame(SentryTracingDebugger::OPERATION_DB_QUERY, $spanContext->getOp());
                $this->assertSame((string)$status, (string)$spanContext->getStatus());
                $this->assertSame(self::DB_QUERY, $spanContext->getDescription());

                $data = $spanContext->getData();
                $this->assertSame(self::DB_BACKEND_TYPE, $data['backendType']);
                $this->assertSame(self::DB_CONNECTION_NAME, $data['connectionName']);
                $this->assertEqualsWithDelta(0, (float)$data['executionTimeMs'], 5);
                $this->assertSame(self::DB_PARAMS, $data['parameters']);
                $this->assertSame($errorMessage, $data['error'] ?? null);

                return true;
            }));
    }

    private function expectHttpResponseChildAdded(SpanStatus $status, ?string $errorMessage = null): void
    {
        // @phpstan-ignore-next-line
        $this->span->shouldReceive('startChild')
            ->once()
            ->with(Mockery::on(function (SpanContext $spanContext) use ($status, $errorMessage) {
                $this->assertSame(SentryTracingDebugger::OPERATION_HTTP_REQUEST, $spanContext->getOp());
                $this->assertSame((string)$status, (string)$spanContext->getStatus());
                $this->assertSame(self::HTTP_METHOD . ' ' . self::HTTP_URL, $spanContext->getDescription());

                $data = $spanContext->getData();
                $this->assertSame(self::HTTP_URL, $data['uri']);
                $this->assertSame(self::HTTP_METHOD, $data['requestMethod']);
                $this->assertSame(self::HTTP_RESPONSE_CODE, $data['responseCode']);
                $this->assertSame(strlen(self::HTTP_RESPONSE_BODY), $data['responseBodyLength']);
                $this->assertEqualsWithDelta(10, (float)$data['executionTimeMs'], 10);
                $this->assertSame($errorMessage, $data['exception'] ?? null);

                return true;
            }));
    }

    private function getExceptionMessageForException(Throwable $exception): string
    {
        return sprintf(
            '%s (%d): %s',
            get_class($exception),
            $exception->getCode(),
            $exception->getMessage(),
        );
    }
}
