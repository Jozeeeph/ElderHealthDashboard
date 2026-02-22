<?php

namespace App\Controller\Admin;

use App\Service\AiDashboardInsightService;
use App\Service\DashboardMetricsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardAiController extends AbstractController
{
    public function __construct(
        private readonly AiDashboardInsightService $aiService,
        private readonly DashboardMetricsService $metricsService
    ) {}

    #[Route('/admin/dashboard/ai-insights', name: 'admin_dashboard_ai_insights', methods: ['GET'])]
    public function insights(): JsonResponse
    {
        try {
            $metrics = $this->metricsService->getAdminDashboardMetrics();
            $report = $this->aiService->generateInsights($metrics);

            // ✅ EXACTEMENT ce que ton JS attend
            return $this->json([
                'ok' => true,
                'report_md' => $report,
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'ok' => false,
                'message' => "Erreur IA : impossible de générer le rapport",
                'debug' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}