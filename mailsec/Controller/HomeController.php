<?php

declare(strict_types=1);

namespace Mailsec\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'mailsec_index', methods: ['GET'])]
    public function index(Request $request): RedirectResponse
    {
        return $this->redirectToRoute('mailsec_recipient_index', ['key' => $request->query->get('key')]);
    }

    #[Route('/{path}/index.php', name: 'mailsec_custom_url_path', methods: ['GET'])]
    public function customUrlPath(Request $request): RedirectResponse
    {
        return $this->index($request);
    }
}
