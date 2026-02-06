<?php

namespace App\Entity;

use App\Repository\RendezVousRepository;
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
    private ?Patient $patient = null;

    #[ORM\ManyToOne]
    private ?PersonnelMedical $personnelMedical = null;

    #[ORM\ManyToOne]
    private ?TypeRendezVous $typeRendezVous = null;

    #[ORM\ManyToOne]
    private ?Admin $admin = null;

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

    public function getPatient(): ?Patient
    {
        return $this->patient;
    }

    public function getPersonnelMedical(): ?PersonnelMedical
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

    public function setPatient(?Patient $patient): self
    {
        $this->patient = $patient;
        return $this;
    }

    public function setPersonnelMedical(?PersonnelMedical $personnelMedical): self
    {
        $this->personnelMedical = $personnelMedical;
        return $this;
    }

    public function setTypeRendezVous(?TypeRendezVous $typeRendezVous): self
    {
        $this->typeRendezVous = $typeRendezVous;
        return $this;
    }
    public function getAdmin(): ?Admin
{
    return $this->admin;
}

public function setAdmin(?Admin $admin): self
{
    $this->admin = $admin;
    return $this;
}
}

