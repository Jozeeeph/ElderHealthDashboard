<?php

namespace App\Entity;

use App\Repository\CommandeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommandeRepository::class)]
class Commande
{
    // Status constants for medical equipment orders
    public const STATUT_PANIER = 'panier';               // In cart (not submitted yet)
    public const STATUT_EN_ATTENTE = 'en_attente';       // Submitted, awaiting approval
    public const STATUT_VALIDE = 'validee';              // Validated by admin
    public const STATUT_EN_PREPARATION = 'en_preparation'; // Being prepared
    public const STATUT_EXPEDIE = 'expedie';             // Shipped
    public const STATUT_LIVRE = 'livree';                // Delivered
    public const STATUT_ANNULE = 'annulee';              // Cancelled

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?\DateTime $dateCommande = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $montantTotal = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $remarques = null;

    #[ORM\Column(length: 255)]
    private ?string $statutCommande = self::STATUT_PANIER; // ADDED: Order status field

    /**
     * @var Collection<int, Equipement>
     */
    #[ORM\ManyToMany(targetEntity: Equipement::class, inversedBy: 'commandes')]
    private Collection $equipements;

    #[ORM\ManyToOne(inversedBy: 'commandes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $utilisateur = null;

    public function __construct()
    {
        $this->equipements = new ArrayCollection();
        $this->dateCommande = new \DateTime(); // Set default date automatically
        $this->statutCommande = self::STATUT_PANIER; // Default: in cart
        $this->montantTotal = '0.00'; // Default amount
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDateCommande(): ?\DateTime
    {
        return $this->dateCommande;
    }

    public function setDateCommande(\DateTime $dateCommande): static
    {
        $this->dateCommande = $dateCommande;

        return $this;
    }

    public function getMontantTotal(): ?string
    {
        return $this->montantTotal;
    }

    public function setMontantTotal(string $montantTotal): static
    {
        $this->montantTotal = $montantTotal;

        return $this;
    }

    public function getRemarques(): ?string
    {
        return $this->remarques;
    }

    public function setRemarques(?string $remarques): static
    {
        $this->remarques = $remarques;

        return $this;
    }

    // ADDED: Order status methods
    public function getStatutCommande(): ?string
    {
        return $this->statutCommande;
    }

    public function setStatutCommande(string $statutCommande): static
    {
        $this->statutCommande = $statutCommande;
        return $this;
    }

    // ADDED: Helper method to get all possible statuses
    public static function getStatuses(): array
    {
        return [
            'Panier' => self::STATUT_PANIER,
            'En attente' => self::STATUT_EN_ATTENTE,
            'Validée' => self::STATUT_VALIDE,
            'En préparation' => self::STATUT_EN_PREPARATION,
            'Expédiée' => self::STATUT_EXPEDIE,
            'Livrée' => self::STATUT_LIVRE,
            'Annulée' => self::STATUT_ANNULE,
        ];
    }

    // ADDED: Helper method to get readable status name
    public function getStatutCommandeReadable(): string
    {
        $statuses = array_flip(self::getStatuses());
        return $statuses[$this->statutCommande] ?? $this->statutCommande;
    }

    /**
     * @return Collection<int, Equipement>
     */
    public function getEquipements(): Collection
    {
        return $this->equipements;
    }

    public function addEquipement(Equipement $equipement): static
    {
        if (!$this->equipements->contains($equipement)) {
            $this->equipements->add($equipement);
            // ADDED: Auto-calculate total when adding equipment
            $this->calculateMontantTotal();
        }

        return $this;
    }

    public function removeEquipement(Equipement $equipement): static
    {
        if ($this->equipements->removeElement($equipement)) {
            // ADDED: Auto-calculate total when removing equipment
            $this->calculateMontantTotal();
        }

        return $this;
    }

    // ADDED: Method to calculate total amount
    public function calculateMontantTotal(): void
    {
        $total = '0.00';
        foreach ($this->equipements as $equipement) {
            $total = bcadd($total, $equipement->getPrix() ?? '0.00', 2);
        }
        $this->montantTotal = $total;
    }

    // ADDED: Method to submit cart to order
    public function soumettreCommande(): void
    {
        if ($this->statutCommande === self::STATUT_PANIER) {
            $this->statutCommande = self::STATUT_EN_ATTENTE;
            $this->dateCommande = new \DateTime();
        }
    }

    // ADDED: Method to cancel order
    public function annulerCommande(): void
    {
        $this->statutCommande = self::STATUT_ANNULE;
    }

    // ADDED: Check if order is in cart
    public function estDansPanier(): bool
    {
        return $this->statutCommande === self::STATUT_PANIER;
    }

    // ADDED: Check if order is completed
    public function estCompletee(): bool
    {
        return $this->statutCommande === self::STATUT_LIVRE;
    }

    public function getUtilisateur(): ?Utilisateur
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(?Utilisateur $utilisateur): static
    {
        $this->utilisateur = $utilisateur;

        return $this;
    }

    // ADDED: String representation
    public function __toString(): string
    {
        return sprintf('Commande #%d - %s - %s DT', 
            $this->id, 
            $this->dateCommande->format('d/m/Y'),
            $this->montantTotal
        );
    }
}