<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Enum\Role;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;


#[IsGranted('ROLE_PATIENT')]
class PatientController extends AbstractController
{

    #[Route('/patient', name: 'app_patient_interfce')]
    public function patientDashboard(): Response
    {
        return $this->render('FrontOffice/patient/index.html.twig');
    }



    #[Route('/patient/{id}/upload-dossier', name: 'patient_upload_dossier', methods: ['POST'])]
    public function uploadDossier(
        int $id,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $patient = $this->getUser();

        if (!$patient || !in_array('ROLE_PATIENT', $patient->getRoles(), true)) {
            return $this->json(['message' => 'Accès interdit'], 403);
        }

        if (!$patient) {
            return $this->json(['message' => 'Patient introuvable'], 404);
        }
        if (!in_array('ROLE_PATIENT', $patient->getRoles(), true)) {
            return $this->json(['message' => 'Utilisateur non patient'], 403);          
        }


        $file = $request->files->get('dossierMedical'); // name="dossierMedical"
        if (!$file) {
            return $this->json(['message' => 'Aucun fichier envoyé'], 400);
        }

        // Vérif PDF
        if ($file->getMimeType() !== 'application/pdf') {
            return $this->json(['message' => 'Le fichier doit être un PDF'], 400);
        }

        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/dossiers';
        $newFilename = 'dossier_' . $patient->getId() . '_' . uniqid() . '.pdf';

        try {
            $file->move($uploadDir, $newFilename);
        } catch (FileException $e) {
            return $this->json(['message' => 'Erreur upload fichier'], 500);
        }

        // On stocke juste le chemin relatif
        $patient->setDossierMedicalPath('/uploads/dossiers/' . $newFilename);
        $em->flush();

        return $this->json(['message' => 'Dossier médical uploadé', 'path' => $patient->getDossierMedicalPath()]);
    }
}
