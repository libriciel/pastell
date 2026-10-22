<?php

declare(strict_types=1);

namespace Pastell\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class AboutController extends AbstractController
{
    #[Route('/about', name: 'app_about')]
    public function index(): JsonResponse
    {
        return $this->json([
            'message' => 'Controller de test pour symfony',
            'path' => 'src/Controller/AboutController.php',
        ]);
    }
}
