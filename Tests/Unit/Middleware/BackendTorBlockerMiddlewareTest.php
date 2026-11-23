<?php

declare(strict_types=1);
namespace StraschekIo\TorBlocker\Tests\Unit\Middleware;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use StraschekIo\TorBlocker\Configuration\ExtensionSettings;
use StraschekIo\TorBlocker\Middleware\BackendTorBlockerMiddleware;
use StraschekIo\TorBlocker\Network\IpAddressNormalizer;
use StraschekIo\TorBlocker\Rendering\BlockedPageRenderer;
use StraschekIo\TorBlocker\Repository\ExitNodeRepository;
use TYPO3\CMS\Core\Core\ApplicationContext;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\ServerRequest;

/**
 * @covers \StraschekIo\TorBlocker\Middleware\BackendTorBlockerMiddleware
 */
final class BackendTorBlockerMiddlewareTest extends TestCase
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
        $projectPath = sys_get_temp_dir() . '/tor_blocker_test_project';
        Environment::initialize(
            new ApplicationContext('Testing'),
            true,
            true,
            $projectPath,
            $projectPath . '/public',
            $projectPath . '/var',
            $projectPath . '/config',
            $projectPath . '/public/typo3/index.php',
            'UNIX'
        );
    }

    protected function tearDown(): void
    {
        $GLOBALS['TYPO3_CONF_VARS'] = $this->typo3ConfVarsBackup;
    }

    public function testBlocksTheBackendLoginFromListedAddresses(): void
    {
        $renderer = $this->createMock(BlockedPageRenderer::class);
        $renderer->method('render')->willReturn('<p>blocked</p>');
        $middleware = $this->createMiddleware($renderer, new ExtensionSettings([]));

        $result = $middleware->process($this->createRequest(self::EXIT_NODE_ADDRESS), $this->createFailingHandler());

        self::assertSame(403, $result->getStatusCode());
        self::assertSame('<p>blocked</p>', (string)$result->getBody());
        self::assertSame('no-store', $result->getHeaderLine('Cache-Control'));
    }

    public function testPassesUnlistedAddressesOn(): void
    {
        $response = new Response();
        $renderer = $this->createMock(BlockedPageRenderer::class);
        $renderer->expects(self::never())->method('render');
        $middleware = $this->createMiddleware($renderer, new ExtensionSettings([]));

        $result = $middleware->process($this->createRequest('192.0.2.1'), $this->createHandler($response));

        self::assertSame($response, $result);
    }

    public function testPassesListedAddressesOnWhenBackendBlockingIsSwitchedOff(): void
    {
        $response = new Response();
        $renderer = $this->createMock(BlockedPageRenderer::class);
        $renderer->expects(self::never())->method('render');
        $middleware = $this->createMiddleware($renderer, new ExtensionSettings(['blockBackend' => '0']));

        $result = $middleware->process($this->createRequest(self::EXIT_NODE_ADDRESS), $this->createHandler($response));

        self::assertSame($response, $result);
    }

    private function createRequest(string $remoteAddress): ServerRequest
    {
        $serverParams = [
            'HTTP_HOST' => 'example.org',
            'REQUEST_URI' => '/typo3/login',
            'SCRIPT_NAME' => '/typo3/index.php',
            'REMOTE_ADDR' => $remoteAddress,
        ];

        return new ServerRequest('https://example.org/typo3/login', 'POST', 'php://input', [], $serverParams);
    }

    private function createMiddleware(BlockedPageRenderer $renderer, ExtensionSettings $settings): BackendTorBlockerMiddleware
    {
        $repository = $this->createMock(ExitNodeRepository::class);
        $repository->method('contains')->willReturnCallback(
            static function (string $address): bool {
                return $address === self::EXIT_NODE_ADDRESS;
            }
        );

        return new BackendTorBlockerMiddleware($repository, $renderer, new IpAddressNormalizer(), $settings);
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
