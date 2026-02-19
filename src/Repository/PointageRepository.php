<?php
namespace App\Repository;

use App\Entity\Pointage;
use App\Entity\Employe;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Pointage>
 *
 * @method Pointage|null find($id, $lockMode = null, $lockVersion = null)
 * @method Pointage|null findOneBy(array $criteria, array $orderBy = null)
 * @method Pointage[]    findAll()
 * @method Pointage[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PointageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Pointage::class);
    }

    // ========== MÉTHODES DE RECHERCHE PAR EMPLOYE ==========

    /**
     * Trouve tous les pointages d'un employé
     */
    public function findByEmploye(Employe $employe): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.employe = :employe')
            ->setParameter('employe', $employe)
            ->orderBy('p.date_heure', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les pointages d'un employé entre deux dates
     * Utilisé par EmployeController::stats()
     */
    public function findForEmployeBetweenDates(
        Employe $employe, 
        \DateTimeInterface $debut, 
        \DateTimeInterface $fin
    ): array {
        return $this->createQueryBuilder('p')
            ->where('p.employe = :employe')
            ->andWhere('p.date_heure BETWEEN :debut AND :fin')
            ->setParameter('employe', $employe)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->orderBy('p.date_heure', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Historique des pointages d'un employé avec filtre de date
     */
    public function findHistoriqueEmploye(int $employeId, \DateTime $debut = null, \DateTime $fin = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.employe = :employeId')
            ->setParameter('employeId', $employeId)
            ->orderBy('p.date_heure', 'DESC');

        if ($debut) {
            $qb->andWhere('p.date_heure >= :debut')
               ->setParameter('debut', $debut);
        }

        if ($fin) {
            $qb->andWhere('p.date_heure <= :fin')
               ->setParameter('fin', $fin);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve le dernier pointage d'un employé
     */
    public function findDernierPointage(Employe $employe): ?Pointage
    {
        return $this->createQueryBuilder('p')
            ->where('p.employe = :employe')
            ->setParameter('employe', $employe)
            ->orderBy('p.date_heure', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve le dernier pointage d'un employé par type
     */
    public function findDernierPointageByType(Employe $employe, string $type): ?Pointage
    {
        return $this->createQueryBuilder('p')
            ->where('p.employe = :employe')
            ->andWhere('p.type = :type')
            ->setParameter('employe', $employe)
            ->setParameter('type', $type)
            ->orderBy('p.date_heure', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    // ========== MÉTHODES DE RECHERCHE PAR DATE ==========

    /**
     * Trouve tous les pointages d'une date spécifique
     */
    public function findByDate(\DateTime $date): array
    {
        $debut = clone $date;
        $debut->setTime(0, 0, 0);
        $fin = clone $date;
        $fin->setTime(23, 59, 59);

        return $this->createQueryBuilder('p')
            ->leftJoin('p.employe', 'e')
            ->addSelect('e')
            ->where('p.date_heure >= :debut')
            ->andWhere('p.date_heure <= :fin')
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->orderBy('p.date_heure', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Pointages d'aujourd'hui
     */
    public function findToday(): array
    {
        $today = new \DateTime();
        $today->setTime(0, 0, 0);
        $tomorrow = (clone $today)->modify('+1 day');

        return $this->createQueryBuilder('p')
            ->leftJoin('p.employe', 'e')
            ->addSelect('e')
            ->where('p.date_heure >= :today')
            ->andWhere('p.date_heure < :tomorrow')
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->orderBy('p.date_heure', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Pointages de cette semaine
     */
    public function findThisWeek(): array
    {
        $monday = new \DateTime('monday this week');
        $monday->setTime(0, 0, 0);
        $sunday = new \DateTime('sunday this week');
        $sunday->setTime(23, 59, 59);

        return $this->createQueryBuilder('p')
            ->leftJoin('p.employe', 'e')
            ->addSelect('e')
            ->where('p.date_heure >= :monday')
            ->andWhere('p.date_heure <= :sunday')
            ->setParameter('monday', $monday)
            ->setParameter('sunday', $sunday)
            ->orderBy('p.date_heure', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Pointages de ce mois
     */
    public function findThisMonth(): array
    {
        $firstDay = new \DateTime('first day of this month');
        $firstDay->setTime(0, 0, 0);
        $lastDay = new \DateTime('last day of this month');
        $lastDay->setTime(23, 59, 59);

        return $this->createQueryBuilder('p')
            ->leftJoin('p.employe', 'e')
            ->addSelect('e')
            ->where('p.date_heure >= :firstDay')
            ->andWhere('p.date_heure <= :lastDay')
            ->setParameter('firstDay', $firstDay)
            ->setParameter('lastDay', $lastDay)
            ->orderBy('p.date_heure', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Pointages entre deux dates
     */
    public function findByDateRange(\DateTime $debut, \DateTime $fin): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.employe', 'e')
            ->addSelect('e')
            ->where('p.date_heure >= :debut')
            ->andWhere('p.date_heure <= :fin')
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->orderBy('p.date_heure', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // ========== MÉTHODES DE STATISTIQUES ==========

    /**
     * Compte les pointages d'aujourd'hui
     */
    public function countToday(): int
    {
        $today = new \DateTime();
        $today->setTime(0, 0, 0);
        $tomorrow = (clone $today)->modify('+1 day');

        return $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.date_heure >= :today')
            ->andWhere('p.date_heure < :tomorrow')
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Statistiques quotidiennes
     */
    public function getDailyStats(\DateTime $date): array
    {
        $debut = clone $date;
        $debut->setTime(0, 0, 0);
        $fin = clone $date;
        $fin->setTime(23, 59, 59);

        $results = $this->createQueryBuilder('p')
            ->select('p.type, COUNT(p.id) as count')
            ->where('p.date_heure >= :debut')
            ->andWhere('p.date_heure <= :fin')
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('p.type')
            ->getQuery()
            ->getResult();

        $stats = [
            'ENTREE' => 0,
            'SORTIE' => 0,
            'total' => 0
        ];

        foreach ($results as $result) {
            $stats[$result['type']] = (int)$result['count'];
            $stats['total'] += (int)$result['count'];
        }

        return $stats;
    }

    /**
     * Statistiques mensuelles
     */
    public function getMonthlyStats(int $annee, int $mois): array
    {
        $debut = new \DateTime($annee . '-' . str_pad($mois, 2, '0', STR_PAD_LEFT) . '-01');
        $fin = (clone $debut)->modify('last day of this month')->setTime(23, 59, 59);

        return $this->createQueryBuilder('p')
            ->select('DAY(p.date_heure) as jour, COUNT(p.id) as total')
            ->addSelect("SUM(CASE WHEN p.type = 'ENTREE' THEN 1 ELSE 0 END) as entrees")
            ->addSelect("SUM(CASE WHEN p.type = 'SORTIE' THEN 1 ELSE 0 END) as sorties")
            ->where('p.date_heure >= :debut')
            ->andWhere('p.date_heure <= :fin')
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('jour')
            ->orderBy('jour', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Heure moyenne d'entrée et de sortie
     */
    public function getAverageTimes(\DateTime $debut = null, \DateTime $fin = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->select("AVG(TIME_TO_SEC(TIME(p.date_heure))) as avg_seconds, p.type")
            ->groupBy('p.type');

        if ($debut) {
            $qb->andWhere('p.date_heure >= :debut')
               ->setParameter('debut', $debut);
        }

        if ($fin) {
            $qb->andWhere('p.date_heure <= :fin')
               ->setParameter('fin', $fin);
        }

        $results = $qb->getQuery()->getResult();

        $averages = [];
        foreach ($results as $result) {
            if ($result['avg_seconds']) {
                $hours = floor($result['avg_seconds'] / 3600);
                $minutes = floor(($result['avg_seconds'] % 3600) / 60);
                $averages[$result['type']] = sprintf('%02d:%02d', $hours, $minutes);
            }
        }

        return $averages;
    }

    // ========== MÉTHODES DE DÉTECTION D'ANOMALIES ==========

    /**
     * Détecte les pointages suspects (confiance trop basse)
     */
    public function findPointagesSuspects(float $seuilConfiance = 0.6): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.employe', 'e')
            ->addSelect('e')
            ->where('p.confidence < :seuil')
            ->setParameter('seuil', $seuilConfiance)
            ->orderBy('p.confidence', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Détecte les pointages sans sortie (entrée sans sortie correspondante)
     */
    public function findEntreesSansSortie(\DateTime $date = null): array
    {
        $date = $date ?: new \DateTime();
        $date->setTime(0, 0, 0);

        // Sous-requête pour trouver les entrées avec sortie
        $subQuery = $this->createQueryBuilder('p2')
            ->select('e2.id')
            ->leftJoin('p2.employe', 'e2')
            ->where('p2.date_heure >= :date')
            ->andWhere('p2.type = :type_sortie')
            ->groupBy('e2.id');

        return $this->createQueryBuilder('p')
            ->leftJoin('p.employe', 'e')
            ->addSelect('e')
            ->where('p.date_heure >= :date')
            ->andWhere('p.type = :type_entree')
            ->andWhere('e.id NOT IN (' . $subQuery->getDQL() . ')')
            ->setParameter('date', $date)
            ->setParameter('type_entree', 'ENTREE')
            ->setParameter('type_sortie', 'SORTIE')
            ->orderBy('p.date_heure', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Détecte les retards (entrée après l'heure d'ouverture)
     */
    public function findRetards(string $heureOuverture = '08:00', \DateTime $date = null): array
    {
        $date = $date ?: new \DateTime();
        $date->setTime(0, 0, 0);
        $heureOuvertureDateTime = \DateTime::createFromFormat('Y-m-d H:i', $date->format('Y-m-d') . ' ' . $heureOuverture);

        return $this->createQueryBuilder('p')
            ->leftJoin('p.employe', 'e')
            ->addSelect('e')
            ->where('p.date_heure >= :date')
            ->andWhere('p.type = :type')
            ->andWhere('p.date_heure > :heureOuverture')
            ->setParameter('date', $date)
            ->setParameter('type', 'ENTREE')
            ->setParameter('heureOuverture', $heureOuvertureDateTime)
            ->orderBy('p.date_heure', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // ========== MÉTHODES DE PAGINATION ==========

    /**
     * Pagination des pointages
     */
    public function findPaginated(int $page = 1, int $limit = 50, array $filters = []): array
    {
        $offset = ($page - 1) * $limit;

        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.employe', 'e')
            ->addSelect('e')
            ->orderBy('p.date_heure', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        $this->applyFilters($qb, $filters);

        return [
            'items' => $qb->getQuery()->getResult(),
            'total' => $this->countWithFilters($filters),
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($this->countWithFilters($filters) / $limit)
        ];
    }

    private function applyFilters(QueryBuilder $qb, array $filters): void
    {
        if (!empty($filters['employe_id'])) {
            $qb->andWhere('p.employe = :employe_id')
               ->setParameter('employe_id', $filters['employe_id']);
        }

        if (!empty($filters['type'])) {
            $qb->andWhere('p.type = :type')
               ->setParameter('type', $filters['type']);
        }

        if (!empty($filters['date_debut'])) {
            $qb->andWhere('p.date_heure >= :date_debut')
               ->setParameter('date_debut', $filters['date_debut']);
        }

        if (!empty($filters['date_fin'])) {
            $qb->andWhere('p.date_heure <= :date_fin')
               ->setParameter('date_fin', $filters['date_fin']);
        }

        if (!empty($filters['search'])) {
            $qb->andWhere('e.nom LIKE :search OR e.prenom LIKE :search OR e.matricule LIKE :search')
               ->setParameter('search', '%' . $filters['search'] . '%');
        }
    }

    private function countWithFilters(array $filters): int
    {
        $qb = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->leftJoin('p.employe', 'e');

        $this->applyFilters($qb, $filters);

        return (int)$qb->getQuery()->getSingleScalarResult();
    }

    // ========== MÉTHODES D'EXPORT ==========

    /**
     * Export des pointages pour Excel/CSV
     */
    public function findForExport(\DateTime $debut = null, \DateTime $fin = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.employe', 'e')
            ->select('p.id, p.date_heure, p.type, p.confidence')
            ->addSelect('e.nom, e.prenom, e.matricule, e.poste')
            ->orderBy('p.date_heure', 'DESC');

        if ($debut) {
            $qb->andWhere('p.date_heure >= :debut')
               ->setParameter('debut', $debut);
        }

        if ($fin) {
            $qb->andWhere('p.date_heure <= :fin')
               ->setParameter('fin', $fin);
        }

        return $qb->getQuery()->getArrayResult();
    }

    // ========== MÉTHODES DE MAINTENANCE ==========

    /**
     * Supprime les anciens pointages (archivage)
     */
    public function archiveOldPointages(int $jours = 365): int
    {
        $dateLimite = new \DateTime('-' . $jours . ' days');

        $qb = $this->createQueryBuilder('p')
            ->where('p.date_heure < :dateLimite')
            ->setParameter('dateLimite', $dateLimite);

        $pointages = $qb->getQuery()->getResult();
        $count = count($pointages);

        foreach ($pointages as $pointage) {
            $this->getEntityManager()->remove($pointage);
        }

        if ($count > 0) {
            $this->getEntityManager()->flush();
        }

        return $count;
    }
}