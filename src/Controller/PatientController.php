<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Enum\Role;
use App\Repository\ConsultationRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PatientController extends AbstractController
{

    #[Route('/patient', name: 'app_patient_interfce')]
    public function patientDashboard(): Response
    {
        return $this->render('FrontOffice/patient/index.html.twig');
    }

    #[Route('/patient/consultations', name: 'patient_consultations')]
    public function patientConsultations(ConsultationRepository $consultationRepository, UtilisateurRepository $utilisateurRepository): Response
    {
        $patient = $this->resolveCurrentPatient($utilisateurRepository);
        if (!$patient) {
            $this->addFlash('danger', 'Patient introuvable.');
            return $this->redirectToRoute('app_patient_interfce');
        }

        $consultations = $consultationRepository->findByPatient($patient);

        return $this->render('FrontOffice/patient/consultations.html.twig', [
            'consultations' => $consultations,
            'patient' => $patient,
        ]);
    }





    #[Route('/patient/{id}/upload-dossier', name: 'patient_upload_dossier', methods: ['POST'])]
    public function uploadDossier(
        int $id,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $patient = $em->getRepository(Utilisateur::class)->find($id);
        if (!$patient) {
            return $this->json(['message' => 'Patient introuvable'], 404);
        }
        if ($patient->getRole() !== Role::PATIENT) {
            return $this->json(['message' => 'Utilisateur non patient'], 400);
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

    private function resolveCurrentPatient(UtilisateurRepository $utilisateurRepository): ?Utilisateur
    {
        $user = $this->getUser();
        if ($user instanceof Utilisateur) {
            return $user;
        }

        if ($user instanceof \App\Entity\User) {
            $email = $user->getEmail();
            if ($email) {
                return $utilisateurRepository->findOneBy(['email' => $email]);
            }
        }

        return null;
    }

}
