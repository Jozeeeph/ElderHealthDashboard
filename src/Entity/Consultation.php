<?php

namespace App\Entity;

use App\Repository\ConsultationRepository;
use App\Entity\Utilisateur;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ConsultationRepository::class)]
#[ORM\Table(name: "consultation")]
class Consultation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le type de consultation est obligatoire.')]
    #[Assert\Choice(
        choices: ['consultation_generale', 'suivi', 'urgence', 'teleconsultation'],
        message: 'Type de consultation invalide.'
    )]
    private ?string $typeConsultation = null;

    #[ORM\Column(type: 'date')]
    #[Assert\NotNull(message: 'La date de consultation est obligatoire.')]
    private ?\DateTimeInterface $dateConsultation = null;

    #[ORM\Column(type: 'time')]
    #[Assert\NotNull(message: 'L heure de consultation est obligatoire.')]
    private ?\DateTimeInterface $heureConsultation = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le lieu est obligatoire.')]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'Le lieu doit contenir au moins {{ limit }} caracteres.',
        maxMessage: 'Le lieu ne peut pas depasser {{ limit }} caracteres.'
    )]
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

    #[ORM\Column(name: 'poids_kg', type: 'decimal', precision: 5, scale: 2, nullable: true)]
    #[Assert\Range(
        min: 20,
        max: 300,
        notInRangeMessage: 'Le poids doit etre entre {{ min }} et {{ max }} kg.'
    )]
    private ?string $poidsKg = null;

    #[ORM\Column(name: 'tension_systolique', type: 'smallint', nullable: true)]
    #[Assert\Range(
        min: 60,
        max: 250,
        notInRangeMessage: 'La tension systolique doit etre entre {{ min }} et {{ max }} mmHg.'
    )]
    private ?int $tensionSystolique = null;

    #[ORM\Column(name: 'tension_diastolique', type: 'smallint', nullable: true)]
    #[Assert\Range(
        min: 30,
        max: 150,
        notInRangeMessage: 'La tension diastolique doit etre entre {{ min }} et {{ max }} mmHg.'
    )]
    private ?int $tensionDiastolique = null;

    // ================= RELATIONS =================
    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: "patient_id", referencedColumnName: "id", nullable: false, onDelete: "CASCADE")]
    #[Assert\NotNull(message: 'Le patient est obligatoire.')]
    private ?Utilisateur $patient = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: "personnel_medical_id", referencedColumnName: "id", nullable: false, onDelete: "CASCADE")]
    #[Assert\NotNull(message: 'Le personnel medical est obligatoire.')]
    private ?Utilisateur $personnelMedical = null;

    // ================= RELATIONS ONE TO ONE =================
    #[ORM\OneToOne(mappedBy: "consultation", targetEntity: Prescription::class, cascade: ["remove"])]
    private ?Prescription $prescription = null;

    #[ORM\OneToOne(mappedBy: "consultation", targetEntity: RapportMedical::class, cascade: ["remove"])]
    private ?RapportMedical $rapportMedical = null;

    // ================= GETTERS & SETTERS =================

    public function getId(): ?int { return $this->id; }

    public function getTypeConsultation(): ?string { return $this->typeConsultation; }
    public function setTypeConsultation(string $typeConsultation): self { $this->typeConsultation = $typeConsultation; return $this; }

    public function getDateConsultation(): ?\DateTimeInterface { return $this->dateConsultation; }
    public function setDateConsultation(?\DateTimeInterface $dateConsultation): self { $this->dateConsultation = $dateConsultation; return $this; }

    public function getHeureConsultation(): ?\DateTimeInterface { return $this->heureConsultation; }
    public function setHeureConsultation(?\DateTimeInterface $heureConsultation): self { $this->heureConsultation = $heureConsultation; return $this; }

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

    public function getPoidsKg(): ?string { return $this->poidsKg; }
    public function setPoidsKg(?string $poidsKg): self { $this->poidsKg = $poidsKg; return $this; }

    public function getTensionSystolique(): ?int { return $this->tensionSystolique; }
    public function setTensionSystolique(?int $tensionSystolique): self { $this->tensionSystolique = $tensionSystolique; return $this; }

    public function getTensionDiastolique(): ?int { return $this->tensionDiastolique; }
    public function setTensionDiastolique(?int $tensionDiastolique): self { $this->tensionDiastolique = $tensionDiastolique; return $this; }

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
