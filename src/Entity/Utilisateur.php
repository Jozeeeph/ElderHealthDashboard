<?php

namespace App\Entity;

use App\Repository\UtilisateurRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[UniqueEntity(
    fields: ['email'],
    message: 'Cet email est déjà utilisé.'
)]
#[ORM\Entity(repositoryClass: UtilisateurRepository::class)]
class Utilisateur implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_REFUSED = 'REFUSED';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank(message: "L'email est obligatoire.")]
    #[Assert\Email(message: "Format d'email invalide.")]
    private ?string $email = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: "Le nom est obligatoire.")]
    #[Assert\Length(min: 2, max: 100)]
    private ?string $nom = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: "Le prénom est obligatoire.")]
    #[Assert\Length(min: 2, max: 100)]
    private ?string $prenom = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "L'adresse est obligatoire.")]
    private ?string $adresse = null;

    #[ORM\Column]
    #[Assert\NotNull(message: "L'âge est obligatoire.")]
    #[Assert\Range(
        min: 0,
        max: 120,
        notInRangeMessage: "L'âge doit être entre {{ min }} et {{ max }} ans."
    )]
    private ?int $age = null;

    #[ORM\Column(type: 'date')]
    #[Assert\NotNull(message: "La date de naissance est obligatoire.")]
    #[Assert\LessThan("today", message: "La date de naissance doit être dans le passé.")]
    private ?\DateTimeInterface $dateNaissance = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: "Le numéro de téléphone est obligatoire.")]
    #[Assert\Regex(
        pattern: "/^[0-9]{8,15}$/",
        message: "Le numéro de téléphone doit contenir uniquement des chiffres."
    )]
    private ?string $numeroTelephone = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: "Le CIN est obligatoire.")]
    #[Assert\Length(min: 6, max: 20)]
    private ?string $cin = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $status = null;

    #[ORM\Column(name: 'account_status', length: 50)]
    private string $accountStatus = self::STATUS_PENDING;

    #[ORM\Column(name: 'is_active', options: ['default' => false])]
    private bool $isActive = false;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $role = null;

    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    #[Assert\NotBlank(message: "Le mot de passe est obligatoire.")]
    #[Assert\Length(
        min: 6,
        minMessage: "Le mot de passe doit contenir au moins {{ limit }} caractères."
    )]
    private ?string $password = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\File(
        maxSize: "5M",
        mimeTypes: ["application/pdf"],
        mimeTypesMessage: "Veuillez uploader un PDF valide."
    )]
    private ?string $dossierMedicalPath = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\File(maxSize: "5M")]
    private ?string $cv = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\File(maxSize: "5M")]
    private ?string $certification = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\File(maxSize: "5M")]
    private ?string $attestation = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $hopitalAffectation = null;

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero(message: "L'expérience doit être positive.")]
    private ?int $nbAnneeExperience = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $specialite = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $disponibilite = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $fonction = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $patante = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $numeroFix = null;

    #[ORM\OneToMany(mappedBy: 'utilisateur', targetEntity: Participation::class)]
    private Collection $participations;

    #[ORM\OneToMany(mappedBy: 'utilisateur', targetEntity: Equipement::class)]
    private Collection $equipements;

    #[ORM\OneToMany(mappedBy: 'utilisateur', targetEntity: Commande::class, orphanRemoval: true)]
    private Collection $commandes;

    public function __construct()
    {
        $this->participations = new ArrayCollection();
        $this->equipements = new ArrayCollection();
        $this->commandes = new ArrayCollection();
        $this->roles = ['ROLE_USER'];
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }

    public function eraseCredentials(): void
    {
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

    // ✅ validation admin
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

    // ✅ activation admin
    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $active): self
    {
        $this->isActive = $active;
        return $this;
    }

    /**
     * ✅ Rôle métier string: ADMIN / PATIENT / PERSONNEL_MEDICAL / PROPRIETAIRE_MEDICAUX
     */
    public function getRoleMetier(): ?string
    {
        return $this->role;
    }

    public function setRoleMetier(?string $role): self
    {
        $this->role = $role;

        // sync automatique roles Symfony
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
            $equipement->setUtilisateur($this);
        }

        return $this;
    }

    public function removeEquipement(Equipement $equipement): static
    {
        if ($this->equipements->removeElement($equipement)) {
            // set the owning side to null (unless already changed)
            if ($equipement->getUtilisateur() === $this) {
                $equipement->setUtilisateur(null);
            }
        }

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
            $commande->setUtilisateur($this);
        }

        return $this;
    }

    public function removeCommande(Commande $commande): static
    {
        if ($this->commandes->removeElement($commande)) {
            // set the owning side to null (unless already changed)
            if ($commande->getUtilisateur() === $this) {
                $commande->setUtilisateur(null);
            }
        }

        return $this;
    }
}
