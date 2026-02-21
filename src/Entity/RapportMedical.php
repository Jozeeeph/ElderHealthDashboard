<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\RapportMedicalRepository;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[Vich\Uploadable]
#[ORM\Entity(repositoryClass: RapportMedicalRepository::class)]
#[ORM\Table(name: "rapport_medical")]
class RapportMedical
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id_rapport = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: 'Ce champ est obligatoire.')]
    private ?string $diagnostic = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: 'Ce champ est obligatoire.')]
    private ?string $recommandations = null;

    #[ORM\Column(type: 'string', length: 10)]
    #[Assert\NotBlank(message: 'Ce champ est obligatoire.')]
    #[Assert\Choice(choices: ['faible', 'moyen', 'eleve'], message: 'Niveau de gravite invalide.')]
    private ?string $niveau_gravite = null; // faible / moyen / elevé

    #[ORM\Column(type: 'datetime')]
    #[Assert\NotNull(message: 'Ce champ est obligatoire.')]
    private ?\DateTimeInterface $date_rapport = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $fichier_path = null;

    #[Vich\UploadableField(mapping: 'rapport_files', fileNameProperty: 'fichier_path')]
    private ?File $fichier = null;

    #[ORM\OneToOne(targetEntity: Consultation::class)]
    #[ORM\JoinColumn(name: "consultation_id", referencedColumnName: "id", nullable: false, unique: true, onDelete: "CASCADE")]
    #[Assert\NotNull(message: 'La consultation est obligatoire.')]
    private ?Consultation $consultation = null;

    // ===================== GETTERS & SETTERS =====================

    public function getIdRapport(): ?int
    {
        return $this->id_rapport;
    }

    public function getDiagnostic(): ?string
    {
        return $this->diagnostic;
    }

    public function setDiagnostic(?string $diagnostic): self
    {
        $this->diagnostic = $diagnostic;
        return $this;
    }

    public function getRecommandations(): ?string
    {
        return $this->recommandations;
    }

    public function setRecommandations(?string $recommandations): self
    {
        $this->recommandations = $recommandations;
        return $this;
    }

    public function getNiveauGravite(): ?string
    {
        return $this->niveau_gravite;
    }

    public function setNiveauGravite(?string $niveau_gravite): self
    {
        $this->niveau_gravite = $niveau_gravite;
        return $this;
    }

    public function getDateRapport(): ?\DateTimeInterface
    {
        return $this->date_rapport;
    }

    public function setDateRapport(?\DateTimeInterface $date_rapport): self
    {
        $this->date_rapport = $date_rapport;
        return $this;
    }

    public function getFichierPath(): ?string
    {
        return $this->fichier_path;
    }

    public function setFichierPath(?string $fichier_path): self
    {
        $this->fichier_path = $fichier_path;
        return $this;
    }

    public function getFichier(): ?File
    {
        return $this->fichier;
    }

    public function setFichier(?File $fichier): self
    {
        $this->fichier = $fichier;

        if ($fichier !== null) {
            // Trigger Doctrine update so Vich can process file replacement.
            $this->date_rapport = new \DateTime();
        }

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
