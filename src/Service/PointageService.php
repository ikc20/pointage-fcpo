<?php

namespace App\Service;

use App\Entity\Pointage;
use App\Entity\Employe;
use Doctrine\ORM\EntityManagerInterface;

class PointageService
{
    public function __construct(private EntityManagerInterface $em) {}
    
    public function canPointage(Employe $employe, string $type): bool
    {
        // Vérifie si l'employé peut pointer (pas de doublon, horaires valides, etc.)
        // Retourne false si dernière entrée < 5 min pour éviter les doublons
        // Vérifie les horaires de travail
    }
    
    public function calculateHeuresTravaillees(int $employeId, \DateTime $date): array
    {
        // Calcule les heures travaillées pour une date
        // Retourne ['heures' => 8.5, 'supplementaires' => 1.5]
    }
    
    public function getStatutPointage(Employe $employe): string
    {
        // "DEJA_POINTE_ENTREE", "DEJA_POINTE_SORTIE", "ABSENT", "PRET_A_POINTER"
        $dernier = $this->getDernierPointage($employe);
        
        if (!$dernier) {
            return 'ABSENT'; // Pas de pointage aujourd'hui
        }
        
        return $dernier->getType() === 'ENTREE' 
            ? 'DEJA_POINTE_ENTREE' 
            : 'DEJA_POINTE_SORTIE';
    }
}