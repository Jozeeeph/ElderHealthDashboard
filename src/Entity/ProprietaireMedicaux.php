<?php

namespace App\Entity;

use App\Repository\ProprietaireMedicauxRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProprietaireMedicauxRepository::class)]
class ProprietaireMedicaux extends Utilisateur
{
    #[ORM\Column(length: 100)]
    private ?string $patante = null;

    #[ORM\Column(length: 20)]
    private ?string $numeroFix = null;

    #[ORM\Column(length: 100)]
    private ?string $specialite = null;

    public function getPatante(): ?string
    {
        return $this->patante;
    }

    public function setPatante(string $patante): self
    {
        $this->patante = $patante;
        return $this;
    }

    public function getNumeroFix(): ?string
    {
        return $this->numeroFix;
    }

    public function setNumeroFix(string $numeroFix): self
    {
        $this->numeroFix = $numeroFix;
        return $this;
    }

    public function getSpecialite(): ?string
    {
        return $this->specialite;
    }

    public function setSpecialite(string $specialite): self
    {
        $this->specialite = $specialite;
        return $this;
    }
}
