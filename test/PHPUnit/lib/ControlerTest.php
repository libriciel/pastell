<?php

declare(strict_types=1);

use Pastell\Tests\SymfonyContainerFactory;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class ControlerTest extends PHPUnit\Framework\TestCase
{
    private ObjectInstancier $objectInstancier;
    private Controler $controler;

    protected function setUp(): void
    {
        $this->objectInstancier = new ObjectInstancier();
        $symfonyContainer = SymfonyContainerFactory::getSymfonyContainer();
        /** @var Environment $environment */
        $environment = $symfonyContainer->get('twig');
        $this->controler = new Controler($this->objectInstancier);
        $this->controler->setTwigEnvironment($environment);
    }

    public function testSetDontRedirect(): void
    {
        $this->controler->setDontRedirect(true);
        $this->assertTrue($this->controler->isDontRedirect());
    }

    public function testSetAllViewParameter(): void
    {
        $this->controler->setAllViewParameter(['foo' => 'bar']);
        $this->assertEquals('bar', $this->controler->getViewParameter()['foo']);
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     */
    public function testRedirect(): void
    {
        $this->objectInstancier->setInstance('site_base', 'test');
        $this->expectException('Exception');
        $this->expectExceptionMessage('Exit called with code 0');
        $this->expectOutputRegex('#Location: .*foo#');
        $this->controler->redirect('foo');
    }

    public function testRender(): void
    {
        $this->objectInstancier->setInstance('template_path', __DIR__ . '/../../../template');
        $this->expectOutputRegex("/Vous n'avez aucun droit sur cette plateforme/");
        $this->controler->renderLegacy('ConnexionNoDroit');
    }

    /**
     * @throws SyntaxError
     * @throws UnrecoverableException
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function testRenderDefault(): void
    {
        $this->objectInstancier->setInstance('template_path', __DIR__ . '/../../../template');
        $this->controler->setViewParameter('template_milieu', 'ConnexionNoDroit');
        $this->controler->setViewParameter('page_title', '');
        $this->controler->setViewParameter('authentification', $this->createMock(Authentification::class));
        $this->controler->setViewParameter('dont_display_breacrumbs', false);
        $this->controler->setViewParameter('manifest_info', ['version' => '', 'revision' => '',]);
        $this->controler->setViewParameter('timer', new PastellTimer());
        $this->expectOutputRegex("/Vous n'avez aucun droit sur cette plateforme/");
        $this->controler->renderDefault();
    }
}
