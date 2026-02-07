<?php

namespace App\Entity;

use App\Enum\Role;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;


#[ORM\Entity(repositoryClass: UtilisateurRepository::class)]
class Utilisateur implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private ?string $email = null;

    #[ORM\Column(length: 100)]
    private ?string $nom = null;

    #[ORM\Column(length: 100)]
    private ?string $prenom = null;

    #[ORM\Column(length: 255)]
    private ?string $adresse = null;

    #[ORM\Column]
    private ?int $age = null;

    #[ORM\Column(type: "date")]
    private ?\DateTimeInterface $dateNaissance = null;

    #[ORM\Column(length: 20)]
    private ?string $numeroTelephone = null;

    #[ORM\Column(length: 20)]
    private ?string $cin = null;

    #[ORM\Column(length: 50)]
    private ?string $status = null;

    // ✅ Role métier (enum) - مطابق للdiagramme
    #[ORM\Column(enumType: Role::class)]
    private ?Role $role = null;

    // ✅ Roles Symfony Security (tableau)
    #[ORM\Column]
    private array $roles = [];

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

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
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

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    // ✅ Getter/Setter Role (enum)
    public function getRole(): ?Role
    {
        return $this->role;
    }

    public function setRole(?Role $role): self
    {
        $this->role = $role;

        // ✅ sync automatique avec Symfony security roles
        if ($role !== null) {
            $this->roles = ['ROLE_' . $role->value];
        }

        return $this;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
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

    public function eraseCredentials(): void
    {
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
}
