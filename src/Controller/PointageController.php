<?php
// src/Controller/PointageController.php

namespace App\Controller;

use App\Entity\Pointage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/pointages')]
class PointageController extends AbstractController
{
    #[Route('/', name: 'api_pointages_index', methods: ['GET'])]
    public function index(EntityManagerInterface $em, Request $request): JsonResponse
    {
        try {
            $pointages = $em->getRepository(Pointage::class)->findBy([], ['date_heure' => 'DESC'], 50);
            $data = [];
            
            foreach ($pointages as $pointage) {
                $data[] = [
                    'id' => $pointage->getId(),
                    'date_heure' => $pointage->getDateHeure() ? 
                        $pointage->getDateHeure()->format('Y-m-d H:i:s') : null,
                    'type' => $pointage->getType(),
                    'employe_id' => $pointage->getEmploye() ? $pointage->getEmploye()->getId() : null,
                    'employe_nom' => $pointage->getEmploye() ? 
                        $pointage->getEmploye()->getNomComplet() : null
                ];
            }
            
            return $this->json([
                'success' => true,
                'data' => $data,
                'count' => count($data)
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}