<?php

namespace App\Entity;

use App\Repository\EmployeRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: EmployeRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Employe
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column(length: 255)]
    private ?string $prenom = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $email = null;

    #[ORM\Column(length: 50, unique: true)]
    private ?string $matricule = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $qr_code = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $date_embauche = null;

    #[ORM\Column(length: 255)]
    private ?string $poste = null;

    #[ORM\Column(length: 20)]
    private ?string $telephone = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $updated_at = null;

    #[ORM\OneToOne(inversedBy: 'employe')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\OneToMany(mappedBy: 'employe', targetEntity: Pointage::class, cascade: ['remove'])]
    private Collection $pointages;

    // ✅ Relation FaceEncoding - Côté inverse
    #[ORM\OneToOne(mappedBy: 'employe', targetEntity: FaceEncoding::class, cascade: ['persist', 'remove'])]
    private ?FaceEncoding $faceEncoding = null;

    public function __construct()
    {
        $this->created_at = new \DateTime();
        $this->date_embauche = new \DateTime();
        $this->matricule = 'EMP' . strtoupper(substr(uniqid(), -6));
        $this->qr_code = 'QR_' . strtoupper(substr(uniqid(), -8));
        $this->pointages = new ArrayCollection();
    }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updated_at = new \DateTime();
    }

    // =============================================
    // GETTERS & SETTERS
    // =============================================
    public function getId(): ?int { return $this->id; }

    public function getNom(): ?string { return $this->nom; }
    public function setNom(string $nom): static { $this->nom = $nom; return $this; }

    public function getPrenom(): ?string { return $this->prenom; }
    public function setPrenom(string $prenom): static { $this->prenom = $prenom; return $this; }

    public function getNomComplet(): string
    {
        return trim($this->prenom . ' ' . $this->nom);
    }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(string $email): static
    {
        $this->email = strtolower($email);
        return $this;
    }

    public function getMatricule(): ?string { return $this->matricule; }
    public function setMatricule(string $matricule): static
    {
        $this->matricule = $matricule;
        return $this;
    }

    public function getQrCode(): ?string { return $this->qr_code; }
    public function setQrCode(string $qr_code): static
    {
        $this->qr_code = $qr_code;
        return $this;
    }

    public function getDateEmbauche(): ?\DateTimeInterface { return $this->date_embauche; }
    public function setDateEmbauche(\DateTimeInterface $date): static
    {
        $this->date_embauche = $date;
        return $this;
    }

    public function getPoste(): ?string { return $this->poste; }
    public function setPoste(string $poste): static { $this->poste = $poste; return $this; }

    public function getTelephone(): ?string { return $this->telephone; }
    public function setTelephone(string $telephone): static { $this->telephone = $telephone; return $this; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getPointages(): Collection
    {
        return $this->pointages;
    }

    // ✅ GETTER/SETTER FACEEENCODING AVEC SYNCHRONISATION
    public function getFaceEncoding(): ?FaceEncoding
    {
        return $this->faceEncoding;
    }

    public function setFaceEncoding(?FaceEncoding $faceEncoding): self
    {
        $this->faceEncoding = $faceEncoding;

        // 🔥 Synchronisation automatique de l'autre côté
        if ($faceEncoding && $faceEncoding->getEmploye() !== $this) {
            $faceEncoding->setEmploye($this);
        }

        return $this;
    }

    // =============================================
    // MÉTIER - POINTAGES
    // =============================================
    public function getDernierPointage(): ?Pointage
    {
        $array = $this->pointages->toArray();
        if (!$array) return null;

        usort($array, fn($a, $b) =>
            $b->getDateHeure() <=> $a->getDateHeure()
        );

        return $array[0];
    }

    public function getHistoriquePointagesParDate(): array
    {
        $historique = [];

        foreach ($this->pointages as $pointage) {
            $date = $pointage->getDateHeure()->format('Y-m-d');

            if (!isset($historique[$date])) {
                $historique[$date] = [
                    'date' => $date,
                    'pointages' => []
                ];
            }

            $historique[$date]['pointages'][] = $pointage->toArray();
        }

        krsort($historique);
        return array_values($historique);
    }

    public function getStatut(): string
    {
        $dernier = $this->getDernierPointage();

        if (!$dernier) return 'INCONNU';

        $today = (new \DateTime())->format('Y-m-d');

        if (
            $dernier->getDateHeure()->format('Y-m-d') === $today &&
            $dernier->getType() === 'ENTREE'
        ) {
            return 'PRESENT';
        }

        return 'ABSENT';
    }

    // =============================================
    // SÉRIALISATION
    // =============================================
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'prenom' => $this->prenom,
            'nom_complet' => $this->getNomComplet(),
            'email' => $this->email,
            'matricule' => $this->matricule,
            'poste' => $this->poste,
            'telephone' => $this->telephone,
            'qr_code' => $this->qr_code,
            'date_embauche' => $this->date_embauche?->format('Y-m-d'),
            'statut' => $this->getStatut(),
            'dernier_pointage' => $this->getDernierPointage()?->toArray(),
            'has_face_encoding' => $this->faceEncoding !== null,
        ];
    }
}