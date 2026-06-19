<?php

namespace App\Entity;

use App\Repository\PlanningRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlanningRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Planning
{
    public const TYPE_NORMAL = 'NORMAL';
    public const TYPE_RAMADAN = 'RAMADAN';
    public const TYPE_HEURES_SUPP = 'HEURES_SUPP';
    public const TYPE_JOUR_FERIE = 'JOUR_FERIE';
    public const TYPE_TELETRAVAIL = 'TELETRAVAIL';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $type = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nom = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $date_debut = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $date_fin = null;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    private ?\DateTimeInterface $heure_debut = null;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    private ?\DateTimeInterface $heure_fin = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $pause_debut = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $pause_fin = null;

    #[ORM\Column]
    private ?bool $pause_obligatoire = false;

    #[ORM\Column(nullable: true)]
    private ?int $duree_pause_min = 60; 

    #[ORM\Column]
    private ?bool $actif = true;

    #[ORM\Column]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeInterface $updated_at = null;

    #[ORM\PrePersist]
    public function onCreate(): void
    {
        $this->created_at = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function onUpdate(): void
    {
        $this->updated_at = new \DateTime();
    }


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): self
    {
        $this->nom = $nom;
        return $this;
    }

    public function getDateDebut(): ?\DateTimeInterface
    {
        return $this->date_debut;
    }

    public function setDateDebut(?\DateTimeInterface $date_debut): self
    {
        $this->date_debut = $date_debut;
        return $this;
    }

    public function getDateFin(): ?\DateTimeInterface
    {
        return $this->date_fin;
    }

    public function setDateFin(?\DateTimeInterface $date_fin): self
    {
        $this->date_fin = $date_fin;
        return $this;
    }

    public function getHeureDebut(): ?\DateTimeInterface
    {
        return $this->heure_debut;
    }

    public function setHeureDebut(\DateTimeInterface $heure_debut): self
    {
        $this->heure_debut = $heure_debut;
        return $this;
    }

    public function getHeureFin(): ?\DateTimeInterface
    {
        return $this->heure_fin;
    }

    public function setHeureFin(\DateTimeInterface $heure_fin): self
    {
        $this->heure_fin = $heure_fin;
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

    public function isPauseObligatoire(): ?bool
    {
        return $this->pause_obligatoire;
    }

    public function setPauseObligatoire(bool $pause_obligatoire): self
    {
        $this->pause_obligatoire = $pause_obligatoire;
        return $this;
    }

    public function getDureePauseMin(): ?int
    {
        return $this->duree_pause_min;
    }

    public function setDureePauseMin(?int $duree_pause_min): self
    {
        $this->duree_pause_min = $duree_pause_min;
        return $this;
    }

    public function isActif(): ?bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): self
    {
        $this->actif = $actif;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->created_at;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updated_at;
    }


    public function getTypeLabel(): string
    {
        return match($this->type) {
            self::TYPE_NORMAL => 'Normal',
            self::TYPE_RAMADAN => 'Ramadan',
            self::TYPE_HEURES_SUPP => 'Heures supplémentaires',
            self::TYPE_JOUR_FERIE => 'Jour férié',
            self::TYPE_TELETRAVAIL => 'Télétravail',
            default => $this->type ?? 'Inconnu'
        };
    }

    public function estDansPeriode(\DateTimeInterface $date): bool
    {
        if (!$this->date_debut) {
            return true;
        }

        if ($date < $this->date_debut) {
            return false;
        }

        if ($this->date_fin && $date > $this->date_fin) {
            return false;
        }

        return true;
    }

    public function getDureeJournee(): int
    {
        if (!$this->heure_debut || !$this->heure_fin) {
            return 0;
        }

        $debut = (clone $this->heure_debut);
        $fin = (clone $this->heure_fin);

        if ($fin < $debut) {
            $fin->modify('+1 day');
        }

        $duree = $fin->getTimestamp() - $debut->getTimestamp();

        if ($this->pause_debut && $this->pause_fin) {
            $pause_debut = (clone $this->pause_debut);
            $pause_fin = (clone $this->pause_fin);
            if ($pause_fin < $pause_debut) {
                $pause_fin->modify('+1 day');
            }
            $duree -= ($pause_fin->getTimestamp() - $pause_debut->getTimestamp());
        }

        return (int)($duree / 3600); 
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'type_label' => $this->getTypeLabel(),
            'nom' => $this->nom,
            'date_debut' => $this->date_debut?->format('Y-m-d'),
            'date_fin' => $this->date_fin?->format('Y-m-d'),
            'heure_debut' => $this->heure_debut?->format('H:i'),
            'heure_fin' => $this->heure_fin?->format('H:i'),
            'pause_debut' => $this->pause_debut?->format('H:i'),
            'pause_fin' => $this->pause_fin?->format('H:i'),
            'pause_obligatoire' => $this->pause_obligatoire,
            'duree_pause_min' => $this->duree_pause_min,
            'duree_journee' => $this->getDureeJournee(),
            'actif' => $this->actif,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}