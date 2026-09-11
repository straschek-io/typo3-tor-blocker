<?php

declare(strict_types=1);
namespace StraschekIo\TorBlocker\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use StraschekIo\TorBlocker\Rendering\BlockedPageRenderer;
use StraschekIo\TorBlocker\Repository\ExitNodeRepository;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class TorBlockerMiddleware implements MiddlewareInterface
{
    /**
     * Languages with a translation of the blocked page, everything else gets the default
     */
    private const SUPPORTED_LANGUAGE_KEYS = ['de'];

    private BlockedPageRenderer $blockedPageRenderer;

    private ExitNodeRepository $exitNodeRepository;

    public function __construct(ExitNodeRepository $exitNodeRepository, BlockedPageRenderer $blockedPageRenderer)
    {
        $this->exitNodeRepository = $exitNodeRepository;
        $this->blockedPageRenderer = $blockedPageRenderer;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $remoteAddress = $this->resolveRemoteAddress($request);
        if ($remoteAddress === '' || !$this->exitNodeRepository->contains($remoteAddress)) {
            return $handler->handle($request);
        }

        try {
            $body = $this->blockedPageRenderer->render($this->resolveLanguageKey($request));
        } catch (\Throwable $exception) {
            // Blocking must not depend on the template being renderable
            $body = '';
        }

        return new HtmlResponse($body, 403, ['Cache-Control' => 'no-store']);
    }

    private function resolveLanguageKey(ServerRequestInterface $request): string
    {
        $acceptLanguage = strtolower($request->getHeaderLine('Accept-Language'));
        $preferredLanguage = substr((string)strtok($acceptLanguage, ',;-'), 0, 2);

        return in_array($preferredLanguage, self::SUPPORTED_LANGUAGE_KEYS, true) ? $preferredLanguage : 'default';
    }

    private function resolveRemoteAddress(ServerRequestInterface $request): string
    {
        $normalizedParams = $request->getAttribute('normalizedParams');
        if ($normalizedParams instanceof NormalizedParams) {
            return $normalizedParams->getRemoteAddress();
        }

        // This middleware runs before the normalizedParams attribute is set;
        // getIndpEnv() respects the reverse proxy configuration as well
        return (string)GeneralUtility::getIndpEnv('REMOTE_ADDR');
    }
}
