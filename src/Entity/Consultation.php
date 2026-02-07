<?php

namespace App\Entity;

use App\Repository\ConsultationRepository;
use App\Entity\Utilisateur;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ConsultationRepository::class)]
#[ORM\Table(name: "consultation")]
class Consultation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $typeConsultation = null;

    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $dateConsultation = null;

    #[ORM\Column(type: 'time')]
    private ?\DateTimeInterface $heureConsultation = null;

    #[ORM\Column(length: 255)]
    private ?string $lieu = null;

    #[ORM\Column(length: 50)]
    private ?string $etatConsultation = null; // planifiee / realisee / annulee / en_cours / terminee

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: "created_by_id", referencedColumnName: "id", nullable: true, onDelete: "SET NULL")]
    private ?Utilisateur $createdBy = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $createdRole = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $createdAt = null;

    // ================= RELATIONS =================
    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: "patient_id", referencedColumnName: "id", nullable: false, onDelete: "CASCADE")]
    private ?Utilisateur $patient = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: "personnel_medical_id", referencedColumnName: "id", nullable: false, onDelete: "CASCADE")]
    private ?Utilisateur $personnelMedical = null;

    // ================= RELATIONS ONE TO ONE =================
    #[ORM\OneToOne(mappedBy: "consultation", targetEntity: Prescription::class)]
    private ?Prescription $prescription = null;

    #[ORM\OneToOne(mappedBy: "consultation", targetEntity: RapportMedical::class)]
    private ?RapportMedical $rapportMedical = null;

    // ================= GETTERS & SETTERS =================

    public function getId(): ?int { return $this->id; }

    public function getTypeConsultation(): ?string { return $this->typeConsultation; }
    public function setTypeConsultation(string $typeConsultation): self { $this->typeConsultation = $typeConsultation; return $this; }

    public function getDateConsultation(): ?\DateTimeInterface { return $this->dateConsultation; }
    public function setDateConsultation(\DateTimeInterface $dateConsultation): self { $this->dateConsultation = $dateConsultation; return $this; }

    public function getHeureConsultation(): ?\DateTimeInterface { return $this->heureConsultation; }
    public function setHeureConsultation(\DateTimeInterface $heureConsultation): self { $this->heureConsultation = $heureConsultation; return $this; }

    public function getLieu(): ?string { return $this->lieu; }
    public function setLieu(string $lieu): self { $this->lieu = $lieu; return $this; }

    public function getEtatConsultation(): ?string { return $this->etatConsultation; }
    public function setEtatConsultation(string $etatConsultation): self { $this->etatConsultation = $etatConsultation; return $this; }

    public function getCreatedBy(): ?Utilisateur { return $this->createdBy; }
    public function setCreatedBy(?Utilisateur $createdBy): self { $this->createdBy = $createdBy; return $this; }

    public function getCreatedRole(): ?string { return $this->createdRole; }
    public function setCreatedRole(?string $createdRole): self { $this->createdRole = $createdRole; return $this; }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(?\DateTimeInterface $createdAt): self { $this->createdAt = $createdAt; return $this; }

    public function getPatient(): ?Utilisateur { return $this->patient; }
    public function setPatient(Utilisateur $patient): self { $this->patient = $patient; return $this; }

    public function getPersonnelMedical(): ?Utilisateur { return $this->personnelMedical; }
    public function setPersonnelMedical(Utilisateur $personnelMedical): self { $this->personnelMedical = $personnelMedical; return $this; }

    public function getPrescription(): ?Prescription { return $this->prescription; }
    public function setPrescription(?Prescription $prescription): self {
        $this->prescription = $prescription;
        if ($prescription && $prescription->getConsultation() !== $this) {
            $prescription->setConsultation($this);
        }
        $this->refreshEtatConsultation();
        return $this;
    }

    public function getRapportMedical(): ?RapportMedical { return $this->rapportMedical; }
    public function setRapportMedical(?RapportMedical $rapportMedical): self {
        $this->rapportMedical = $rapportMedical;
        if ($rapportMedical && $rapportMedical->getConsultation() !== $this) {
            $rapportMedical->setConsultation($this);
        }
        $this->refreshEtatConsultation();
        return $this;
    }

    public function refreshEtatConsultation(): void
    {
        if ($this->etatConsultation === 'annulee' || $this->etatConsultation === 'annulée') {
            return;
        }

        if ($this->prescription && $this->rapportMedical) {
            $this->etatConsultation = 'terminee';
            return;
        }

        $this->etatConsultation = 'en_cours';
    }
}
