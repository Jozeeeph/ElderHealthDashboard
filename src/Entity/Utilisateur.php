<?php

namespace App\Entity;

use App\Repository\UtilisateurRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UtilisateurRepository::class)]
class Utilisateur implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const STATUS_PENDING  = 'PENDING';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_REFUSED  = 'REFUSED';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank(message: "L'email est obligatoire.")]
    #[Assert\Email(message: "Veuillez saisir un email valide.")]
    #[Assert\Length(max: 180, maxMessage: "L'email ne doit pas dépasser {{ limit }} caractères.")]
    private ?string $email = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: "Le nom est obligatoire.")]
    #[Assert\Length(min: 2, max: 100, minMessage: "Le nom doit contenir au moins {{ limit }} caractères.", maxMessage: "Le nom ne doit pas dépasser {{ limit }} caractères.")]
    #[Assert\Regex(pattern: "/^[\p{L}\s'\-]+$/u", message: "Le nom doit contenir uniquement des lettres.")]
    private ?string $nom = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: "Le prénom est obligatoire.")]
    #[Assert\Length(min: 2, max: 100, minMessage: "Le prénom doit contenir au moins {{ limit }} caractères.", maxMessage: "Le prénom ne doit pas dépasser {{ limit }} caractères.")]
    #[Assert\Regex(pattern: "/^[\p{L}\s'\-]+$/u", message: "Le prénom doit contenir uniquement des lettres.")]
    private ?string $prenom = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "L'adresse est obligatoire.")]
    #[Assert\Length(min: 5, max: 255, minMessage: "L'adresse doit contenir au moins {{ limit }} caractères.", maxMessage: "L'adresse ne doit pas dépasser {{ limit }} caractères.")]
    private ?string $adresse = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: "L'âge est obligatoire.")]
    #[Assert\Positive(message: "L'âge doit être un nombre positif.")]
    #[Assert\Range(min: 1, max: 120, notInRangeMessage: "L'âge doit être entre {{ min }} et {{ max }}.")]
    private ?int $age = null;

    #[ORM\Column(type: 'date')]
    #[Assert\NotNull(message: "La date de naissance est obligatoire.")]
    #[Assert\LessThanOrEqual("today", message: "La date de naissance ne peut pas être dans le futur.")]
    private ?\DateTimeInterface $dateNaissance = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: "Le numéro de téléphone est obligatoire.")]
    #[Assert\Regex(pattern: "/^\+?\d{8,15}$/", message: "Numéro invalide. Exemple: 22123456 ou +21622123456.")]
    private ?string $numeroTelephone = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: "Le CIN est obligatoire.")]
    #[Assert\Regex(pattern: "/^\d{8}$/", message: "Le CIN doit contenir exactement 8 chiffres.")]
    private ?string $cin = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $status = null;

    #[ORM\Column(name: 'account_status', length: 50)]
    private string $accountStatus = self::STATUS_PENDING;

    #[ORM\Column(name: 'is_active', options: ['default' => false])]
    private bool $isActive = false;

    /**
     * ✅ Rôle métier stocké en string : ADMIN / PATIENT / PERSONNEL_MEDICAL / PROPRIETAIRE_MEDICAUX
     */
    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Choice(
        choices: ["ADMIN", "PATIENT", "PERSONNEL_MEDICAL", "PROPRIETAIRE_MEDICAUX"],
        message: "Rôle invalide."
    )]
    private ?string $role = null;

    #[ORM\Column]
    private array $roles = [];

    /**
     * ✅ IMPORTANT :
     * Ce champ contient un HASH (bcrypt/argon2id...) donc ON NE MET PAS de Regex “mot de passe fort” ici.
     * La validation du mot de passe se fait avec plainPassword (dans le FormType, mapped=false).
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $dossierMedicalPath = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $cv = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $certification = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $attestation = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $hopitalAffectation = null;

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero(message: "Le nombre d'années d'expérience doit être positif ou zéro.")]
    #[Assert\Range(min: 0, max: 70, notInRangeMessage: "Expérience invalide.")]
    private ?int $nbAnneeExperience = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100, maxMessage: "La spécialité ne doit pas dépasser {{ limit }} caractères.")]
    private ?string $specialite = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $disponibilite = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100, maxMessage: "La fonction ne doit pas dépasser {{ limit }} caractères.")]
    private ?string $fonction = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100, maxMessage: "Le champ patente ne doit pas dépasser {{ limit }} caractères.")]
    private ?string $patante = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Regex(pattern: "/^\+?\d{8,15}$/", message: "Numéro fixe invalide.")]
    private ?string $numeroFix = null;

    /**
     * @var Collection<int, Participation>
     */
    #[ORM\OneToMany(targetEntity: Participation::class, mappedBy: 'utilisateur')]
    private Collection $participations;

    /**
     * @var Collection<int, Equipement>
     */
    #[ORM\OneToMany(targetEntity: Equipement::class, mappedBy: 'utilisateur')]
    private Collection $equipements;

    /**
     * @var Collection<int, Commande>
     */
    #[ORM\OneToMany(targetEntity: Commande::class, mappedBy: 'utilisateur', orphanRemoval: true)]
    private Collection $commandes;

    public function __construct()
    {
        $this->participations = new ArrayCollection();
        $this->equipements = new ArrayCollection();
        $this->commandes = new ArrayCollection();

        $this->accountStatus = self::STATUS_PENDING;
        $this->isActive = false;

        $this->roles = ['ROLE_USER'];
    }

    // --------------------
    // Security
    // --------------------
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';
        return array_values(array_unique($roles));
    }

    public function eraseCredentials(): void
    {
        // rien
    }

    // --------------------
    // Getters / setters
    // --------------------
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): self
    {
        $this->nom = $nom;
        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): self
    {
        $this->prenom = $prenom;
        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(string $adresse): self
    {
        $this->adresse = $adresse;
        return $this;
    }

    public function getAge(): ?int
    {
        return $this->age;
    }

    public function setAge(int $age): self
    {
        $this->age = $age;
        return $this;
    }

    public function getDateNaissance(): ?\DateTimeInterface
    {
        return $this->dateNaissance;
    }

    public function setDateNaissance(\DateTimeInterface $date): self
    {
        $this->dateNaissance = $date;
        return $this;
    }

    public function getNumeroTelephone(): ?string
    {
        return $this->numeroTelephone;
    }

    public function setNumeroTelephone(string $numeroTelephone): self
    {
        $this->numeroTelephone = $numeroTelephone;
        return $this;
    }

    public function getCin(): ?string
    {
        return $this->cin;
    }

    public function setCin(string $cin): self
    {
        $this->cin = $cin;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getAccountStatus(): string
    {
        return $this->accountStatus;
    }

    public function setAccountStatus(string $status): self
    {
        $allowed = [self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_REFUSED];
        if (!in_array($status, $allowed, true)) {
            throw new \InvalidArgumentException('accountStatus invalide: ' . $status);
        }
        $this->accountStatus = $status;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $active): self
    {
        $this->isActive = $active;
        return $this;
    }

    public function getRoleMetier(): ?string
    {
        return $this->role;
    }

    public function setRoleMetier(?string $role): self
    {
        $this->role = $role;

        if ($role !== null && $role !== '') {
            $this->roles = ['ROLE_' . $role];
        }

        return $this;
    }

    public function getRolesSymfony(): array
    {
        return $this->roles;
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;
        return $this;
    }

    public function getDossierMedicalPath(): ?string
    {
        return $this->dossierMedicalPath;
    }

    public function setDossierMedicalPath(?string $dossierMedicalPath): self
    {
        $this->dossierMedicalPath = $dossierMedicalPath;
        return $this;
    }

    public function getCv(): ?string
    {
        return $this->cv;
    }

    public function setCv(?string $cv): self
    {
        $this->cv = $cv;
        return $this;
    }

    public function getCertification(): ?string
    {
        return $this->certification;
    }

    public function setCertification(?string $certification): self
    {
        $this->certification = $certification;
        return $this;
    }

    public function getAttestation(): ?string
    {
        return $this->attestation;
    }

    public function setAttestation(?string $attestation): self
    {
        $this->attestation = $attestation;
        return $this;
    }

    public function getHopitalAffectation(): ?string
    {
        return $this->hopitalAffectation;
    }

    public function setHopitalAffectation(?string $hopitalAffectation): self
    {
        $this->hopitalAffectation = $hopitalAffectation;
        return $this;
    }

    public function getNbAnneeExperience(): ?int
    {
        return $this->nbAnneeExperience;
    }

    public function setNbAnneeExperience(?int $nbAnneeExperience): self
    {
        $this->nbAnneeExperience = $nbAnneeExperience;
        return $this;
    }

    public function getSpecialite(): ?string
    {
        return $this->specialite;
    }

    public function setSpecialite(?string $specialite): self
    {
        $this->specialite = $specialite;
        return $this;
    }

    public function getDisponibilite(): ?string
    {
        return $this->disponibilite;
    }

    public function setDisponibilite(?string $disponibilite): self
    {
        $this->disponibilite = $disponibilite;
        return $this;
    }

    public function getFonction(): ?string
    {
        return $this->fonction;
    }

    public function setFonction(?string $fonction): self
    {
        $this->fonction = $fonction;
        return $this;
    }

    public function getPatante(): ?string
    {
        return $this->patante;
    }

    public function setPatante(?string $patante): self
    {
        $this->patante = $patante;
        return $this;
    }

    public function getNumeroFix(): ?string
    {
        return $this->numeroFix;
    }

    public function setNumeroFix(?string $numeroFix): self
    {
        $this->numeroFix = $numeroFix;
        return $this;
    }

    public function getParticipations(): Collection
    {
        return $this->participations;
    }

    public function addParticipation(Participation $participation): static
    {
        if (!$this->participations->contains($participation)) {
            $this->participations->add($participation);
            $participation->setUtilisateur($this);
        }
        return $this;
    }

    public function removeParticipation(Participation $participation): static
    {
        if ($this->participations->removeElement($participation)) {
            if ($participation->getUtilisateur() === $this) {
                $participation->setUtilisateur(null);
            }
        }
        return $this;
    }

    public function getEquipements(): Collection
    {
        return $this->equipements;
    }

    public function addEquipement(Equipement $equipement): static
    {
        if (!$this->equipements->contains($equipement)) {
            $this->equipements->add($equipement);
            $equipement->setUtilisateur($this);
        }
        return $this;
    }

    public function removeEquipement(Equipement $equipement): static
    {
        if ($this->equipements->removeElement($equipement)) {
            if ($equipement->getUtilisateur() === $this) {
                $equipement->setUtilisateur(null);
            }
        }
        return $this;
    }

    public function getCommandes(): Collection
    {
        return $this->commandes;
    }

    public function addCommande(Commande $commande): static
    {
        if (!$this->commandes->contains($commande)) {
            $this->commandes->add($commande);
            $commande->setUtilisateur($this);
        }
        return $this;
    }

    public function removeCommande(Commande $commande): static
    {
        if ($this->commandes->removeElement($commande)) {
            if ($commande->getUtilisateur() === $this) {
                $commande->setUtilisateur(null);
            }
        }
        return $this;
    }
}
