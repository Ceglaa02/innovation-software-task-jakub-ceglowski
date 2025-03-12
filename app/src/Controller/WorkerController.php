<?php

namespace App\Controller;

use App\Services\WorkerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class WorkerController extends AbstractController
{
    private WorkerService $workerService;

    public function __construct(WorkerService $workerService)
    {
        $this->workerService = $workerService;
    }

    #[Route('/worker/add', name: 'addWorker', methods: ['POST'])]
    public function addWorker(Request $request): JsonResponse
    {
        $error = '';
        $data = [
            'name' => $request->get('name', ''),
            'surname' => $request->get('surname', ''),
            'id' => uniqid('user', true)
        ];

        $id = $this->workerService->add($data);

        if (!is_null($id)) {
            return new JsonResponse(['response' => ['id' => $id]]);
        } else {
            return new JsonResponse(['response' => ['id' => null, 'error' => $error]]);
        }
    }

    #[Route('/worker-time/register', name: 'registerWorker', methods: ['POST'])]
    public function registerWorkerTime(Request $request): JsonResponse
    {
        $data = [
            'worker_id' => $request->get('worker_id', ''),
            'start' => $request->get('start', ''),
            'end' => $request->get('end', '')
        ];

        $message = $this->workerService->registerTime($data);

        return new JsonResponse(['response' => ['message' => $message]]);
    }

    #[Route('/worker-time/summary', name: 'summaryWorker', methods: ['POST'])]
    public function summaryWorkerTime(Request $request): JsonResponse
    {
        $workerId = $request->get('worker_id', '');
        $day = $request->get('day', '');

        $message = $this->workerService->summaryTime($workerId, $day);

        return new JsonResponse(['response' => ['response' => $message]]);
    }
}