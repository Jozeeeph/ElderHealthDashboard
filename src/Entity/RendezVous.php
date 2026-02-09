<?php

namespace App\Entity;

use App\Repository\RendezVousRepository;
use App\Entity\Utilisateur;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
class RendezVous
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'date')]
    #[Assert\NotBlank(message: 'La date est obligatoire.')]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(type: 'time')]
    #[Assert\NotBlank(message: 'L heure est obligatoire.')]
    private ?\DateTimeInterface $heure = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le lieu est obligatoire.')]
    #[Assert\Length(min: 2, max: 255, minMessage: 'Le lieu doit contenir au moins {{ limit }} caracteres.', maxMessage: 'Le lieu ne peut pas depasser {{ limit }} caracteres.')]
    private ?string $lieu = null;

    #[ORM\ManyToOne]
    #[Assert\NotNull(message: 'Le patient est obligatoire.')]
    private ?Utilisateur $patient = null;

    #[ORM\ManyToOne]
    #[Assert\NotNull(message: 'Le personnel medical est obligatoire.')]
    private ?Utilisateur $personnelMedical = null;

    #[ORM\ManyToOne]
    #[Assert\NotNull(message: 'Le type de rendez-vous est obligatoire.')]
    private ?TypeRendezVous $typeRendezVous = null;

    #[ORM\ManyToOne]
    private ?Utilisateur $admin = null;
   

        #[ORM\Column(length: 20)]
        #[Assert\NotBlank(message: 'L etat est obligatoire.')]
        #[Assert\Choice(choices: ['PLANIFIE', 'PLANIFIEE', 'EN_COURS', 'TERMINE', 'TERMINEE', 'ANNULEE'], message: 'Etat invalide.')]
private ?string $etat = 'PLANIFIEE';

public function getEtat(): ?string
{
    return $this->etat;
}

public function setEtat(string $etat): self
{
    $this->etat = $etat;
    return $this;
}
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function getHeure(): ?\DateTimeInterface
    {
        return $this->heure;
    }

    public function getLieu(): ?string
    {
        return $this->lieu;
    }

    public function getPatient(): ?Utilisateur
    {
        return $this->patient;
    }

    public function getPersonnelMedical(): ?Utilisateur
    {
        return $this->personnelMedical;
    }

    public function getTypeRendezVous(): ?TypeRendezVous
    {
        return $this->typeRendezVous;
    }

    

    public function setDate(?\DateTimeInterface $date): self
    {
        $this->date = $date;
        return $this;
    }

    public function setHeure(?\DateTimeInterface $heure): self
    {
        $this->heure = $heure;
        return $this;
    }

    public function setLieu(?string $lieu): self
    {
        $this->lieu = $lieu;
        return $this;
    }

    public function setPatient(?Utilisateur $patient): self
    {
        $this->patient = $patient;
        return $this;
    }

    public function setPersonnelMedical(?Utilisateur $personnelMedical): self
    {
        $this->personnelMedical = $personnelMedical;
        return $this;
    }

    public function setTypeRendezVous(?TypeRendezVous $typeRendezVous): self
    {
        $this->typeRendezVous = $typeRendezVous;
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
