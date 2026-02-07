<?php

namespace App\Entity;

use App\Repository\TypeEventRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TypeEventRepository::class)]
class TypeEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: "Le libellé est obligatoire.")]
    #[Assert\Length(
        min: 2,
        max: 100,
        minMessage: "Le libellé doit contenir au moins {{ limit }} caractères.",
        maxMessage: "Le libellé ne doit pas dépasser {{ limit }} caractères."
    )]
    private ?string $libelle = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        max: 2000,
        maxMessage: "La description ne doit pas dépasser {{ limit }} caractères."
    )]
    private ?string $description = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Length(
        max: 20,
        maxMessage: "La couleur ne doit pas dépasser {{ limit }} caractères."
    )]
    // soit tu stockes des couleurs CSS "red/blue", soit un hex "#ff0000"
    #[Assert\Regex(
        pattern: "/^(#[0-9A-Fa-f]{6}|#[0-9A-Fa-f]{3}|red|blue|green|yellow|orange|purple|gray|black)$/",
        message: "Couleur invalide. Utilisez une couleur (red, blue, ...) "
    )]
    private ?string $couleur = null;

    #[ORM\Column]
    #[Assert\NotNull(message: "Le statut actif/inactif est obligatoire.")]
    private ?bool $isActive = null;

    /**
     * @var Collection<int, Event>
     */
    #[ORM\OneToMany(targetEntity: Event::class, mappedBy: 'type')]
    private Collection $events;

    public function __construct()
    {
        $this->events = new ArrayCollection();
        $this->isActive = true; // (optionnel) valeur par défaut
    }

    public function getId(): ?int { return $this->id; }

    public function getLibelle(): ?string { return $this->libelle; }
    public function setLibelle(string $libelle): static { $this->libelle = $libelle; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }

    public function getCouleur(): ?string { return $this->couleur; }
    public function setCouleur(?string $couleur): static { $this->couleur = $couleur; return $this; }

    public function isActive(): ?bool { return $this->isActive; }
    public function setIsActive(bool $isActive): static { $this->isActive = $isActive; return $this; }

    /**
     * @return Collection<int, Event>
     */
    public function getEvents(): Collection { return $this->events; }

    public function addEvent(Event $event): static
    {
        if (!$this->events->contains($event)) {
            $this->events->add($event);
            $event->setType($this);
        }
        return $this;
    }

    public function removeEvent(Event $event): static
    {
        if ($this->events->removeElement($event)) {
            if ($event->getType() === $this) {
                $event->setType(null);
            }
        }
        return $this;
    }
}
