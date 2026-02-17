<?php

namespace App\Controller\Api; 

use App\Entity\Absence;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/absences')]
class AbsenceController extends AbstractController
{
    private const TYPES_VALIDES = ['CONGE', 'MALADIE', 'FORMATION', 'FAMILIAL', 'AUTRE'];

    #[Route('', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json([
                'success' => false,
                'code' => 'MISSING_DATA',
                'message' => 'Données manquantes'
            ], 400);
        }

        if (empty($data['date_debut']) || empty($data['date_fin'])) {
            return $this->json([
                'success' => false,
                'code' => 'MISSING_DATES',
                'message' => 'Les dates de début et fin sont requises'
            ], 400);
        }

        $type = $data['type'] ?? 'CONGE';
        if (!in_array($type, self::TYPES_VALIDES)) {
            return $this->json([
                'success' => false,
                'code' => 'INVALID_ABSENCE_TYPE',
                'message' => 'Type d\'absence invalide'
            ], 400);
        }

        try {
            $dateDebut = new \DateTimeImmutable($data['date_debut']);
            $dateFin   = new \DateTimeImmutable($data['date_fin']);
        } catch (\Exception) {
            return $this->json([
                'success' => false,
                'code' => 'INVALID_DATE_FORMAT',
                'message' => 'Format de date invalide'
            ], 400);
        }

        if ($dateFin < $dateDebut) {
            return $this->json([
                'success' => false,
                'code' => 'END_DATE_BEFORE_START',
                'message' => 'La date de fin doit être postérieure à la date de début'
            ], 400);
        }

        $user = $this->getUser();
        if (!$user) {
            return $this->json([
                'success' => false,
                'code' => 'UNAUTHENTICATED',
                'message' => 'Utilisateur non authentifié'
            ], 401);
        }

        $employe = $user->getEmploye();
        if (!$employe) {
            return $this->json([
                'success' => false,
                'code' => 'NO_EMPLOYEE_LINKED',
                'message' => 'Aucun employé associé à cet utilisateur'
            ], 400);
        }

        $conflits = $em->getRepository(Absence::class)->findConflits(
            $employe,
            $dateDebut,
            $dateFin
        );

        if (!empty($conflits)) {
            return $this->json([
                'success' => false,
                'code' => 'OVERLAPPING_ABSENCE',
                'message' => 'Une absence existe déjà sur cette période',
                'conflits' => array_map([$this, 'serializeAbsence'], $conflits)
            ], 409);
        }

        $absence = (new Absence())
            ->setEmploye($employe)
            ->setType($type)
            ->setDateDebut(\DateTime::createFromImmutable($dateDebut))
            ->setDateFin(\DateTime::createFromImmutable($dateFin))
            ->setMotif($data['motif'] ?? null)
            ->setStatut(Absence::STATUT_EN_ATTENTE);

        $em->persist($absence);
        $em->flush();

        return $this->json([
            'success' => true,
            'code' => 'ABSENCE_CREATED',
            'message' => 'Demande d’absence créée avec succès',
            'data' => $this->serializeAbsence($absence)
        ], 201);
    }

    #[Route('/employe/{id}', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function byEmploye(
        int $id,
        EntityManagerInterface $em
    ): JsonResponse {
        $user = $this->getUser();
        
        if ($user->getEmploye()?->getId() !== $id && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json([
                'success' => false,
                'code' => 'ACCESS_DENIED',
                'message' => 'Accès non autorisé'
            ], 403);
        }

        $absences = $em->getRepository(Absence::class)
            ->findBy(
                ['employe' => $id],
                ['date_debut' => 'DESC']
            );

        return $this->json([
            'success' => true,
            'data' => array_map([$this, 'serializeAbsence'], $absences)
        ]);
    }

    private function serializeAbsence(Absence $a): array
    {
        return [
            'id' => $a->getId(),
            'type' => $a->getType(),
            'type_label' => match ($a->getType()) {
                'CONGE' => 'Congé payé',
                'MALADIE' => 'Maladie',
                'FORMATION' => 'Formation',
                'FAMILIAL' => 'Événement familial',
                'AUTRE' => 'Autre',
                default => $a->getType()
            },
            'date_debut' => $a->getDateDebut()->format('Y-m-d'),
            'date_fin' => $a->getDateFin()->format('Y-m-d'),
            'statut' => $a->getStatut(),
            'statut_label' => match ($a->getStatut()) {
                Absence::STATUT_EN_ATTENTE => 'En attente de validation',
                Absence::STATUT_VALIDE => 'Validée',
                Absence::STATUT_REJETE => 'Rejetée',
                default => 'Inconnu'
            },
            'motif' => $a->getMotif(),
            'duree_jours' => $a->getDateDebut()->diff($a->getDateFin())->days + 1,
            'created_at' => $a->getCreatedAt()?->format('Y-m-d H:i:s')
        ];
    }
}