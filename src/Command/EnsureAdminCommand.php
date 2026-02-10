<?php

namespace App\Command;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:ensure-admin',
    description: 'Crée un admin par défaut si aucun admin n’existe (ou si l’email admin n’existe pas).'
)]
class EnsureAdminCommand extends Command
{
    private const ADMIN_EMAIL = 'admin@elder.tn';
    private const ADMIN_PASSWORD = 'Admin@123';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $repo = $this->em->getRepository(Utilisateur::class);

        // ✅ 1) Vérifier par EMAIL (plus fiable que "ROLE_ADMIN")
        $existing = $repo->findOneBy(['email' => self::ADMIN_EMAIL]);
        if ($existing) {
            $output->writeln('<info>✅ Admin déjà existant : ' . $existing->getEmail() . ' (ID=' . $existing->getId() . ')</info>');
            return Command::SUCCESS;
        }

        // ✅ 2) Créer l’admin avec TOUS les champs obligatoires
        $admin = new Utilisateur();
        $admin->setEmail(self::ADMIN_EMAIL);
        $admin->setNom('Admin');
        $admin->setPrenom('System');
        $admin->setAdresse('Tunis');

        $admin->setAge(30);
        $admin->setDateNaissance(new \DateTime('1995-01-01'));
        $admin->setNumeroTelephone('00000000');
        $admin->setCin('00000000');

        // Statuts (chez toi defaults existent, mais on les fixe clairement)
        $admin->setAccountStatus(Utilisateur::STATUS_APPROVED);
        $admin->setIsActive(true);

        // Roles
        $admin->setRoles(['ROLE_ADMIN']);     // rôle Symfony
        $admin->setRoleMetier('ADMIN');       // rôle métier (met ROLE_ADMIN automatiquement aussi)

        // Password hash
        $admin->setPassword($this->hasher->hashPassword($admin, self::ADMIN_PASSWORD));

        $this->em->persist($admin);
        $this->em->flush();

        $output->writeln('<comment>✅ Admin créé : ' . self::ADMIN_EMAIL . ' / ' . self::ADMIN_PASSWORD . '</comment>');
        return Command::SUCCESS;
    }
}
