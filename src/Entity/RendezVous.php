<?php

namespace App\Entity;

use App\Repository\RendezVousRepository;
use App\Entity\Utilisateur;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class RendezVous
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(type: 'time')]
    private ?\DateTimeInterface $heure = null;

    #[ORM\Column(length: 255)]
    private ?string $lieu = null;

    #[ORM\ManyToOne]
    private ?Utilisateur $patient = null;

    #[ORM\ManyToOne]
    private ?Utilisateur $personnelMedical = null;

    #[ORM\ManyToOne]
    private ?TypeRendezVous $typeRendezVous = null;

    #[ORM\ManyToOne]
    private ?Utilisateur $admin = null;
   

        #[ORM\Column(length: 20)]
private ?string $etat = 'PLANIFIE';

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
