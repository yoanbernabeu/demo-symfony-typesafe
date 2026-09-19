<?php

namespace App\Controller;

use App\Entity\Experience;
use App\Repository\ExperienceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

final class InboxController extends AbstractController
{
    private const PER_PAGE = 20;

    #[Route('/', name: 'app_inbox', methods: ['GET'])]
    public function index(
        ExperienceRepository $experiences,
        #[MapQueryParameter(options: ['min_range' => 1])] int $page = 1,
        #[MapQueryParameter] string $q = '',
    ): Response {
        return $this->render('inbox/index.html.twig', [
            'page' => $experiences->paginate($page, self::PER_PAGE, trim($q)),
            'search' => trim($q),
            'selected' => null,
        ]);
    }

    #[Route('/demandes/{id}', name: 'app_inbox_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Experience $experience, Request $request, ExperienceRepository $experiences): Response
    {
        // Clicking a row only asks for the "experience" Turbo Frame: no need to query and render the list again
        if ('experience' === $request->headers->get('Turbo-Frame')) {
            return $this->render('inbox/_experience.html.twig', ['selected' => $experience]);
        }

        return $this->render('inbox/index.html.twig', [
            'page' => $experiences->paginate(1, self::PER_PAGE),
            'search' => '',
            'selected' => $experience,
        ]);
    }
}
