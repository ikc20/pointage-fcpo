<?php

namespace App\Repository;

use App\Entity\Planning;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Planning>
 */
class PlanningRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Planning::class);
    }

    /**
     * Trouve le planning actif pour une date donnée
     */
    public function findActiveForDate(\DateTimeInterface $date): ?Planning
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.actif = true')
            ->orderBy('p.date_debut', 'DESC')
            ->setMaxResults(1);

        // Si la date est spécifiée, on filtre par période
        if ($date) {
            $qb->andWhere('p.date_debut <= :date')
               ->andWhere('p.date_fin >= :date OR p.date_fin IS NULL')
               ->setParameter('date', $date);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * Trouve tous les plannings actifs
     */
    public function findAllActive(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.actif = true')
            ->orderBy('p.date_debut', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve le planning pour aujourd'hui
     */
    public function findForToday(): ?Planning
    {
        return $this->findActiveForDate(new \DateTime());
    }

    /**
     * Vérifie si une date est dans une période de planning spécifique
     */
    public function isInPeriod(\DateTimeInterface $date, string $type): bool
    {
        $count = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.actif = true')
            ->andWhere('p.type = :type')
            ->andWhere('p.date_debut <= :date')
            ->andWhere('p.date_fin >= :date OR p.date_fin IS NULL')
            ->setParameter('type', $type)
            ->setParameter('date', $date)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Récupère tous les types de plannings distincts
     */
    public function getDistinctTypes(): array
    {
        $result = $this->createQueryBuilder('p')
            ->select('DISTINCT p.type')
            ->orderBy('p.type', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();

        return $result;
    }
}