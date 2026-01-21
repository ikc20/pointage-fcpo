<?php

namespace App\Entity;

use App\Repository\FaceEncodingRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;

#[ORM\Entity(repositoryClass: FaceEncodingRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'face_encodings')]
class FaceEncoding
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'faceEncoding')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Employe $employe = null;

    #[ORM\Column(type: 'text')]
    private string $encoding;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
private \DateTimeInterface $createdAt;

#[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
private ?\DateTimeInterface $updatedAt = null;
    
public function __construct()
{
    $this->createdAt = new \DateTime();
}

#[ORM\PreUpdate]
public function onPreUpdate(): void
{
    $this->updatedAt = new \DateTime();
}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmploye(): ?Employe
    {
        return $this->employe;
    }

    public function setEmploye(Employe $employe): self
    {
        $this->employe = $employe;
        return $this;
    }

    public function getEncoding(): string
    {
        return $this->encoding;
    }

    public function setEncoding(string $encoding): self
    {
        $this->encoding = $encoding;
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
    return $this->createdAt;
     }

    public function getUpdatedAt(): ?\DateTimeInterface
     {
    return $this->updatedAt;
     }

}
