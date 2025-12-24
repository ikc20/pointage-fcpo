<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

$kernel = new App\Kernel($_SERVER['APP_ENV'] ?? 'dev', (bool) ($_SERVER['APP_DEBUG'] ?? false));
$kernel->boot();

$container = $kernel->getContainer();
$em = $container->get('doctrine.orm.entity_manager');

echo "Ajout des données de test...\n";

// Ajouter des employés
$employes = [
    [
        'nom' => 'Dupont',
        'prenom' => 'Jean',
        'email' => 'jean.dupont@entreprise.com',
        'matricule' => 'EMP001',
        'poste' => 'Développeur',
        'telephone' => '0123456789',
        'date_embauche' => '2024-01-15'
    ],
    [
        'nom' => 'Martin',
        'prenom' => 'Marie',
        'email' => 'marie.martin@entreprise.com',
        'matricule' => 'EMP002',
        'poste' => 'Chef de projet',
        'telephone' => '0234567890',
        'date_embauche' => '2023-06-20'
    ],
    [
        'nom' => 'Bernard',
        'prenom' => 'Pierre',
        'email' => 'pierre.bernard@entreprise.com',
        'matricule' => 'EMP003',
        'poste' => 'Commercial',
        'telephone' => '0345678901',
        'date_embauche' => '2024-03-10'
    ]
];

foreach ($employes as $data) {
    $employe = new App\Entity\Employe();
    $employe->setNom($data['nom']);
    $employe->setPrenom($data['prenom']);
    $employe->setEmail($data['email']);
    $employe->setMatricule($data['matricule']);
    $employe->setPoste($data['poste']);
    $employe->setTelephone($data['telephone']);
    $employe->setDateEmbauche(new DateTime($data['date_embauche']));
    
    $em->persist($employe);
    echo "Employé créé: {$data['prenom']} {$data['nom']} ({$data['matricule']})\n";
}

$em->flush();
echo "Données enregistrées avec succès!\n";
