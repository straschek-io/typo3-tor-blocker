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
use TYPO3\CMS\Core\Core\ApplicationContext;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\ServerRequest;

/**
 * @covers \StraschekIo\TorBlocker\Middleware\TorBlockerMiddleware
 */
final class TorBlockerMiddlewareTest extends TestCase
{
    private const EXIT_NODE_ADDRESS = '203.0.113.7';

    /**
     * @var mixed
     */
    private $typo3ConfVarsBackup;

    protected function setUp(): void
    {
        $this->typo3ConfVarsBackup = $GLOBALS['TYPO3_CONF_VARS'] ?? null;
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['reverseProxyIP'] = '';
        // NormalizedParams::createFromRequest() reads the script and public path from the environment
        $projectPath = sys_get_temp_dir() . '/tor_blocker_test_project';
        Environment::initialize(
            new ApplicationContext('Testing'),
            true,
            true,
            $projectPath,
            $projectPath . '/public',
            $projectPath . '/var',
            $projectPath . '/config',
            $projectPath . '/public/index.php',
            'UNIX'
        );
    }

    protected function tearDown(): void
    {
        $GLOBALS['TYPO3_CONF_VARS'] = $this->typo3ConfVarsBackup;
    }

    public function testPassesTheRequestOnWhenTheAddressIsNotListed(): void
    {
        $response = new Response();
        $renderer = $this->createMock(BlockedPageRenderer::class);
        $renderer->expects(self::never())->method('render');
        $middleware = $this->createMiddleware($renderer);

        $result = $middleware->process($this->createRequest('192.0.2.1'), $this->createHandler($response));

        self::assertSame($response, $result);
    }

    public function testPassesTheRequestOnWithoutAClientAddress(): void
    {
        $response = new Response();
        $middleware = $this->createMiddleware($this->createMock(BlockedPageRenderer::class));

        $result = $middleware->process($this->createRequest(null), $this->createHandler($response));

        self::assertSame($response, $result);
    }

    public function testAnswersListedAddressesWithTheBlockedPage(): void
    {
        $renderer = $this->createMock(BlockedPageRenderer::class);
        $renderer->method('render')->willReturn('<p>blocked</p>');
        $middleware = $this->createMiddleware($renderer);

        $result = $middleware->process($this->createRequest(self::EXIT_NODE_ADDRESS), $this->createFailingHandler());

        self::assertSame(403, $result->getStatusCode());
        self::assertSame('<p>blocked</p>', (string)$result->getBody());
        self::assertSame('no-store', $result->getHeaderLine('Cache-Control'));
    }

    public function testBlocksListedAddressesReportedAsIpv4MappedIpv6(): void
    {
        $renderer = $this->createMock(BlockedPageRenderer::class);
        $renderer->method('render')->willReturn('');
        $middleware = $this->createMiddleware($renderer);

        $result = $middleware->process($this->createRequest('::ffff:' . self::EXIT_NODE_ADDRESS), $this->createFailingHandler());

        self::assertSame(403, $result->getStatusCode());
    }

    public function testRespectsTheReverseProxyConfiguration(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['reverseProxyIP'] = '192.0.2.1';
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['reverseProxyHeaderMultiValue'] = 'first';
        $renderer = $this->createMock(BlockedPageRenderer::class);
        $renderer->method('render')->willReturn('');
        $middleware = $this->createMiddleware($renderer);
        $request = $this->createRequest('192.0.2.1', ['HTTP_X_FORWARDED_FOR' => self::EXIT_NODE_ADDRESS]);

        $result = $middleware->process($request, $this->createFailingHandler());

        self::assertSame(403, $result->getStatusCode());
    }

    public function testIgnoresForwardedHeadersWithoutAReverseProxy(): void
    {
        $response = new Response();
        $middleware = $this->createMiddleware($this->createMock(BlockedPageRenderer::class));
        $request = $this->createRequest('192.0.2.1', ['HTTP_X_FORWARDED_FOR' => self::EXIT_NODE_ADDRESS]);

        $result = $middleware->process($request, $this->createHandler($response));

        self::assertSame($response, $result);
    }

    public function testUsesTheNormalizedParamsAttributeWhenPresent(): void
    {
        $normalizedParams = $this->createMock(NormalizedParams::class);
        $normalizedParams->method('getRemoteAddress')->willReturn(self::EXIT_NODE_ADDRESS);
        $renderer = $this->createMock(BlockedPageRenderer::class);
        $renderer->method('render')->willReturn('');
        $middleware = $this->createMiddleware($renderer);
        $request = $this->createRequest('192.0.2.1')->withAttribute('normalizedParams', $normalizedParams);

        $result = $middleware->process($request, $this->createFailingHandler());

        self::assertSame(403, $result->getStatusCode());
    }

    /**
     * @dataProvider acceptLanguageProvider
     */
    public function testChoosesTheLanguageFromTheAcceptLanguageHeader(string $header, string $expectedLanguageKey): void
    {
        $renderer = $this->createMock(BlockedPageRenderer::class);
        $renderer->expects(self::once())->method('render')->with($expectedLanguageKey)->willReturn('');
        $middleware = $this->createMiddleware($renderer);
        $request = $this->createRequest(self::EXIT_NODE_ADDRESS);
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
        $renderer = $this->createMock(BlockedPageRenderer::class);
        $renderer->method('render')->willThrowException(new \RuntimeException('Template missing'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');
        $middleware = $this->createMiddleware($renderer);
        $middleware->setLogger($logger);

        $result = $middleware->process($this->createRequest(self::EXIT_NODE_ADDRESS), $this->createFailingHandler());

        self::assertSame(403, $result->getStatusCode());
        self::assertSame('', (string)$result->getBody());
    }

    /**
     * @param array<string, string> $additionalServerParams
     */
    private function createRequest(?string $remoteAddress, array $additionalServerParams = []): ServerRequest
    {
        $serverParams = ['HTTP_HOST' => 'example.org', 'REQUEST_URI' => '/', 'SCRIPT_NAME' => '/index.php'];
        if ($remoteAddress !== null) {
            $serverParams['REMOTE_ADDR'] = $remoteAddress;
        }

        return new ServerRequest('https://example.org/', 'GET', 'php://input', [], $serverParams + $additionalServerParams);
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
