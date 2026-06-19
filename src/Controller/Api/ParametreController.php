<?php

namespace App\Controller\Api; 

use App\Entity\Parametre;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/parametres')]
class ParametreController extends AbstractController
{
    #[Route('/', name: 'parametre_index', methods: ['GET'])]
    
    public function index(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $categorie = $request->query->get('categorie');
        $cle = $request->query->get('cle');
        
        $qb = $em->getRepository(Parametre::class)->createQueryBuilder('p')
            ->orderBy('p.categorie', 'ASC')
            ->addOrderBy('p.cle', 'ASC');
        
        if ($categorie) {
            $qb->andWhere('p.categorie = :categorie')
               ->setParameter('categorie', $categorie);
        }
        
        if ($cle) {
            $qb->andWhere('p.cle LIKE :cle')
               ->setParameter('cle', '%' . $cle . '%');
        }
        
        $parametres = $qb->getQuery()->getResult();
        
        $data = [];
        foreach ($parametres as $parametre) {
            $data[] = $this->serializeParametre($parametre);
        }
        
        return $this->json([
            'success' => true,
            'data' => $data,
            'count' => count($data)
        ]);
    }

    #[Route('/{id}', name: 'parametre_show', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function show(Parametre $parametre): JsonResponse
    {
        return $this->json([
            'success' => true,
            'data' => $this->serializeParametre($parametre)
        ]);
    }

    #[Route('/cle/{cle}', name: 'parametre_by_cle', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function getByCle(string $cle, EntityManagerInterface $em): JsonResponse
    {
        $parametre = $em->getRepository(Parametre::class)->findOneBy(['cle' => $cle]);
        
        if (!$parametre) {
            return $this->json([
                'success' => false,
                'error' => 'Paramètre non trouvé'
            ], Response::HTTP_NOT_FOUND);
        }
        
        return $this->json([
            'success' => true,
            'data' => $this->serializeParametre($parametre)
        ]);
    }

    #[Route('/', name: 'parametre_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        ValidatorInterface $validator
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        
        $parametre = new Parametre();
        $parametre->setCle($data['cle'] ?? '');
        $parametre->setValeur($data['valeur'] ?? null);
        $parametre->setDescription($data['description'] ?? null);
        $parametre->setType($data['type'] ?? 'string');
        $parametre->setCategorie($data['categorie'] ?? 'general');
        $parametre->setCreatedAt(new \DateTime());
        
        // Validation
        $errors = $validator->validate($parametre);
        if (count($errors) > 0) {
            return $this->json([
                'success' => false,
                'errors' => (string) $errors
            ], Response::HTTP_BAD_REQUEST);
        }
        
        // Vérifier si la clé existe déjà
        $existing = $em->getRepository(Parametre::class)->findOneBy(['cle' => $parametre->getCle()]);
        if ($existing) {
            return $this->json([
                'success' => false,
                'error' => 'Un paramètre avec cette clé existe déjà'
            ], Response::HTTP_BAD_REQUEST);
        }
        
        $em->persist($parametre);
        $em->flush();
        
        return $this->json([
            'success' => true,
            'message' => 'Paramètre créé avec succès',
            'data' => $this->serializeParametre($parametre)
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'parametre_update', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN')]
    public function update(
        Request $request,
        Parametre $parametre,
        EntityManagerInterface $em,
        ValidatorInterface $validator
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        
        // Ne pas permettre la modification de la clé
        if (isset($data['cle']) && $data['cle'] !== $parametre->getCle()) {
            return $this->json([
                'success' => false,
                'error' => 'La clé du paramètre ne peut pas être modifiée'
            ], Response::HTTP_BAD_REQUEST);
        }
        
        if (isset($data['valeur'])) $parametre->setValeur($data['valeur']);
        if (isset($data['description'])) $parametre->setDescription($data['description']);
        if (isset($data['type'])) $parametre->setType($data['type']);
        if (isset($data['categorie'])) $parametre->setCategorie($data['categorie']);
        
        $parametre->setUpdatedAt(new \DateTime());
        
        // Validation
        $errors = $validator->validate($parametre);
        if (count($errors) > 0) {
            return $this->json([
                'success' => false,
                'errors' => (string) $errors
            ], Response::HTTP_BAD_REQUEST);
        }
        
        $em->flush();
        
        return $this->json([
            'success' => true,
            'message' => 'Paramètre mis à jour avec succès',
            'data' => $this->serializeParametre($parametre)
        ]);
    }

    #[Route('/{id}', name: 'parametre_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Parametre $parametre, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($parametre);
        $em->flush();
        
        return $this->json([
            'success' => true,
            'message' => 'Paramètre supprimé avec succès'
        ]);
    }

    #[Route('/categories', name: 'parametre_categories', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function getCategories(EntityManagerInterface $em): JsonResponse
    {
        $categories = $em->getRepository(Parametre::class)->createQueryBuilder('p')
            ->select('DISTINCT p.categorie')
            ->orderBy('p.categorie', 'ASC')
            ->getQuery()
            ->getResult();
        
        $categoryList = array_column($categories, 'categorie');
        
        return $this->json([
            'success' => true,
            'data' => $categoryList,
            'count' => count($categoryList)
        ]);
    }

    #[Route('/batch/update', name: 'parametre_batch_update', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function batchUpdate(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!is_array($data)) {
            return $this->json([
                'success' => false,
                'error' => 'Données invalides'
            ], Response::HTTP_BAD_REQUEST);
        }
        
        $updated = 0;
        $errors = [];
        
        foreach ($data as $cle => $valeur) {
            $parametre = $em->getRepository(Parametre::class)->findOneBy(['cle' => $cle]);
            
            if ($parametre) {
                $parametre->setValeur($valeur);
                $parametre->setUpdatedAt(new \DateTime());
                $updated++;
            } else {
                $errors[] = "Paramètre '$cle' non trouvé";
            }
        }
        
        $em->flush();
        
        $response = [
            'success' => true,
            'message' => "$updated paramètre(s) mis à jour",
            'updated' => $updated
        ];
        
        if (!empty($errors)) {
            $response['errors'] = $errors;
        }
        
        return $this->json($response);
    }

    private function serializeParametre(Parametre $parametre): array
    {
        // Convertir la valeur selon le type
        $valeur = $parametre->getValeur();
        $type = $parametre->getType();
        
        if ($valeur !== null) {
            switch ($type) {
                case 'integer':
                case 'int':
                    $valeur = (int) $valeur;
                    break;
                case 'float':
                case 'double':
                    $valeur = (float) $valeur;
                    break;
                case 'boolean':
                case 'bool':
                    $valeur = filter_var($valeur, FILTER_VALIDATE_BOOLEAN);
                    break;
                case 'json':
                case 'array':
                    $valeur = json_decode($valeur, true);
                    break;
                default:
                    // string - laisser tel quel
                    break;
            }
        }
        
        return [
            'id' => $parametre->getId(),
            'cle' => $parametre->getCle(),
            'valeur' => $valeur,
            'description' => $parametre->getDescription(),
            'type' => $parametre->getType(),
            'categorie' => $parametre->getCategorie(),
            'created_at' => $parametre->getCreatedAt() ? $parametre->getCreatedAt()->format('Y-m-d H:i:s') : null,
            'updated_at' => $parametre->getUpdatedAt() ? $parametre->getUpdatedAt()->format('Y-m-d H:i:s') : null
        ];
    }
}