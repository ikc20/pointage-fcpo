<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

$kernel = new App\Kernel($_SERVER['APP_ENV'] ?? 'dev', (bool) ($_SERVER['APP_DEBUG'] ?? false));
$kernel->boot();

$container = $kernel->getContainer();
$em = $container->get('doctrine.orm.entity_manager');

echo "Ajout des pointages de test...\n";

// Récupérer les employés
$employes = $em->getRepository(App\Entity\Employe::class)->findAll();

if (empty($employes)) {
    echo "Aucun employé trouvé. Créez d'abord des employés.\n";
    exit(1);
}

// Ajouter des pointages pour les derniers 5 jours
for ($i = 0; $i < 5; $i++) {
    $date = new DateTime("-$i days");
    
    foreach ($employes as $employe) {
        // Pointage d'entrée (8h-9h aléatoire)
        $entree = clone $date;
        $entree->setTime(rand(8, 9), rand(0, 59));
        
        $pointageEntree = new App\Entity\Pointage();
        $pointageEntree->setEmploye($employe);
        $pointageEntree->setDateHeure($entree);
        $pointageEntree->setType('ENTREE');
        $pointageEntree->setConfidence(0.95);
        
        $em->persist($pointageEntree);
        
        // Pointage de sortie (17h-18h aléatoire)
        $sortie = clone $date;
        $sortie->setTime(rand(17, 18), rand(0, 59));
        
        $pointageSortie = new App\Entity\Pointage();
        $pointageSortie->setEmploye($employe);
        $pointageSortie->setDateHeure($sortie);
        $pointageSortie->setType('SORTIE');
        $pointageSortie->setConfidence(0.92);
        
        $em->persist($pointageSortie);
        
        echo "Pointages ajoutés pour {$employe->getNomComplet()} le {$date->format('Y-m-d')}\n";
    }
}

$em->flush();
echo "Pointages enregistrés avec succès!\n";
