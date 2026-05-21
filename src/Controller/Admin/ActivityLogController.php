<?php

namespace App\Controller\Admin;

use App\Repository\ActivityLogRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/activity-logs')]
#[IsGranted('ROLE_ADMIN')]
class ActivityLogController extends AbstractController
{
    public function __construct(
        private ActivityLogRepository $activityLogRepository,
        private UserRepository $userRepository
    ) {
    }

    #[Route('', name: 'admin_activity_log_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $userId = $request->query->getInt('user');
        $action = $request->query->get('action');
        $startDate = $request->query->get('start_date') ? new \DateTime($request->query->get('start_date')) : null;
        $endDate = $request->query->get('end_date') ? new \DateTime($request->query->get('end_date')) : null;
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        $logs = $this->activityLogRepository->findWithFilters(
            $userId ?: null,
            $action ?: null,
            $startDate,
            $endDate,
            $perPage,
            $offset
        );

        $totalLogs = $this->activityLogRepository->countWithFilters(
            $userId ?: null,
            $action ?: null,
            $startDate,
            $endDate
        );

        $totalPages = (int) ceil($totalLogs / $perPage) ?: 1;

        $users = $this->userRepository->findAll();
        $actions = ['CREATE', 'UPDATE', 'DELETE', 'LOGIN', 'LOGOUT', 'PASSWORD_CHANGE', 'PASSWORD_RESET'];

        return $this->render('admin/activity_logs/index.html.twig', [
            'logs' => $logs,
            'users' => $users,
            'actions' => $actions,
            'filters' => [
                'user' => $userId,
                'action' => $action,
                'start_date' => $startDate?->format('Y-m-d'),
                'end_date' => $endDate?->format('Y-m-d'),
            ],
            'page' => $page,
            'totalPages' => $totalPages,
            'totalLogs' => $totalLogs,
        ]);
    }

    #[Route('/{id}', name: 'admin_activity_log_show', methods: ['GET'])]
    public function show(int $id): Response
    {
        $log = $this->activityLogRepository->find($id);

        if (!$log) {
            throw $this->createNotFoundException('Activity log not found.');
        }

        return $this->render('admin/activity_logs/show.html.twig', [
            'log' => $log,
        ]);
    }
}

