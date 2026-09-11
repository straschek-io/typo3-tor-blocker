<?php

declare(strict_types=1);
namespace StraschekIo\TorBlocker\Tests\Unit\Middleware;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use StraschekIo\TorBlocker\Middleware\TorBlockerMiddleware;
use StraschekIo\TorBlocker\Network\IpAddressNormalizer;
use StraschekIo\TorBlocker\Rendering\BlockedPageRenderer;
use StraschekIo\TorBlocker\Repository\ExitNodeRepository;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * @covers \StraschekIo\TorBlocker\Middleware\TorBlockerMiddleware
 */
final class TorBlockerMiddlewareTest extends TestCase
{
    private const EXIT_NODE_ADDRESS = '203.0.113.7';

    private array $serverBackup;

    /**
     * @var mixed
     */
    private $typo3ConfVarsBackup;

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;
        $this->typo3ConfVarsBackup = $GLOBALS['TYPO3_CONF_VARS'] ?? null;
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['reverseProxyIP'] = '';
        // getIndpEnv() caches the client address per process
        GeneralUtility::flushInternalRuntimeCaches();
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        $GLOBALS['TYPO3_CONF_VARS'] = $this->typo3ConfVarsBackup;
        GeneralUtility::flushInternalRuntimeCaches();
    }

    public function testPassesTheRequestOnWhenTheAddressIsNotListed(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.0.2.1';
        $response = new Response();
        $renderer = $this->createMock(BlockedPageRenderer::class);
        $renderer->expects(self::never())->method('render');
        $middleware = $this->createMiddleware($renderer);

        $result = $middleware->process(new ServerRequest('https://example.org/'), $this->createHandler($response));

        self::assertSame($response, $result);
    }

    public function testPassesTheRequestOnWithoutAClientAddress(): void
    {
        unset($_SERVER['REMOTE_ADDR']);
        $response = new Response();
        $middleware = $this->createMiddleware($this->createMock(BlockedPageRenderer::class));

        $result = $middleware->process(new ServerRequest('https://example.org/'), $this->createHandler($response));

        self::assertSame($response, $result);
    }

    public function testAnswersListedAddressesWithTheBlockedPage(): void
    {
        $_SERVER['REMOTE_ADDR'] = self::EXIT_NODE_ADDRESS;
        $renderer = $this->createMock(BlockedPageRenderer::class);
        $renderer->method('render')->willReturn('<p>blocked</p>');
        $middleware = $this->createMiddleware($renderer);

        $result = $middleware->process(new ServerRequest('https://example.org/'), $this->createFailingHandler());

        self::assertSame(403, $result->getStatusCode());
        self::assertSame('<p>blocked</p>', (string)$result->getBody());
        self::assertSame('no-store', $result->getHeaderLine('Cache-Control'));
    }

    public function testBlocksListedAddressesReportedAsIpv4MappedIpv6(): void
    {
        $_SERVER['REMOTE_ADDR'] = '::ffff:' . self::EXIT_NODE_ADDRESS;
        $renderer = $this->createMock(BlockedPageRenderer::class);
        $renderer->method('render')->willReturn('');
        $middleware = $this->createMiddleware($renderer);

        $result = $middleware->process(new ServerRequest('https://example.org/'), $this->createFailingHandler());

        self::assertSame(403, $result->getStatusCode());
    }

    public function testUsesTheNormalizedParamsAttributeWhenPresent(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.0.2.1';
        $normalizedParams = $this->createMock(NormalizedParams::class);
        $normalizedParams->method('getRemoteAddress')->willReturn(self::EXIT_NODE_ADDRESS);
        $renderer = $this->createMock(BlockedPageRenderer::class);
        $renderer->method('render')->willReturn('');
        $middleware = $this->createMiddleware($renderer);
        $request = (new ServerRequest('https://example.org/'))->withAttribute('normalizedParams', $normalizedParams);

        $result = $middleware->process($request, $this->createFailingHandler());

        self::assertSame(403, $result->getStatusCode());
    }

    /**
     * @dataProvider acceptLanguageProvider
     */
    public function testChoosesTheLanguageFromTheAcceptLanguageHeader(string $header, string $expectedLanguageKey): void
    {
        $_SERVER['REMOTE_ADDR'] = self::EXIT_NODE_ADDRESS;
        $renderer = $this->createMock(BlockedPageRenderer::class);
        $renderer->expects(self::once())->method('render')->with($expectedLanguageKey)->willReturn('');
        $middleware = $this->createMiddleware($renderer);
        $request = new ServerRequest('https://example.org/');
        if ($header !== '') {
            $request = $request->withHeader('Accept-Language', $header);
        }

        $middleware->process($request, $this->createFailingHandler());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public function acceptLanguageProvider(): array
    {
        return [
            'german browser' => ['de-DE,de;q=0.9,en;q=0.8', 'de'],
            'english browser' => ['en-US,en;q=0.9,de;q=0.8', 'default'],
            'other language' => ['fr-FR,fr;q=0.9', 'default'],
            'other language with german fallback' => ['fr-FR,fr;q=0.9,de;q=0.5', 'de'],
            'quality wins over position' => ['de;q=0.1,en;q=1.0', 'default'],
            'upper case and underscore' => ['DE_AT', 'de'],
            'zero quality is ignored' => ['de;q=0,en', 'default'],
            'wildcard' => ['*', 'default'],
            'no header' => ['', 'default'],
            'garbage' => ['q=0.9,deutsch, ,;;', 'default'],
        ];
    }

    public function testStillBlocksWhenTheBlockedPageCannotBeRendered(): void
    {
        $_SERVER['REMOTE_ADDR'] = self::EXIT_NODE_ADDRESS;
        $renderer = $this->createMock(BlockedPageRenderer::class);
        $renderer->method('render')->willThrowException(new \RuntimeException('Template missing'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');
        $middleware = $this->createMiddleware($renderer);
        $middleware->setLogger($logger);

        $result = $middleware->process(new ServerRequest('https://example.org/'), $this->createFailingHandler());

        self::assertSame(403, $result->getStatusCode());
        self::assertSame('', (string)$result->getBody());
    }

    private function createMiddleware(BlockedPageRenderer $renderer): TorBlockerMiddleware
    {
        return new TorBlockerMiddleware($this->createRepository(), $renderer, new IpAddressNormalizer());
    }

    private function createRepository(): ExitNodeRepository
    {
        $repository = $this->createMock(ExitNodeRepository::class);
        $repository->method('contains')->willReturnCallback(
            static function (string $address): bool {
                return $address === self::EXIT_NODE_ADDRESS;
            }
        );

        return $repository;
    }

    private function createHandler(ResponseInterface $response): RequestHandlerInterface
    {
        return new class($response) implements RequestHandlerInterface {
            private ResponseInterface $response;

            public function __construct(ResponseInterface $response)
            {
                $this->response = $response;
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };
    }

    private function createFailingHandler(): RequestHandlerInterface
    {
        return new class() implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new \LogicException('A blocked request must not reach the next handler.');
            }
        };
    }
}
