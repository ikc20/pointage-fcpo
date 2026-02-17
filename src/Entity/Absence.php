<?php

namespace App\Entity;

use App\Repository\AbsenceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AbsenceRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Absence
{
    public const STATUT_EN_ATTENTE = 'EN_ATTENTE';
    public const STATUT_VALIDE = 'VALIDÉ';
    public const STATUT_REJETE = 'REJETÉ';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private string $type = 'CONGE';

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private \DateTime $date_debut;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private \DateTime $date_fin;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $motif = null;

    #[ORM\Column(length: 20)]
    private string $statut = self::STATUT_EN_ATTENTE;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $justificatif = null;

    #[ORM\Column]
    private \DateTime $created_at;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $updated_at = null;

    #[ORM\ManyToOne(inversedBy: 'absences')]
    #[ORM\JoinColumn(nullable: false)]
    private Employe $employe;

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

    public function getType(): string 
    { 
        return $this->type;
         }
    public function setType(string $type): self
     {
         $this->type = $type;
          return $this;
           }

    public function getDateDebut(): \DateTime 
    { 
        return $this->date_debut; 
        }
    public function setDateDebut(\DateTime $date): self 
    { 
        $this->date_debut = $date; 
        return $this; 
        }

    public function getDateFin(): \DateTime 
    {
         return $this->date_fin;
          }
    public function setDateFin(\DateTime $date): self
     {
         $this->date_fin = $date; return $this;
          }

    public function getMotif(): ?string
     {
         return $this->motif;
          }
    public function setMotif(?string $motif): self
     { 
        $this->motif = $motif;
         return $this;
          }

    public function getStatut(): string 
    { 
        return $this->statut;
     }
    public function setStatut(string $statut): self
     {
         $this->statut = $statut; 
         return $this;
          }

    public function getJustificatif(): ?string
     {
         return $this->justificatif; 
         }
    public function setJustificatif(?string $justificatif): self
     { 
        $this->justificatif = $justificatif; 
        return $this;
         }

    public function getEmploye(): Employe 
    { 
        return $this->employe;
         }
    public function setEmploye(Employe $employe): self
     { 
        $this->employe = $employe; return $this;
         }
}
