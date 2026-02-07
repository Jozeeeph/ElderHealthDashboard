<?php

namespace App\Entity;

use App\Repository\TypeRendezVousRepository;
use BcMath\Number;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Utilisateur;

#[ORM\Entity(repositoryClass: TypeRendezVousRepository::class)]
class TypeRendezVous
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

   

    #[ORM\Column(length: 200)]
    private ?string $type = null;

    #[ORM\Column]
    private ?float $Tarif = null;

    
    #[ORM\Column(length: 200, nullable: true)]
    private ?string $duree = null;
    #[ORM\ManyToOne]
    private ?Utilisateur $admin = null;
    public function getId(): ?int
    {
        return $this->id;
    }

    
    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getTarif(): ?float
    {
        return $this->Tarif;
    }

    public function setTarif(float $Tarif): static
    {
        $this->Tarif = $Tarif;

        return $this;
    }

    public function getDuree(): ?string
    {
        return $this->duree;
    }

    public function setDuree(?string $duree): static
    {
        $this->duree = $duree;

        return $this;
    }
    public function getAdmin(): ?Utilisateur
{
    return $this->admin;
}


public function setAdmin(?Utilisateur $admin): self
{
    $this->admin = $admin;
    return $this;
}
}
