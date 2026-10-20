<?php

declare(strict_types=1);

namespace Pastell\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_homepage')]
    public function index(Request $request): RedirectResponse
    {
        return $this->redirectToRoute('app.legacy.document_index', $request->query->all());
    }
}
