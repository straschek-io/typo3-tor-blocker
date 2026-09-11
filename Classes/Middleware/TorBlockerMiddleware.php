<?php

declare(strict_types=1);
namespace StraschekIo\TorBlocker\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use StraschekIo\TorBlocker\Network\IpAddressNormalizer;
use StraschekIo\TorBlocker\Rendering\BlockedPageRenderer;
use StraschekIo\TorBlocker\Repository\ExitNodeRepository;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\NormalizedParams;

class TorBlockerMiddleware implements MiddlewareInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Languages with a translation of the blocked page, mapped to the key of the label file.
     * English is the source language of the labels, so it maps to "default".
     */
    private const LANGUAGE_KEYS = ['en' => 'default', 'de' => 'de'];

    private BlockedPageRenderer $blockedPageRenderer;

    private ExitNodeRepository $exitNodeRepository;

    private IpAddressNormalizer $ipAddressNormalizer;

    public function __construct(
        ExitNodeRepository $exitNodeRepository,
        BlockedPageRenderer $blockedPageRenderer,
        IpAddressNormalizer $ipAddressNormalizer
    ) {
        $this->exitNodeRepository = $exitNodeRepository;
        $this->blockedPageRenderer = $blockedPageRenderer;
        $this->ipAddressNormalizer = $ipAddressNormalizer;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $remoteAddress = $this->ipAddressNormalizer->normalize($this->resolveRemoteAddress($request));
        if ($remoteAddress === '' || !$this->exitNodeRepository->contains($remoteAddress)) {
            return $handler->handle($request);
        }

        try {
            $body = $this->blockedPageRenderer->render($this->resolveLanguageKey($request));
        } catch (\Throwable $exception) {
            // Blocking must not depend on the template being renderable
            if ($this->logger !== null) {
                $this->logger->error('Could not render the blocked page, answering with an empty body.', [
                    'exception' => $exception,
                ]);
            }
            $body = '';
        }

        return new HtmlResponse($body, 403, ['Cache-Control' => 'no-store']);
    }

    private function resolveLanguageKey(ServerRequestInterface $request): string
    {
        foreach ($this->parseAcceptLanguage($request->getHeaderLine('Accept-Language')) as $language) {
            if (isset(self::LANGUAGE_KEYS[$language])) {
                return self::LANGUAGE_KEYS[$language];
            }
        }

        return 'default';
    }

    /**
     * Primary language subtags of an Accept-Language header, best quality first.
     *
     * @param string $header
     * @return string[]
     */
    private function parseAcceptLanguage(string $header): array
    {
        $languages = [];
        $position = 0;
        foreach (explode(',', $header) as $entry) {
            $parameters = array_map('trim', explode(';', $entry));
            $tag = strtolower((string)array_shift($parameters));
            $quality = 1.0;
            foreach ($parameters as $parameter) {
                if (strpos($parameter, 'q=') === 0) {
                    $quality = (float)substr($parameter, 2);
                }
            }
            $primaryLanguage = explode('-', str_replace('_', '-', $tag))[0];
            if ($quality <= 0 || !preg_match('/^[a-z]{2,8}$/', $primaryLanguage)) {
                continue;
            }
            $languages[] = ['language' => $primaryLanguage, 'quality' => $quality, 'position' => $position++];
        }
        // usort() is not stable before PHP 8, the position keeps the header order for equal qualities
        usort($languages, static function (array $first, array $second): int {
            return $second['quality'] <=> $first['quality'] ?: $first['position'] <=> $second['position'];
        });

        return array_column($languages, 'language');
    }

    /**
     * The client address as the core determines it, including the reverse proxy configuration.
     * This middleware runs before the core sets the normalizedParams attribute, so it usually
     * has to create the params itself.
     */
    private function resolveRemoteAddress(ServerRequestInterface $request): string
    {
        $normalizedParams = $request->getAttribute('normalizedParams');
        if (!$normalizedParams instanceof NormalizedParams) {
            $normalizedParams = NormalizedParams::createFromRequest($request, $GLOBALS['TYPO3_CONF_VARS']['SYS'] ?? []);
        }

        return $normalizedParams->getRemoteAddress();
    }
}
