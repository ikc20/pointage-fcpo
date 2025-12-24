<?php

namespace App\Repository;

use App\Entity\Employe;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Employe>
 *
 * @method Employe|null find($id, $lockMode = null, $lockVersion = null)
 * @method Employe|null findOneBy(array $criteria, array $orderBy = null)
 * @method Employe[]    findAll()
 * @method Employe[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EmployeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Employe::class);
    }

    // ========== MÉTHODES DE RECHERCHE ==========

    /**
     * Recherche des employés par nom, prénom, email ou matricule
     */
    public function search(string $term): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.nom LIKE :term')
            ->orWhere('e.prenom LIKE :term')
            ->orWhere('e.email LIKE :term')
            ->orWhere('e.matricule LIKE :term')
            ->orWhere('e.poste LIKE :term')
            ->setParameter('term', '%' . $term . '%')
            ->orderBy('e.nom', 'ASC')
            ->addOrderBy('e.prenom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve un employé par son matricule
     */
    public function findByMatricule(string $matricule): ?Employe
    {
        return $this->createQueryBuilder('e')
            ->where('e.matricule = :matricule')
            ->setParameter('matricule', $matricule)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve un employé par son email
     */
    public function findByEmail(string $email): ?Employe
    {
        return $this->createQueryBuilder('e')
            ->where('e.email = :email')
            ->setParameter('email', $email)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve un employé par son QR code
     */
    public function findByQrCode(string $qrCode): ?Employe
    {
        return $this->createQueryBuilder('e')
            ->where('e.qr_code = :qrCode')
            ->setParameter('qrCode', $qrCode)
            ->getQuery()
            ->getOneOrNullResult();
    }

    // ========== MÉTHODES DE STATISTIQUES ==========

    /**
     * Compte le nombre total d'employés
     */
    public function countEmployes(): int
    {
        return $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Récupère les employés avec leur dernier pointage
     */
    public function findWithLastPointage(): array
    {
        $result = $this->createQueryBuilder('e')
            ->leftJoin('e.pointages', 'p')
            ->addSelect('p')
            ->orderBy('e.nom', 'ASC')
            ->getQuery()
            ->getResult();

        // On pourrait optimiser avec une sous-requête DQL
        return $result;
    }

    /**
     * Trouve les employés actuellement présents (dernier pointage = ENTRÉE aujourd'hui)
     */
    public function findEmployesPresents(): array
    {
        $today = new \DateTime();
        $today->setTime(0, 0, 0);
        $tomorrow = (clone $today)->modify('+1 day');

        return $this->createQueryBuilder('e')
            ->leftJoin('e.pointages', 'p')
            ->where('p.date_heure >= :today')
            ->andWhere('p.date_heure < :tomorrow')
            ->andWhere('p.type = :type')
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->setParameter('type', 'ENTREE')
            ->groupBy('e.id')
            ->having('MAX(p.date_heure) = p.date_heure')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques par poste
     */
    public function getStatsByPoste(): array
    {
        $results = $this->createQueryBuilder('e')
            ->select('e.poste, COUNT(e.id) as count')
            ->groupBy('e.poste')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($results as $result) {
            $stats[$result['poste']] = (int)$result['count'];
        }

        return $stats;
    }

    /**
     * Employés sans pointage récent (dernière semaine)
     */
    public function findEmployesSansPointageRecent(int $jours = 7): array
    {
        $dateLimite = new \DateTime('-' . $jours . ' days');
        $dateLimite->setTime(0, 0, 0);

        // Sous-requête pour les employés avec pointage récent
        $subQuery = $this->createQueryBuilder('e2')
            ->select('e2.id')
            ->leftJoin('e2.pointages', 'p2')
            ->where('p2.date_heure >= :dateLimite')
            ->groupBy('e2.id');

        return $this->createQueryBuilder('e')
            ->where('e.id NOT IN (' . $subQuery->getDQL() . ')')
            ->setParameter('dateLimite', $dateLimite)
            ->getQuery()
            ->getResult();
    }

    // ========== MÉTHODES DE PAGINATION ==========

    /**
     * Pagination avec filtres optionnels
     */
    public function findPaginated(int $page = 1, int $limit = 20, array $filters = []): array
    {
        $offset = ($page - 1) * $limit;

        $qb = $this->createQueryBuilder('e')
            ->orderBy('e.nom', 'ASC')
            ->addOrderBy('e.prenom', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        // Appliquer les filtres
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
        if (!empty($filters['search'])) {
            $qb->andWhere('e.nom LIKE :search OR e.prenom LIKE :search OR e.email LIKE :search OR e.matricule LIKE :search')
                ->setParameter('search', '%' . $filters['search'] . '%');
        }

        if (!empty($filters['poste'])) {
            $qb->andWhere('e.poste = :poste')
                ->setParameter('poste', $filters['poste']);
        }

        if (!empty($filters['statut'])) {
            $today = new \DateTime();
            $today->setTime(0, 0, 0);
            
            if ($filters['statut'] === 'PRESENT') {
                $qb->leftJoin('e.pointages', 'p')
                    ->andWhere('p.date_heure >= :today')
                    ->andWhere('p.type = :type')
                    ->setParameter('today', $today)
                    ->setParameter('type', 'ENTREE')
                    ->groupBy('e.id')
                    ->having('MAX(p.date_heure) = p.date_heure');
            } elseif ($filters['statut'] === 'ABSENT') {
                // Logique pour absent
            }
        }
    }

    private function countWithFilters(array $filters): int
    {
        $qb = $this->createQueryBuilder('e')
            ->select('COUNT(e.id)');

        $this->applyFilters($qb, $filters);

        return (int)$qb->getQuery()->getSingleScalarResult();
    }

    // ========== MÉTHODES DE RAPPORTS ==========

    /**
     * Rapport mensuel des présences
     */
    public function getRapportMensuel(int $annee, int $mois): array
    {
        $debut = new \DateTime($annee . '-' . str_pad($mois, 2, '0', STR_PAD_LEFT) . '-01');
        $fin = (clone $debut)->modify('last day of this month')->setTime(23, 59, 59);

        return $this->createQueryBuilder('e')
            ->leftJoin('e.pointages', 'p')
            ->select('e.id, e.nom, e.prenom, e.matricule, e.poste')
            ->addSelect('COUNT(p.id) as total_pointages')
            ->addSelect("SUM(CASE WHEN p.type = 'ENTREE' THEN 1 ELSE 0 END) as entrees")
            ->addSelect("SUM(CASE WHEN p.type = 'SORTIE' THEN 1 ELSE 0 END) as sorties")
            ->where('p.date_heure >= :debut')
            ->andWhere('p.date_heure <= :fin')
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('e.id')
            ->orderBy('e.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Export des données employés pour Excel/CSV
     */
    public function findForExport(): array
    {
        return $this->createQueryBuilder('e')
            ->select('e.id, e.nom, e.prenom, e.email, e.matricule, e.poste, e.telephone')
            ->addSelect('e.date_embauche, e.created_at')
            ->addSelect('COUNT(p.id) as total_pointages')
            ->leftJoin('e.pointages', 'p')
            ->groupBy('e.id')
            ->orderBy('e.nom', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    // ========== MÉTHODES DE MAINTENANCE ==========

    /**
     * Nettoie les employés sans pointage (optionnel)
     */
    public function cleanOrphaned(): int
    {
        // Trouver les employés sans pointage depuis plus de X jours
        $dateLimite = new \DateTime('-90 days'); // 3 mois
        
        $qb = $this->createQueryBuilder('e')
            ->leftJoin('e.pointages', 'p')
            ->having('MAX(p.date_heure) < :dateLimite OR COUNT(p.id) = 0')
            ->setParameter('dateLimite', $dateLimite)
            ->groupBy('e.id');

        $employes = $qb->getQuery()->getResult();
        $count = count($employes);

        foreach ($employes as $employe) {
            $this->getEntityManager()->remove($employe);
        }

        if ($count > 0) {
            $this->getEntityManager()->flush();
        }

        return $count;
    }
}