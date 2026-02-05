<?php

namespace App\Entity;
use App\Repository\PatientRepository;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Patient extends Utilisateur
{
    // Chemin/nom du fichier PDF stocké sur le serveur
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $dossierMedicalPath = null;

    public function getDossierMedicalPath(): ?string
    {
        return $this->dossierMedicalPath;
    }

    public function setDossierMedicalPath(?string $dossierMedicalPath): self
    {
        $this->dossierMedicalPath = $dossierMedicalPath;
        return $this;
    }
}
