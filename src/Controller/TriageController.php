<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TriageController extends AbstractController
{
    #[Route('/qualification', name: 'app_triage', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('triage/index.html.twig');
    }
}
