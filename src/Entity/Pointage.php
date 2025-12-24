<?php
// src/Entity/Pointage.php
namespace App\Entity;

use App\Repository\PointageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PointageRepository::class)]
class Pointage
{
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

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ip_address = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $device_info = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $photo_capture = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 8, nullable: true)]
    private ?string $latitude = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 11, scale: 8, nullable: true)]
    private ?string $longitude = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\ManyToOne(targetEntity: Employe::class, inversedBy: 'pointages')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Employe $employe = null;

    public function __construct()
    {
        $this->date_heure = new \DateTime();
        $this->created_at = new \DateTime();
    }

    // ========== GETTERS ET SETTERS ==========

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
        if (!in_array($type, ['ENTREE', 'SORTIE'])) {
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

    // ========== RELATION AVEC EMPLOYE ==========

    public function getEmploye(): ?Employe
    {
        return $this->employe;
    }

    public function setEmploye(?Employe $employe): static
    {
        $this->employe = $employe;

        return $this;
    }

    // ========== MÉTHODES UTILITAIRES ==========

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
        ];
    }

    public function getTypeLibelle(): string
    {
        return $this->type === 'ENTREE' ? 'Entrée' : 'Sortie';
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