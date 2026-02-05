<?php

namespace App\Entity;

use App\Repository\PersonnelMedicalRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PersonnelMedicalRepository::class)]
class PersonnelMedical extends Utilisateur
{
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $cv = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $certification = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $attestation = null;

    #[ORM\Column(length: 255)]
    private ?string $hopitalAffectation = null;

    #[ORM\Column]
    private ?int $nbAnneeExperience = null;

    #[ORM\Column(length: 100)]
    private ?string $specialite = null;

    #[ORM\Column(length: 50)]
    private ?string $disponibilite = null;

    #[ORM\Column(length: 100)]
    private ?string $fonction = null;

    // getters/setters (tu peux les générer avec ton IDE)
}
