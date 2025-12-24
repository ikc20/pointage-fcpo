<?php
// fix_migration_storage.php

use Doctrine\DBAL\DriverManager;
use Doctrine\Migrations\Configuration\Configuration;
use Doctrine\Migrations\Metadata\Storage\TableMetadataStorageConfiguration;

require __DIR__.'/vendor/autoload.php';

$kernel = new App\Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();

$container = $kernel->getContainer();
$connection = $container->get('doctrine.dbal.default_connection');

echo "Connexion à la base établie...\n";

// Vérifiez la table
$tableExists = $connection->executeQuery("SHOW TABLES LIKE 'doctrine_migration_versions'")->fetchOne();
echo "Table existe: " . ($tableExists ? 'OUI' : 'NON') . "\n";

if ($tableExists) {
    // Affichez le contenu
    $versions = $connection->executeQuery("SELECT * FROM doctrine_migration_versions")->fetchAllAssociative();
    echo "Versions dans la table:\n";
    foreach ($versions as $version) {
        echo "- {$version['version']} (exécuté le: {$version['executed_at']})\n";
    }
    
    // Vérifiez si le fichier existe
    $versionInDb = $versions[0]['version'] ?? '';
    $filename = str_replace('DoctrineMigrations\\', '', $versionInDb);
    $filepath = __DIR__ . '/migrations/' . $filename . '.php';
    
    echo "Cherche fichier: $filepath\n";
    
    if (file_exists($filepath)) {
        echo "Fichier trouvé!\n";
        
        // Forcez la synchronisation
        $configuration = new Configuration($connection);
        $configuration->addMigrationsDirectory('DoctrineMigrations', __DIR__.'/migrations');
        
        $storageConfiguration = new TableMetadataStorageConfiguration();
        $storageConfiguration->setTableName('doctrine_migration_versions');
        $configuration->setMetadataStorageConfiguration($storageConfiguration);
        
        // Initialisez le stockage
        $configuration->getMetadataStorage()->ensureInitialized();
        
        echo "Stockage synchronisé avec succès!\n";
    } else {
        echo "Fichier non trouvé. Deux options:\n";
        echo "1. Supprimer l'entrée de la table\n";
        echo "2. Recréer le fichier de migration\n";
    }
}

echo "Terminé.\n";