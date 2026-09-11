<?php

declare(strict_types=1);
namespace StraschekIo\TorBlocker\Tests\Unit\Middleware;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use StraschekIo\TorBlocker\Middleware\TorBlockerMiddleware;
use StraschekIo\TorBlocker\Rendering\BlockedPageRenderer;
use StraschekIo\TorBlocker\Repository\ExitNodeRepository;
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
        $middleware = new TorBlockerMiddleware($this->createRepository(), $renderer);

        $result = $middleware->process(new ServerRequest('https://example.org/'), $this->createHandler($response));

        self::assertSame($response, $result);
    }

    public function testAnswersListedAddressesWithTheBlockedPage(): void
    {
        $_SERVER['REMOTE_ADDR'] = self::EXIT_NODE_ADDRESS;
        $renderer = $this->createMock(BlockedPageRenderer::class);
        $renderer->method('render')->willReturn('<p>blocked</p>');
        $middleware = new TorBlockerMiddleware($this->createRepository(), $renderer);

        $result = $middleware->process(new ServerRequest('https://example.org/'), $this->createFailingHandler());

        self::assertSame(403, $result->getStatusCode());
        self::assertSame('<p>blocked</p>', (string)$result->getBody());
        self::assertSame('no-store', $result->getHeaderLine('Cache-Control'));
    }

    public function testRendersTheGermanPageForGermanBrowsers(): void
    {
        $_SERVER['REMOTE_ADDR'] = self::EXIT_NODE_ADDRESS;
        $renderer = $this->createMock(BlockedPageRenderer::class);
        $renderer->expects(self::once())->method('render')->with('de')->willReturn('');
        $middleware = new TorBlockerMiddleware($this->createRepository(), $renderer);
        $request = (new ServerRequest('https://example.org/'))->withHeader('Accept-Language', 'de-DE,de;q=0.9,en;q=0.8');

        $middleware->process($request, $this->createFailingHandler());
    }

    public function testRendersTheDefaultPageForOtherLanguages(): void
    {
        $_SERVER['REMOTE_ADDR'] = self::EXIT_NODE_ADDRESS;
        $renderer = $this->createMock(BlockedPageRenderer::class);
        $renderer->expects(self::once())->method('render')->with('default')->willReturn('');
        $middleware = new TorBlockerMiddleware($this->createRepository(), $renderer);
        $request = (new ServerRequest('https://example.org/'))->withHeader('Accept-Language', 'fr-FR,fr;q=0.9');

        $middleware->process($request, $this->createFailingHandler());
    }

    public function testStillBlocksWhenTheBlockedPageCannotBeRendered(): void
    {
        $_SERVER['REMOTE_ADDR'] = self::EXIT_NODE_ADDRESS;
        $renderer = $this->createMock(BlockedPageRenderer::class);
        $renderer->method('render')->willThrowException(new \RuntimeException('Template missing'));
        $middleware = new TorBlockerMiddleware($this->createRepository(), $renderer);

        $result = $middleware->process(new ServerRequest('https://example.org/'), $this->createFailingHandler());

        self::assertSame(403, $result->getStatusCode());
        self::assertSame('', (string)$result->getBody());
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
