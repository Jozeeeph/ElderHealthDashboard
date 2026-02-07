<?php

namespace App\Command;

use App\Entity\Utilisateur;
use App\Enum\Role;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-patient',
    description: 'Create a Patient user for testing.'
)]
class CreatePatientCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Email')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Password', 'Password123!')
            ->addOption('nom', null, InputOption::VALUE_REQUIRED, 'Nom', 'Patient')
            ->addOption('prenom', null, InputOption::VALUE_REQUIRED, 'Prenom', 'Demo')
            ->addOption('cin', null, InputOption::VALUE_REQUIRED, 'CIN', 'CIN' . random_int(1000, 9999));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = $input->getOption('email') ?? ('patient' . time() . '@example.test');

        $user = new Utilisateur();
        $user->setEmail($email);
        $user->setNom($input->getOption('nom'));
        $user->setPrenom($input->getOption('prenom'));
        $user->setAdresse('Adresse demo');
        $user->setAge(65);
        $user->setDateNaissance(new \DateTime('1960-01-01'));
        $user->setNumeroTelephone('0700000000');
        $user->setCin($input->getOption('cin'));
        $user->setStatus('actif');
        $user->setRole(Role::PATIENT);

        $hashed = $this->hasher->hashPassword($user, (string) $input->getOption('password'));
        $user->setPassword($hashed);

        $this->em->persist($user);
        $this->em->flush();

        $output->writeln('<info>Patient created: ' . $email . '</info>');
        return Command::SUCCESS;
    }
}
