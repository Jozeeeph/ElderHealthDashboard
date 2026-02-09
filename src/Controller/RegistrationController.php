<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Enum\Role;
use App\Form\RegistrationType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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

        if ($form->isSubmitted() && $form->isValid()) {

            // role choisi
            $roleMetier = $form->get('roleMetier')->getData();
            $user->setRoleMetier($roleMetier);

            // password hash
            $hashed = $hasher->hashPassword($user, $user->getPassword());
            $user->setPassword($hashed);

            // upload directory
            $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads';

            // helper upload
            $uploadPdf = function (?UploadedFile $file) use ($uploadDir): ?string {
                if (!$file) return null;
                $safeName = uniqid('f_', true) . '.' . $file->guessExtension();
                $file->move($uploadDir, $safeName);
                return '/uploads/' . $safeName;
            };

            // Patient
            $dossierFile = $form->get('dossierMedicalFile')->getData();
            if ($dossierFile) {
                $user->setDossierMedicalPath($uploadPdf($dossierFile));
            }

            // Personnel médical
            $cvFile = $form->get('cvFile')->getData();
            if ($cvFile) {
                $user->setCv($uploadPdf($cvFile));
            }

            $certFile = $form->get('certificationFile')->getData();
            if ($certFile) {
                $user->setCertification($uploadPdf($certFile));
            }

            $em->persist($user);
            $em->flush();

         return $this->render('BackOffice/message.html.twig', [
    'message' => "✅ Demande envoyée. Votre compte est en attente de validation par l’administrateur.",
    'return_route' => 'app_public_site',
]);

                }
                    return $this->render('FrontOffice/security/register.html.twig', [
    'form' => $form->createView(),
]);

    }
}
