#!/bin/bash

# Créer les contrôleurs de base

# EmployeController
cat > src/Controller/EmployeController.php << 'EOF'
<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/employes')]
class EmployeController extends AbstractController
{
    #[Route('/', name: 'employe_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return $this->json([
            'message' => 'EmployeController - À compléter'
        ]);
    }
}
EOF

# PointageController (existe déjà)
if [ ! -f "src/Controller/PointageController.php" ]; then
    cat > src/Controller/PointageController.php << 'EOF'
<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/pointages')]
class PointageController extends AbstractController
{
    #[Route('/', name: 'pointage_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return $this->json([
            'message' => 'PointageController - À compléter'
        ]);
    }
}
EOF
fi

# AbsenceController
cat > src/Controller/AbsenceController.php << 'EOF'
<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/absences')]
class AbsenceController extends AbstractController
{
    #[Route('/', name: 'absence_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return $this->json([
            'message' => 'AbsenceController - À compléter'
        ]);
    }
}
EOF

# UserController
cat > src/Controller/UserController.php << 'EOF'
<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/users')]
class UserController extends AbstractController
{
    #[Route('/', name: 'user_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return $this->json([
            'message' => 'UserController - À compléter'
        ]);
    }
}
EOF

# ParametreController
cat > src/Controller/ParametreController.php << 'EOF'
<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/parametres')]
class ParametreController extends AbstractController
{
    #[Route('/', name: 'parametre_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return $this->json([
            'message' => 'ParametreController - À compléter'
        ]);
    }
}
EOF

# DashboardController
cat > src/Controller/DashboardController.php << 'EOF'
<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/dashboard')]
class DashboardController extends AbstractController
{
    #[Route('/statistiques', name: 'dashboard_statistiques', methods: ['GET'])]
    public function statistiques(): JsonResponse
    {
        return $this->json([
            'message' => 'DashboardController - À compléter'
        ]);
    }
}
EOF

echo "Contrôleurs créés avec succès !"