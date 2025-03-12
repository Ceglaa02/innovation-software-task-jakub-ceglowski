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
            'id' => uniqid('user',true)
        ];

        $id = $this->workerService->add($data);

        if (!is_null($id)) {
            return new JsonResponse(['id' => $id]);
        } else {
            return new JsonResponse(['id' => null, 'error' => $error]);
        }
    }
}