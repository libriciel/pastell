<?php

declare(strict_types=1);

namespace Pastell\Controller;

use FrontController;
use ObjectInstancierFactory;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

final class LegacyController extends AbstractController
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function loadLegacyScript(string $requestPath, string $legacyScript): Response
    {
        $_SERVER['PHP_SELF'] = $requestPath;
        $_SERVER['SCRIPT_NAME'] = $requestPath;
        $_SERVER['SCRIPT_FILENAME'] = $legacyScript;

        \chdir(\dirname($legacyScript));

        $objectInstancier = ObjectInstancierFactory::getObjetInstancier();
        $frontController = new FrontController($objectInstancier);

        $frontController->setGetParameter($_GET);
        $frontController->setPostParameter($_POST);
        $frontController->setServerInfo($_SERVER);
        $frontController->setTwigEnvironment($this->container->get('twig'));
        $objectInstancier->setInstance(Environment::class, $this->container->get('twig'));

        if ($this->isFileDownloadRequest($requestPath)) {
            \ob_start(chunk_size: 8192);
        } else {
            \ob_start();
        }

        $frontController->dispatch();
        $content = (string)\ob_get_clean();

        $headers = [];
        foreach (\headers_list() as $header) {
            $trimmed = \explode(': ', $header);
            $headers[$trimmed[0]] = $trimmed[1];
        }
        return new Response($content, 200, $headers);
    }

    private function isFileDownloadRequest(string $requestPath): bool
    {
        $downloadRequestPath = [
            '/Document/recuperationFichier',
            '/DonneesFormulaire/downloadAll',
            '/MailSec/export',
        ];

        return in_array($requestPath, $downloadRequestPath);
    }
}
