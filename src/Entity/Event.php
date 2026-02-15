<?php

namespace App\Entity;

use App\Repository\EventRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EventRepository::class)]
class Event
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank(message: "Le titre est obligatoire.")]
    #[Assert\Length(
        min: 3,
        max: 150,
        minMessage: "Le titre doit contenir au moins {{ limit }} caractères.",
        maxMessage: "Le titre ne doit pas dépasser {{ limit }} caractères."
    )]
    private ?string $titre = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: "La description est obligatoire.")]
    #[Assert\Length(
        min: 10,
        minMessage: "La description doit contenir au moins {{ limit }} caractères."
    )]
    private ?string $description = null;

    #[ORM\Column]
    #[Assert\NotNull(message: "La date de début est obligatoire.")]
    private ?\DateTimeImmutable $dateDebut = null;

    #[ORM\Column(nullable: true)]
    #[Assert\GreaterThanOrEqual(
        propertyPath: "dateDebut",
        message: "La date de fin doit être supérieure ou égale à la date de début."
    )]
    private ?\DateTimeImmutable $dateFin = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(
        max: 255,
        maxMessage: "Le lieu ne doit pas dépasser {{ limit }} caractères."
    )]
    private ?string $lieu = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Positive(message: "La capacité doit être un nombre positif.")]
    #[Assert\LessThanOrEqual(value: 100000, message: "La capacité est trop grande.")]
    private ?int $capaciteMax = null;

    #[ORM\Column(length: 255, nullable: true)]
    // (image upload: la validation File se fait plutôt dans le Form Type, pas ici)
    private ?string $image = null;

    #[ORM\Column(length: 30, nullable: true)]
    #[Assert\Choice(
        choices: ["PRIVEE", "PUBLIE"],
        message: "Statut invalide. Choisissez : PRIVEE, PUBLIE ."
    )]
    private ?string $statut = null;

    #[ORM\Column]
    #[Assert\NotNull(message: "La date de création est obligatoire.")]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: 'boolean')]
    private bool $reminderSent = false;

    #[ORM\ManyToOne(inversedBy: 'events')]
    #[Assert\NotNull(message: "Le type d'événement est obligatoire.")]
    private ?TypeEvent $type = null;

    /**
     * @var Collection<int, Participation>
     */
    #[ORM\OneToMany(mappedBy: 'event', targetEntity: Participation::class, cascade: ['remove'], orphanRemoval: true)]
    private Collection $participations;

    public function __construct()
    {
        $this->participations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }
    public function setTitre(string $titre): static
    {
        $this->titre = $titre;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }
    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getDateDebut(): ?\DateTimeImmutable
    {
        return $this->dateDebut;
    }
    public function setDateDebut(\DateTimeImmutable $dateDebut): static
    {
        $this->dateDebut = $dateDebut;
        return $this;
    }

    public function getDateFin(): ?\DateTimeImmutable
    {
        return $this->dateFin;
    }
    public function setDateFin(?\DateTimeImmutable $dateFin): static
    {
        $this->dateFin = $dateFin;
        return $this;
    }

    public function getLieu(): ?string
    {
        return $this->lieu;
    }
    public function setLieu(?string $lieu): static
    {
        $this->lieu = $lieu;
        return $this;
    }

    public function getCapaciteMax(): ?int
    {
        return $this->capaciteMax;
    }
    public function setCapaciteMax(?int $capaciteMax): static
    {
        $this->capaciteMax = $capaciteMax;
        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }
    public function setImage(?string $image): static
    {
        $this->image = $image;
        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }
    public function setStatut(?string $statut): static
    {
        $this->statut = $statut;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }
    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getType(): ?TypeEvent
    {
        return $this->type;
    }
    public function setType(?TypeEvent $type): static
    {
        $this->type = $type;
        return $this;
    }

    /**
     * @return Collection<int, Participation>
     */
    public function getParticipations(): Collection
    {
        return $this->participations;
    }

    public function addParticipation(Participation $participation): static
    {
        if (!$this->participations->contains($participation)) {
            $this->participations->add($participation);
            $participation->setEvent($this);
        }
        return $this;
    }

    public function removeParticipation(Participation $participation): static
    {
        if ($this->participations->removeElement($participation)) {
            if ($participation->getEvent() === $this) {
                $participation->setEvent(null);
            }
        }
        return $this;
    }
    public function isReminderSent(): bool
    {
        return $this->reminderSent;
    }
    public function setReminderSent(bool $reminderSent): self
    {
        $this->reminderSent = $reminderSent;
        return $this;
    }
}
