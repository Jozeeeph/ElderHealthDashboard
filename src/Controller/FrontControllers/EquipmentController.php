<?php

namespace App\Controller\FrontControllers;

use App\Repository\EquipementRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/equipements')]
class EquipmentController extends AbstractController
{
    #[Route('/', name: 'front_equipements_index', methods: ['GET'])]
    public function index(Request $request, EquipementRepository $equipementRepository): Response
    {
        $sort = $this->normalizeSort((string) $request->query->get('sort', 'best_sellers'));

        return $this->render('FrontOffice/equipement/index.html.twig', [
            'equipements' => $equipementRepository->findBy([], $this->getSortOrder($sort)),
            'current_category' => null,
            'current_sort' => $sort,
        ]);
    }

    #[Route('/categorie/{categorie}', name: 'front_equipements_by_category', methods: ['GET'])]
    public function byCategory(string $categorie, Request $request, EquipementRepository $equipementRepository): Response
    {
        $sort = $this->normalizeSort((string) $request->query->get('sort', 'best_sellers'));
        $equipements = $equipementRepository->findBy(['categorie' => $categorie], $this->getSortOrder($sort));
        
        return $this->render('FrontOffice/equipement/index.html.twig', [
            'equipements' => $equipements,
            'current_category' => $categorie,
            'current_sort' => $sort,
        ]);
    }

    #[Route('/search-ajax', name: 'front_equipements_search_ajax', methods: ['GET'])]
    public function searchAjax(Request $request, EquipementRepository $equipementRepository): JsonResponse
    {
        $query = (string) $request->query->get('q', '');
        $sort = $this->normalizeSort((string) $request->query->get('sort', 'best_sellers'));
        $category = $request->query->get('category');
        $category = is_string($category) && $category !== '' ? $category : null;

        $equipements = $equipementRepository->searchForFront($query, $category, $this->getSortOrder($sort));

        $html = $this->renderView('FrontOffice/equipement/_products_results.html.twig', [
            'equipements' => $equipements,
        ]);

        return $this->json([
            'html' => $html,
            'count' => count($equipements),
        ]);
    }

    #[Route('/{id}', name: 'front_equipements_show', methods: ['GET'])]
    public function show(int $id, EquipementRepository $equipementRepository): Response
    {
        $equipement = $equipementRepository->find($id);
        
        if (!$equipement) {
            throw $this->createNotFoundException('Équipement non trouvé');
        }

        return $this->render('FrontOffice/equipement/show.html.twig', [
            'equipement' => $equipement,
        ]);
    }

    private function normalizeSort(string $sort): string
    {
        $allowedSorts = ['best_sellers', 'price_asc', 'price_desc', 'newest'];

        return in_array($sort, $allowedSorts, true) ? $sort : 'best_sellers';
    }

    private function getSortOrder(string $sort): array
    {
        return match ($sort) {
            'price_asc' => ['prix' => 'ASC'],
            'price_desc' => ['prix' => 'DESC'],
            'newest' => ['dateAjout' => 'DESC'],
            default => ['id' => 'DESC'],
        };
    }
}
