<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\PrescriptionRepository;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: PrescriptionRepository::class)]
#[ORM\Table(name: "prescription")]
class Prescription
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id_prescription = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: 'Ce champ est obligatoire.')]
    private ?string $medicaments = null;

    #[ORM\Column(type: 'string', length: 50)]
    #[Assert\NotBlank(message: 'Ce champ est obligatoire.')]
    private ?string $frequence = null;

    #[ORM\Column(type: 'string', length: 50)]
    #[Assert\NotBlank(message: 'Ce champ est obligatoire.')]
    private ?string $dosage = null;

    #[ORM\Column(type: 'string', length: 50)]
    #[Assert\NotBlank(message: 'Ce champ est obligatoire.')]
    private ?string $duree_traitement = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: 'Ce champ est obligatoire.')]
    private ?string $consignes = null;

    #[ORM\Column(type: 'date')]
    #[Assert\NotNull(message: 'Ce champ est obligatoire.')]
    private ?\DateTimeInterface $date_debut = null;

    #[ORM\Column(type: 'date')]
    #[Assert\NotNull(message: 'Ce champ est obligatoire.')]
    private ?\DateTimeInterface $date_fin = null;

    #[ORM\OneToOne(targetEntity: Consultation::class)]
    #[ORM\JoinColumn(name: "consultation_id", referencedColumnName: "id", nullable: false, unique: true, onDelete: "CASCADE")]
    #[Assert\NotNull(message: 'Ce champ est obligatoire.')]
    private ?Consultation $consultation = null;

    #[Assert\Callback]
    public function validateDateRange(ExecutionContextInterface $context): void
    {
        if (!$this->date_debut || !$this->date_fin) {
            return;
        }

        if ($this->date_fin < $this->date_debut) {
            $context->buildViolation('La date de fin doit etre apres la date de debut.')
                ->atPath('dateFin')
                ->addViolation();
        }
    }

    #[Assert\Callback]
    public function validateConsignesWordCount(ExecutionContextInterface $context): void
    {
        if ($this->consignes === null || trim($this->consignes) === '') {
            return;
        }

        $words = preg_split('/\s+/u', trim($this->consignes), -1, PREG_SPLIT_NO_EMPTY);
        $count = $words ? count($words) : 0;

        if ($count > 1000) {
            $context->buildViolation('Maximum 1000 mots.')
                ->atPath('consignes')
                ->addViolation();
        }
    }

    // ===================== GETTERS & SETTERS =====================

    public function getIdPrescription(): ?int
    {
        return $this->id_prescription;
    }

    public function getMedicaments(): ?string
    {
        return $this->medicaments;
    }

    public function setMedicaments(string $medicaments): self
    {
        $this->medicaments = $medicaments;
        return $this;
    }

    public function getFrequence(): ?string
    {
        return $this->frequence;
    }

    public function setFrequence(string $frequence): self
    {
        $this->frequence = $frequence;
        return $this;
    }

    public function getDosage(): ?string
    {
        return $this->dosage;
    }

    public function setDosage(string $dosage): self
    {
        $this->dosage = $dosage;
        return $this;
    }

    public function getDureeTraitement(): ?string
    {
        return $this->duree_traitement;
    }

    public function setDureeTraitement(string $duree_traitement): self
    {
        $this->duree_traitement = $duree_traitement;
        return $this;
    }

    public function getConsignes(): ?string
    {
        return $this->consignes;
    }

    public function setConsignes(?string $consignes): self
    {
        $this->consignes = $consignes;
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

    public function getConsultation(): ?Consultation
    {
        return $this->consultation;
    }

    public function setConsultation(Consultation $consultation): self
    {
        $this->consultation = $consultation;
        return $this;
    }
}
