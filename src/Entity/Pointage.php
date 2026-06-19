<?php
namespace App\Entity;

use App\Repository\PointageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PointageRepository::class)]
class Pointage
{
    public const TYPE_ENTREE = 'ENTREE';
    public const TYPE_SORTIE = 'SORTIE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $date_heure = null;

    #[ORM\Column(length: 10)]
    private ?string $type = null;

    #[ORM\Column(nullable: true)]
    private ?float $confidence = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $distance = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ip_address = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $device_info = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $photo_capture = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $methode = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 8, nullable: true)]
    private ?string $latitude = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 11, scale: 8, nullable: true)]
    private ?string $longitude = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\ManyToOne(targetEntity: Employe::class, inversedBy: 'pointages')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Employe $employe = null;


    #[ORM\Column(nullable: true)]
    private ?bool $estHeureSupp = null;

    #[ORM\ManyToOne(targetEntity: Planning::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Planning $planning = null;

    #[ORM\Column(nullable: true)]
    private ?bool $estEnPause = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeInterface $pause_debut = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeInterface $pause_fin = null;

    public function __construct()
    {
        $this->date_heure = new \DateTime();
        $this->created_at = new \DateTime();
    }


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDateHeure(): ?\DateTimeInterface
    {
        return $this->date_heure;
    }

    public function setDateHeure(\DateTimeInterface $date_heure): static
    {
        $this->date_heure = $date_heure;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        if (!in_array($type, [self::TYPE_ENTREE, self::TYPE_SORTIE])) {
            throw new \InvalidArgumentException("Le type doit être 'ENTREE' ou 'SORTIE'");
        }
        $this->type = $type;
        return $this;
    }

    public function getConfidence(): ?float
    {
        return $this->confidence;
    }

    public function setConfidence(?float $confidence): static
    {
        $this->confidence = $confidence;
        return $this;
    }

    public function getDistance(): ?float
    {
        return $this->distance;
    }

    public function setDistance(?float $distance): self
    {
        $this->distance = $distance;
        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ip_address;
    }

    public function setIpAddress(?string $ip_address): static
    {
        $this->ip_address = $ip_address;
        return $this;
    }

    public function getDeviceInfo(): ?string
    {
        return $this->device_info;
    }

    public function setDeviceInfo(?string $device_info): static
    {
        $this->device_info = $device_info;
        return $this;
    }

    public function getPhotoCapture(): ?string
    {
        return $this->photo_capture;
    }

    public function setPhotoCapture(?string $photo_capture): static
    {
        $this->photo_capture = $photo_capture;
        return $this;
    }

    public function getMethode(): ?string
    {
        return $this->methode;
    }

    public function setMethode(?string $methode): self
    {
        $this->methode = $methode;
        return $this;
    }

    public function getLatitude(): ?string
    {
        return $this->latitude;
    }

    public function setLatitude(?string $latitude): static
    {
        $this->latitude = $latitude;
        return $this;
    }

    public function getLongitude(): ?string
    {
        return $this->longitude;
    }

    public function setLongitude(?string $longitude): static
    {
        $this->longitude = $longitude;
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

    public function getEmploye(): ?Employe
    {
        return $this->employe;
    }

    public function setEmploye(?Employe $employe): static
    {
        $this->employe = $employe;
        return $this;
    }


    public function getEstHeureSupp(): ?bool
    {
        return $this->estHeureSupp;
    }

    public function setEstHeureSupp(?bool $estHeureSupp): self
    {
        $this->estHeureSupp = $estHeureSupp;
        return $this;
    }

    public function getPlanning(): ?Planning
    {
        return $this->planning;
    }

    public function setPlanning(?Planning $planning): self
    {
        $this->planning = $planning;
        return $this;
    }

    public function getEstEnPause(): ?bool
    {
        return $this->estEnPause;
    }

    public function setEstEnPause(?bool $estEnPause): self
    {
        $this->estEnPause = $estEnPause;
        return $this;
    }

    public function getPauseDebut(): ?\DateTimeInterface
    {
        return $this->pause_debut;
    }

    public function setPauseDebut(?\DateTimeInterface $pause_debut): self
    {
        $this->pause_debut = $pause_debut;
        return $this;
    }

    public function getPauseFin(): ?\DateTimeInterface
    {
        return $this->pause_fin;
    }

    public function setPauseFin(?\DateTimeInterface $pause_fin): self
    {
        $this->pause_fin = $pause_fin;
        return $this;
    }


    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'date_heure' => $this->date_heure ? $this->date_heure->format('Y-m-d H:i:s') : null,
            'date' => $this->date_heure ? $this->date_heure->format('Y-m-d') : null,
            'heure' => $this->date_heure ? $this->date_heure->format('H:i:s') : null,
            'type' => $this->type,
            'type_libelle' => $this->getTypeLibelle(),
            'confidence' => $this->confidence,
            'confidence_pourcentage' => $this->confidence ? round($this->confidence * 100, 1) : null,
            'distance' => $this->distance,
            'ip_address' => $this->ip_address,
            'device_info' => $this->device_info,
            'photo_capture' => $this->photo_capture,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'employe_id' => $this->employe ? $this->employe->getId() : null,
            'employe_nom_complet' => $this->employe ? $this->employe->getNomComplet() : null,
            'employe_matricule' => $this->employe ? $this->employe->getMatricule() : null,
            'employe_poste' => $this->employe ? $this->employe->getPoste() : null,
            'est_heure_supp' => $this->estHeureSupp,
            'planning_id' => $this->planning?->getId(),
            'planning_type' => $this->planning?->getType(),
            'planning_label' => $this->planning?->getTypeLabel(),
            'est_en_pause' => $this->estEnPause,
            'pause_debut' => $this->pause_debut?->format('H:i'),
            'pause_fin' => $this->pause_fin?->format('H:i'),
        ];
    }

    public function getTypeLibelle(): string
    {
        return $this->type === self::TYPE_ENTREE ? 'Entrée' : 'Sortie';
    }

    public function getJourSemaine(): string
    {
        if (!$this->date_heure) {
            return '';
        }
        
        $jours = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
        return $jours[(int)$this->date_heure->format('w')];
    }

    public function estAujourdhui(): bool
    {
        if (!$this->date_heure) {
            return false;
        }
        
        $aujourdhui = new \DateTime();
        return $this->date_heure->format('Y-m-d') === $aujourdhui->format('Y-m-d');
    }
}