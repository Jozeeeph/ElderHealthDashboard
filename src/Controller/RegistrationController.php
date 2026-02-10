<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Enum\Role;
use App\Form\RegistrationType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): Response {
        $user = new Utilisateur();
        $user->setAccountStatus(Utilisateur::STATUS_PENDING);
        $user->setIsActive(false);

        $form = $this->createForm(RegistrationType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {

            // role choisi
            $roleMetier = $form->get('roleMetier')->getData();

            // fichiers
            $dossierFile = $form->get('dossierMedicalFile')->getData();
            $cvFile = $form->get('cvFile')->getData();
            $certFile = $form->get('certificationFile')->getData();

            // ✅ Contrôles par rôle (ajout d'erreurs au formulaire)
            if ($roleMetier === Role::PATIENT->value) {
                if (!$dossierFile) {
                    $form->get('dossierMedicalFile')->addError(new FormError("Pour un Patient, le dossier médical (PDF) est obligatoire."));
                }
            }

            if ($roleMetier === Role::PERSONNEL_MEDICAL->value) {
                if (!$cvFile) {
                    $form->get('cvFile')->addError(new FormError("Le CV (PDF) est obligatoire pour le Personnel médical."));
                }
                if (!$certFile) {
                    $form->get('certificationFile')->addError(new FormError("La Certification (PDF) est obligatoire pour le Personnel médical."));
                }
            }

            if ($roleMetier === Role::PROPRIETAIRE_MEDICAUX->value) {
                if (!$user->getPatante()) {
                    $form->get('patante')->addError(new FormError("La patente est obligatoire pour un Propriétaire médicaux."));
                }
                if (!$user->getNumeroFix()) {
                    $form->get('numeroFix')->addError(new FormError("Le numéro fixe est obligatoire pour un Propriétaire médicaux."));
                }
            }

            // ✅ Si formulaire valide maintenant, on sauvegarde
            if ($form->isValid()) {

                // affect role métier + roles symfony
                $user->setRoleMetier($roleMetier);

                // ✅ hash à partir du plainPassword
                $plainPassword = $form->get('plainPassword')->getData();
                $hashed = $hasher->hashPassword($user, $plainPassword);
                $user->setPassword($hashed);

                // upload directory
                $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads';

                $uploadPdf = function (?UploadedFile $file) use ($uploadDir): ?string {
                    if (!$file) return null;
                    $safeName = uniqid('f_', true) . '.' . $file->guessExtension();
                    $file->move($uploadDir, $safeName);
                    return '/uploads/' . $safeName;
                };

                // Patient
                if ($dossierFile) {
                    $user->setDossierMedicalPath($uploadPdf($dossierFile));
                }

                // Personnel médical
                if ($cvFile) {
                    $user->setCv($uploadPdf($cvFile));
                }
                if ($certFile) {
                    $user->setCertification($uploadPdf($certFile));
                }

                $em->persist($user);
                $em->flush();

                $this->addFlash('success', "Demande envoyée. Votre compte est en attente de validation par l’administrateur.");
                return $this->redirectToRoute('app_home');
            }
        }

        return $this->render('FrontOffice/security/register.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
