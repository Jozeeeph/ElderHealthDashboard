<?php

namespace App\Entity;

use App\Repository\TypeRendezVousRepository;
use BcMath\Number;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Utilisateur;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TypeRendezVousRepository::class)]
class TypeRendezVous
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

   

    #[ORM\Column(length: 200)]
    #[Assert\NotBlank(message: 'Le type est obligatoire.')]
    #[Assert\Length(min: 2, max: 200, minMessage: 'Le type doit contenir au moins {{ limit }} caractères.', maxMessage: 'Le type ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $type = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: 'Le tarif est obligatoire.')]
    #[Assert\PositiveOrZero(message: 'Le tarif doit être un nombre positif.')]
    #[Assert\Range(min: 0, max: 10000, notInRangeMessage: 'Le tarif doit être entre {{ min }} et {{ max }}.')]
    private ?float $Tarif = null;

    
    #[ORM\Column(length: 200, nullable: true)]
    #[Assert\NotBlank(message: 'La durée est obligatoire.')]
    #[Assert\Range(min: 1, max: 1440, notInRangeMessage: 'La durée doit être entre {{ min }} et {{ max }} minutes.')]
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
