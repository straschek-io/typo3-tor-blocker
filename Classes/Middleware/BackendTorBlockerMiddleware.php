<?php

declare(strict_types=1);
namespace StraschekIo\TorBlocker\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use StraschekIo\TorBlocker\Configuration\ExtensionSettings;
use StraschekIo\TorBlocker\Network\IpAddressNormalizer;
use StraschekIo\TorBlocker\Rendering\BlockedPageRenderer;
use StraschekIo\TorBlocker\Repository\ExitNodeRepository;

/**
 * Blocks backend requests, the login included, unless the extension configuration switches it off.
 */
final class BackendTorBlockerMiddleware extends TorBlockerMiddleware
{
    private ExtensionSettings $extensionSettings;

    public function __construct(
        ExitNodeRepository $exitNodeRepository,
        BlockedPageRenderer $blockedPageRenderer,
        IpAddressNormalizer $ipAddressNormalizer,
        ExtensionSettings $extensionSettings
    ) {
        parent::__construct($exitNodeRepository, $blockedPageRenderer, $ipAddressNormalizer);
        $this->extensionSettings = $extensionSettings;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->extensionSettings->isBackendBlocked()) {
            return $handler->handle($request);
        }

        return parent::process($request, $handler);
    }
}
