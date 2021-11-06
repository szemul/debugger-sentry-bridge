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
use Sentry\Breadcrumb;
use Sentry\State\HubInterface;
use Szemul\Database\Debugging\DatabaseCompleteEvent;
use Szemul\Database\Debugging\DatabaseStartEvent;
use Szemul\Debugger\Event\DebugEventInterface;
use Szemul\Debugger\Event\HttpRequestSentEvent;
use Szemul\Debugger\Event\HttpResponseReceivedEvent;
use Szemul\DebuggerSentryBridge\MetadataFormatter;
use Szemul\DebuggerSentryBridge\SentryBreadcrumbDebugger;
use PHPUnit\Framework\TestCase;
use Throwable;

class SentryBreadcrumbDebuggerTest extends TestCase
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

    private HubInterface|MockInterface|LegacyMockInterface $hub;
    private MetadataFormatter                              $metadataFormatter;
    private SentryBreadcrumbDebugger                       $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hub               = Mockery::mock(HubInterface::class);
        $this->metadataFormatter = new MetadataFormatter();

        $this->sut = new SentryBreadcrumbDebugger($this->hub, $this->metadataFormatter); // @phpstan-ignore-line
    }

    public function testDebugInfo(): void
    {
        $expected = [
            'hub'               => '*** Instance of ' . get_class($this->hub),
            'metadataFormatter' => $this->metadataFormatter,
        ];

        $this->assertEquals($expected, $this->sut->__debugInfo());
    }

    public function testSuccessfulDatabaseEvent(): void
    {
        $event = $this->getDatabaseCompleteEvent();
        $this->expectDatabaseBreadcrumbAdded();

        $this->sut->handleEvent($event);
    }

    public function testFailedDatabaseEvent(): void
    {
        $errorMessage = 'Test exception';
        $event        = $this->getDatabaseCompleteEvent(new Exception($errorMessage));

        $this->expectDatabaseBreadcrumbAdded(Breadcrumb::LEVEL_ERROR, $errorMessage);

        $this->sut->handleEvent($event);
    }

    public function testSuccessfulHttpResponseEvent(): void
    {
        $event = $this->getHttpResponseReceivedEvent();
        $this->expectHttpResponseBreadcrumbAdded();

        $this->sut->handleEvent($event);
    }

    public function testFailedHttpResponseEvent(): void
    {
        $exception = new Exception('Test exception');
        $event     = $this->getHttpResponseReceivedEvent($exception);

        $this->expectHttpResponseBreadcrumbAdded(
            Breadcrumb::LEVEL_ERROR,
            $this->getExceptionMessageForException($exception),
        );

        $this->sut->handleEvent($event);
    }

    public function testOtherEvent_shouldNotBeHandled(): void
    {
        $this->sut->handleEvent($this->getMockEvent());

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

    private function expectDatabaseBreadcrumbAdded(
        string $level = Breadcrumb::LEVEL_INFO,
        ?string $errorMessage = null,
    ): void {
        // @phpstan-ignore-next-line
        $this->hub->shouldReceive('addBreadcrumb')
            ->once()
            ->with(Mockery::on(function (Breadcrumb $breadcrumb) use ($level, $errorMessage) {
                $this->assertSame(SentryBreadcrumbDebugger::OPERATION_DB_QUERY, $breadcrumb->getCategory());
                $this->assertSame($level, $breadcrumb->getLevel());
                $this->assertSame(Breadcrumb::TYPE_DEFAULT, $breadcrumb->getType());
                $this->assertSame(self::DB_QUERY, $breadcrumb->getMessage());

                $metadata = $breadcrumb->getMetadata();
                $this->assertSame(self::DB_BACKEND_TYPE, $metadata['backendType']);
                $this->assertSame(self::DB_CONNECTION_NAME, $metadata['connectionName']);
                $this->assertEqualsWithDelta(0, (float)$metadata['executionTimeMs'], 5);
                $this->assertSame(self::DB_PARAMS, $metadata['parameters']);
                $this->assertSame($errorMessage, $metadata['error'] ?? null);

                return true;
            }));
    }

    private function expectHttpResponseBreadcrumbAdded(
        string $level = Breadcrumb::LEVEL_INFO,
        ?string $errorMessage = null,
    ): void {
        // @phpstan-ignore-next-line
        $this->hub->shouldReceive('addBreadcrumb')
            ->once()
            ->with(Mockery::on(function (Breadcrumb $breadcrumb) use ($level, $errorMessage) {
                $this->assertSame(SentryBreadcrumbDebugger::OPERATION_HTTP_REQUEST, $breadcrumb->getCategory());
                $this->assertSame($level, $breadcrumb->getLevel());
                $this->assertSame(Breadcrumb::TYPE_HTTP, $breadcrumb->getType());
                $this->assertSame(self::HTTP_METHOD . ' ' . self::HTTP_URL, $breadcrumb->getMessage());

                $metadata = $breadcrumb->getMetadata();
                $this->assertSame(self::HTTP_URL, $metadata['uri']);
                $this->assertSame(self::HTTP_METHOD, $metadata['requestMethod']);
                $this->assertSame(self::HTTP_RESPONSE_CODE, $metadata['responseCode']);
                $this->assertSame(strlen(self::HTTP_RESPONSE_BODY), $metadata['responseBodyLength']);
                $this->assertEqualsWithDelta(10, (float)$metadata['executionTimeMs'], 10);
                $this->assertSame($errorMessage, $metadata['exception'] ?? null);

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
