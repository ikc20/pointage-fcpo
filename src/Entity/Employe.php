<?php
 
namespace App\Entity;

use App\Repository\EmployeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

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

   #[ORM\OneToOne(mappedBy: 'employe', targetEntity: FaceEncoding::class, cascade: ['persist', 'remove'])]
    private ?FaceEncoding $faceEncoding = null;

    #[ORM\Column(length: 50, unique: true)]
    private ?string $matricule = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $photo = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $descripteurs_visage = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $qr_code = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $date_embauche = null;

    #[ORM\Column(length: 255)]
    private ?string $poste = null;

    #[ORM\Column(length: 20)]
    private ?string $telephone = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updated_at = null;

    /**
     * @var Collection<int, Pointage>
     */
    #[ORM\OneToMany(mappedBy: 'employe', targetEntity: Pointage::class, cascade: ['persist', 'remove'])]
    private Collection $pointages;

    /**
     * @var Collection<int, Absence>
     */
    #[ORM\OneToMany(targetEntity: Absence::class, mappedBy: 'employe', orphanRemoval: true)]
    
    private Collection $absences;

    public function __construct()
    {
        $this->pointages = new ArrayCollection();
        $this->created_at = new \DateTime();
        
        if (empty($this->matricule)) {
            $this->matricule = 'EMP' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        }
        
        if (empty($this->qr_code)) {
            $this->qr_code = 'QR_' . uniqid('', true);
        }
        $this->absences = new ArrayCollection();
    }



    #[ORM\OneToOne(inversedBy: 'employe')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updated_at = new \DateTime();
    }


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

   public function getFaceEncoding(): ?FaceEncoding
    {
    return $this->faceEncoding;
    } 

public function setFaceEncoding(?FaceEncoding $faceEncoding): self
    { 
    $this->faceEncoding = $faceEncoding;

    if ($faceEncoding && $faceEncoding->getEmploye() !== $this) {
        $faceEncoding->setEmploye($this);
    }

    return $this;
     }
 
   public function getUser(): ?User
    {
    return $this->user;
    }

public function setUser(?User $user): static
   {
    $this->user = $user;

    if ($user && $user->getEmploye() !== $this) {
        $user->setEmploye($this);
    }
 

    return $this;
    }


    public function getMatricule(): ?string
    {
        return $this->matricule;
    }

    public function setMatricule(string $matricule): static
    {
        $this->matricule = $matricule;

        return $this;
    }

    public function getPhoto(): ?string
    {
        return $this->photo;
    }

    public function setPhoto(?string $photo): static
    {
        $this->photo = $photo;

        return $this;
    }

    public function getDescripteursVisage(): ?string
    {
        return $this->descripteurs_visage;
    }

    public function setDescripteursVisage(?string $descripteurs_visage): static
    {
        $this->descripteurs_visage = $descripteurs_visage;

        return $this;
    }

    public function getQrCode(): ?string
    {
        return $this->qr_code;
    }

    public function setQrCode(string $qr_code): static
    {
        $this->qr_code = $qr_code;

        return $this;
    }

    public function getDateEmbauche(): ?\DateTimeInterface
    {
        return $this->date_embauche;
    }

    public function setDateEmbauche(\DateTimeInterface $date_embauche): static
    {
        $this->date_embauche = $date_embauche;

        return $this;
    }

    public function getPoste(): ?string
    {
        return $this->poste;
    }

    public function setPoste(string $poste): static
    {
        $this->poste = $poste;

        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(string $telephone): static
    {
        $this->telephone = $telephone;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTimeInterface $created_at): static
    {
        $this->created_at = $created_at;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(?\DateTimeInterface $updated_at): static
    {
        $this->updated_at = $updated_at;

        return $this;
    }


    /**
     * @return Collection<int, Pointage>
     */
    public function getPointages(): Collection
    {
        return $this->pointages;
    }

    public function addPointage(Pointage $pointage): static
    {
        if (!$this->pointages->contains($pointage)) {
            $this->pointages->add($pointage);
            $pointage->setEmploye($this);
        }

        return $this;
    }

    public function removePointage(Pointage $pointage): static
    {
        if ($this->pointages->removeElement($pointage)) {
            if ($pointage->getEmploye() === $this) {
                $pointage->setEmploye(null);
            }
        }

        return $this;
    }


    public function getNomComplet(): string
    {
        return $this->prenom . ' ' . $this->nom;
    }

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
            'photo' => $this->photo,
            'date_embauche' => $this->date_embauche ? $this->date_embauche->format('Y-m-d') : null,
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,
            'pointages_count' => $this->pointages->count(),
            'statut' => $this->getStatut(),
            'dernier_pointage' => $this->getDernierPointage() ? $this->getDernierPointage()->toArray() : null,
        ];
    }

    public function getDernierPointage(): ?Pointage
    {
        $pointages = $this->pointages->toArray();
        
        if (empty($pointages)) {
            return null;
        }
        
        // Trier par date_heure décroissante
        usort($pointages, function($a, $b) {
            return $b->getDateHeure() <=> $a->getDateHeure();
        });
        
        return $pointages[0];
    }

    public function getStatut(): string
    {
        $dernierPointage = $this->getDernierPointage();
        
        if (!$dernierPointage) {
            return 'INCONNU';
        }
        
        $maintenant = new \DateTime();
        $datePointage = $dernierPointage->getDateHeure();
        
        if ($datePointage->format('Y-m-d') === $maintenant->format('Y-m-d') && 
            $dernierPointage->getType() === 'ENTREE') {
            return 'PRESENT';
        }
        
        return 'ABSENT';
    }

    public function getHistoriquePointagesParDate(): array
    {
        $historique = [];
        
        foreach ($this->pointages as $pointage) {
            $date = $pointage->getDateHeure()->format('Y-m-d');
            
            if (!isset($historique[$date])) {
                $historique[$date] = [
                    'date' => $date,
                    'entree' => null,
                    'sortie_dejeuner' => null,
                    'entree_apres_midi' => null,
                    'sortie' => null,
                    'pointages' => []
                ];
            }
            
            $historique[$date]['pointages'][] = $pointage->toArray();
        }
        
        krsort($historique);
        
        return array_values($historique);
    }

    /**
     * @return Collection<int, Absence>
     */
    public function getAbsences(): Collection
    {
        return $this->absences;
    }

    public function addAbsence(Absence $absence): static
    {
        if (!$this->absences->contains($absence)) {
            $this->absences->add($absence);
            $absence->setEmploye($this);
        }

        return $this;
    }

    public function removeAbsence(Absence $absence): static
{
    if ($this->absences->removeElement($absence)) {
    }
    return $this;
}

}