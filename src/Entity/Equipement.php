<?php

namespace App\Entity;

use App\Repository\EquipementRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EquipementRepository::class)]
class Equipement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le nom de l'équipement est obligatoire")]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: "Le nom doit contenir au moins {{ limit }} caractères",
        maxMessage: "Le nom ne peut pas dépasser {{ limit }} caractères"
    )]
    private ?string $nom = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank(message: "Le prix est obligatoire")]
    #[Assert\Positive(message: "Le prix doit être positif")]
    private ?string $prix = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: "La quantité est obligatoire")]
    #[Assert\PositiveOrZero(message: "La quantité doit être positive ou zéro")]
    private ?int $quantiteDisponible = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le statut est obligatoire")]
    #[Assert\Choice(
        choices: ['disponible', 'en_rupture', 'en_maintenance'],
        message: "Le statut doit être: disponible, en_rupture ou en_maintenance"
    )]
    private ?string $statut = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $categorie = null;

    #[ORM\Column]
    private ?\DateTime $dateAjout = null;

    /**
     * @var Collection<int, Commande>
     */
    #[ORM\ManyToMany(targetEntity: Commande::class, mappedBy: 'equipements')]
    private Collection $commandes;

    public function __construct()
    {
        $this->commandes = new ArrayCollection();
        $this->dateAjout = new \DateTime(); // Initialize dateAjout automatically
        $this->statut = 'disponible'; // Default status
    }

    // ADD THIS METHOD TO PREVENT CIRCULAR REFERENCES
    public function __sleep()
    {
        // Only serialize these properties, skip the relationships
        return [
            'id', 
            'nom', 
            'description', 
            'prix', 
            'quantiteDisponible', 
            'statut', 
            'image', 
            'categorie', 
            'dateAjout'
        ];
        // Note: commandes is NOT included
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getPrix(): ?string
    {
        return $this->prix;
    }

    public function setPrix(string $prix): static
    {
        $this->prix = $prix;

        return $this;
    }

    public function getQuantiteDisponible(): ?int
    {
        return $this->quantiteDisponible;
    }

    public function setQuantiteDisponible(int $quantiteDisponible): static
    {
        $this->quantiteDisponible = $quantiteDisponible;

        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;

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

    public function getCategorie(): ?string
    {
        return $this->categorie;
    }

    public function setCategorie(?string $categorie): static
    {
        $this->categorie = $categorie;

        return $this;
    }

    public function getDateAjout(): ?\DateTime
    {
        return $this->dateAjout;
    }

    public function setDateAjout(\DateTime $dateAjout): static
    {
        $this->dateAjout = $dateAjout;

        return $this;
    }

    /**
     * @return Collection<int, Commande>
     */
    public function getCommandes(): Collection
    {
        return $this->commandes;
    }

    public function addCommande(Commande $commande): static
    {
        if (!$this->commandes->contains($commande)) {
            $this->commandes->add($commande);
            $commande->addEquipement($this);
        }

        return $this;
    }

    public function removeCommande(Commande $commande): static
    {
        if ($this->commandes->removeElement($commande)) {
            $commande->removeEquipement($this);
        }

        return $this;
    }
}