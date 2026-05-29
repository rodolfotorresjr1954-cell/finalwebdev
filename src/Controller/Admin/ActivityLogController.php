<?php

namespace App\Controller\Admin;

use App\Entity\ActivityLog;
use App\Repository\ActivityLogRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/activity-logs')]
#[IsGranted('ROLE_ADMIN')]
class ActivityLogController extends AbstractController
{
    public function __construct(
        private ActivityLogRepository $activityLogRepository,
        private UserRepository $userRepository,
    ) {
    }

    #[Route('', name: 'admin_activity_log_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $context = $this->buildContext($request);

        return $this->render('admin/activity_logs/index.html.twig', $context);
    }

    #[Route('/rows', name: 'admin_activity_log_rows', methods: ['GET'])]
    public function rows(Request $request): JsonResponse
    {
        $context = $this->buildContext($request);

        return $this->json($this->buildRowsPayload($context));
    }

    #[Route('/stream', name: 'admin_activity_log_stream', methods: ['GET'])]
    public function liveStream(Request $request): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($request): void {
            @set_time_limit(0);
            @ini_set('output_buffering', 'off');
            @ini_set('zlib.output_compression', '0');

            $lastSignature = '';
            $maxSeconds = 25;
            $start = time();

            while ((time() - $start) < $maxSeconds) {
                $context = $this->buildContext($request);
                $payload = $this->buildRowsPayload($context);

                if ($payload['rowsSignature'] !== $lastSignature) {
                    $lastSignature = $payload['rowsSignature'];
                    echo "event: activity_logs\n";
                    echo 'data: '.json_encode($payload, JSON_UNESCAPED_UNICODE)."\n\n";
                } else {
                    echo "event: ping\n";
                    echo "data: {}\n\n";
                }

                @ob_flush();
                @flush();
                sleep(2);
            }
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
        $response->headers->set('Connection', 'keep-alive');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
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

    /**
     * @return array<string, mixed>
     */
    private function buildContext(Request $request): array
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

        return [
            'logs' => $logs,
            'users' => $this->userRepository->findAll(),
            'actions' => ['CREATE', 'UPDATE', 'DELETE', 'LOGIN', 'LOGOUT', 'REGISTER', 'PASSWORD_CHANGE', 'PASSWORD_RESET'],
            'filters' => [
                'user' => $userId,
                'action' => $action,
                'start_date' => $startDate?->format('Y-m-d'),
                'end_date' => $endDate?->format('Y-m-d'),
            ],
            'page' => $page,
            'totalPages' => $totalPages,
            'totalLogs' => $totalLogs,
        ];
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array{rowsHtml: string, rowsSignature: string, footerText: string}
     */
    private function buildRowsPayload(array $context): array
    {
        /** @var list<ActivityLog> $logs */
        $logs = $context['logs'];
        $totalLogs = (int) $context['totalLogs'];
        $page = (int) $context['page'];
        $totalPages = (int) $context['totalPages'];

        $rowsSignature = (string) $totalLogs.'|'.implode('|', array_map(
            static fn (ActivityLog $log): string => sprintf(
                '%d:%s:%s',
                (int) $log->getId(),
                (string) $log->getAction(),
                $log->getCreatedAt()?->format('Y-m-d H:i:s') ?? ''
            ),
            $logs
        ));

        $footerText = sprintf('Showing %d of %d entries', \count($logs), $totalLogs);
        if ($totalPages > 1) {
            $footerText .= sprintf(' · Page %d / %d', $page, $totalPages);
        }

        return [
            'rowsHtml' => $this->renderView('admin/activity_logs/_rows.html.twig', [
                'logs' => $logs,
            ]),
            'rowsSignature' => $rowsSignature,
            'footerText' => $footerText,
        ];
    }
}
