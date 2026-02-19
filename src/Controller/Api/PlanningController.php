<?php

namespace App\Controller\Api;

use App\Entity\Planning;
use App\Repository\PlanningRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/plannings')]
#[IsGranted('ROLE_ADMIN')] 
class PlanningController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    public function index(PlanningRepository $planningRepo): JsonResponse
    {
        $plannings = $planningRepo->findAll();

        return $this->json([
            'success' => true,
            'data' => array_map(fn($p) => $p->toArray(), $plannings)
        ]);
    }

    #[Route('/actif', methods: ['GET'])]
    public function actif(PlanningRepository $planningRepo): JsonResponse
    {
        $planning = $planningRepo->findForToday();

        return $this->json([
            'success' => true,
            'data' => $planning?->toArray()
        ]);
    }

    #[Route('', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        ValidatorInterface $validator
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json([
                'success' => false,
                'message' => 'JSON invalide'
            ], Response::HTTP_BAD_REQUEST);
        }

        $required = ['type', 'heure_debut', 'heure_fin'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return $this->json([
                    'success' => false,
                    'message' => "Champ requis manquant: $field"
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        $planning = new Planning();
        $planning->setType($data['type']);
        $planning->setNom($data['nom'] ?? null);
        $planning->setHeureDebut(new \DateTime($data['heure_debut']));
        $planning->setHeureFin(new \DateTime($data['heure_fin']));

        if (!empty($data['date_debut'])) {
            $planning->setDateDebut(new \DateTime($data['date_debut']));
        }
        if (!empty($data['date_fin'])) {
            $planning->setDateFin(new \DateTime($data['date_fin']));
        }
        if (!empty($data['pause_debut'])) {
            $planning->setPauseDebut(new \DateTime($data['pause_debut']));
        }
        if (!empty($data['pause_fin'])) {
            $planning->setPauseFin(new \DateTime($data['pause_fin']));
        }

        $planning->setPauseObligatoire($data['pause_obligatoire'] ?? false);
        $planning->setDureePauseMin($data['duree_pause_min'] ?? 60);
        $planning->setActif($data['actif'] ?? true);

        $errors = $validator->validate($planning);
        if (count($errors) > 0) {
            $messages = [];
            foreach ($errors as $error) {
                $messages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json([
                'success' => false,
                'errors' => $messages
            ], Response::HTTP_BAD_REQUEST);
        }

        $em->persist($planning);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Planning créé avec succès',
            'data' => $planning->toArray()
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(
        int $id,
        Request $request,
        PlanningRepository $planningRepo,
        EntityManagerInterface $em,
        ValidatorInterface $validator
    ): JsonResponse {
        $planning = $planningRepo->find($id);

        if (!$planning) {
            return $this->json([
                'success' => false,
                'message' => 'Planning non trouvé'
            ], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json([
                'success' => false,
                'message' => 'JSON invalide'
            ], Response::HTTP_BAD_REQUEST);
        }

        if (isset($data['type'])) $planning->setType($data['type']);
        if (isset($data['nom'])) $planning->setNom($data['nom']);
        if (isset($data['heure_debut'])) $planning->setHeureDebut(new \DateTime($data['heure_debut']));
        if (isset($data['heure_fin'])) $planning->setHeureFin(new \DateTime($data['heure_fin']));
        if (isset($data['date_debut'])) $planning->setDateDebut(new \DateTime($data['date_debut']));
        if (isset($data['date_fin'])) $planning->setDateFin(new \DateTime($data['date_fin']));
        if (isset($data['pause_debut'])) $planning->setPauseDebut(new \DateTime($data['pause_debut']));
        if (isset($data['pause_fin'])) $planning->setPauseFin(new \DateTime($data['pause_fin']));
        if (isset($data['pause_obligatoire'])) $planning->setPauseObligatoire($data['pause_obligatoire']);
        if (isset($data['duree_pause_min'])) $planning->setDureePauseMin($data['duree_pause_min']);
        if (isset($data['actif'])) $planning->setActif($data['actif']);

        $errors = $validator->validate($planning);
        if (count($errors) > 0) {
            $messages = [];
            foreach ($errors as $error) {
                $messages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json([
                'success' => false,
                'errors' => $messages
            ], Response::HTTP_BAD_REQUEST);
        }

        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Planning mis à jour',
            'data' => $planning->toArray()
        ]);
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(int $id, PlanningRepository $planningRepo, EntityManagerInterface $em): JsonResponse
    {
        $planning = $planningRepo->find($id);

        if (!$planning) {
            return $this->json([
                'success' => false,
                'message' => 'Planning non trouvé'
            ], Response::HTTP_NOT_FOUND);
        }

        $em->remove($planning);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Planning supprimé'
        ]);
    }
}