<?php
// src/Controller/EmployeController.php

namespace App\Controller;

use App\Entity\Employe;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;  // Note: 'Attribute' au lieu de 'Annotation'

#[Route('/api/employes')]
class EmployeController extends AbstractController
{
    #[Route('/', name: 'api_employes_index', methods: ['GET'])]
    public function index(EntityManagerInterface $em): JsonResponse
    {
        try {
            $employes = $em->getRepository(Employe::class)->findAll();
            $data = [];
            
            foreach ($employes as $employe) {
                $data[] = [
                    'id' => $employe->getId(),
                    'nom' => $employe->getNom(),
                    'prenom' => $employe->getPrenom(),
                    'email' => $employe->getEmail(),
                    'matricule' => $employe->getMatricule(),
                    'poste' => $employe->getPoste()
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

    #[Route('/{id}', name: 'api_employes_show', methods: ['GET'])]
    public function show(Employe $employe): JsonResponse
    {
        return $this->json([
            'success' => true,
            'data' => [
                'id' => $employe->getId(),
                'nom' => $employe->getNom(),
                'prenom' => $employe->getPrenom(),
                'email' => $employe->getEmail(),
                'matricule' => $employe->getMatricule(),
                'poste' => $employe->getPoste(),
                'telephone' => $employe->getTelephone(),
                'date_embauche' => $employe->getDateEmbauche() ? 
                    $employe->getDateEmbauche()->format('Y-m-d') : null
            ]
        ]);
    }
}