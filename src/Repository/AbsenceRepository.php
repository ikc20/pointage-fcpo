<?php

namespace App\Repository;

use App\Entity\Absence;
use App\Entity\Employe;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Absence>
 */
class AbsenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Absence::class);
    }

    /**
     * Trouve les absences qui chevauchent une période donnée pour un employé
     * 
     * @param Employe $employe
     * @param \DateTimeInterface $dateDebut
     * @param \DateTimeInterface $dateFin
     * @return Absence[]
     */
    public function findConflits(
        Employe $employe, 
        \DateTimeInterface $dateDebut, 
        \DateTimeInterface $dateFin
    ): array {
        return $this->createQueryBuilder('a')
            ->where('a.employe = :employe')
            ->andWhere('a.statut != :statutRejete')
            ->andWhere('(
                (a.date_debut <= :dateFin AND a.date_fin >= :dateDebut)
            )')
            ->setParameter('employe', $employe)
            ->setParameter('statutRejete', Absence::STATUT_REJETE)
            ->setParameter('dateDebut', $dateDebut)
            ->setParameter('dateFin', $dateFin)
            ->orderBy('a.date_debut', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les absences en attente pour un employé
     */
    public function countPendingForEmploye(Employe $employe): int
    {
        return $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.employe = :employe')
            ->andWhere('a.statut = :statut')
            ->setParameter('employe', $employe)
            ->setParameter('statut', Absence::STATUT_EN_ATTENTE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Calcule le total des jours d'absence validés pour un employé
     */
    public function sumValidatedDaysForEmploye(Employe $employe): int
    {
        $absences = $this->createQueryBuilder('a')
            ->where('a.employe = :employe')
            ->andWhere('a.statut = :statut')
            ->setParameter('employe', $employe)
            ->setParameter('statut', Absence::STATUT_VALIDE)
            ->getQuery()
            ->getResult();

        $total = 0;
        foreach ($absences as $absence) {
            $debut = $absence->getDateDebut();
            $fin = $absence->getDateFin();
            $total += $debut->diff($fin)->days + 1;
        }

        return $total;
    }

    /**
     * Trouve la prochaine absence pour un employé
     */
    public function findNextForEmploye(Employe $employe): ?Absence
    {
        $today = new \DateTime();
        $today->setTime(0, 0, 0);

        return $this->createQueryBuilder('a')
            ->where('a.employe = :employe')
            ->andWhere('a.statut = :statut')
            ->andWhere('a.date_debut >= :today')
            ->setParameter('employe', $employe)
            ->setParameter('statut', Absence::STATUT_VALIDE)
            ->setParameter('today', $today)
            ->orderBy('a.date_debut', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}