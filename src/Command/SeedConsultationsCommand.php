<?php

namespace App\Command;

use App\Entity\Consultation;
use App\Repository\UtilisateurRepository;
use App\Enum\Role;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:seed-consultations',
    description: 'Create 2 demo consultations for UI preview.'
)]
class SeedConsultationsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UtilisateurRepository $utilisateurRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $patient = $this->utilisateurRepository->findOneBy(['role' => Role::PATIENT]);
        $personnel = $this->utilisateurRepository->findOneBy(['role' => Role::PERSONNEL_MEDICAL]);
        $admin = $this->utilisateurRepository->findOneBy(['role' => Role::ADMIN]);

        if (!$patient || !$personnel || !$admin) {
            $output->writeln('<error>Missing data. Need at least 1 patient, 1 personnel medical, and 1 admin.</error>');
            return Command::FAILURE;
        }

        $now = new \DateTimeImmutable();

        $consultation1 = (new Consultation())
            ->setTypeConsultation('Suivi')
            ->setDateConsultation($now)
            ->setHeureConsultation(new \DateTimeImmutable('09:30'))
            ->setLieu('Cabinet A')
            ->setEtatConsultation('planifiée')
            ->setPatient($patient)
            ->setPersonnelMedical($personnel)
            ->setCreatedBy($admin)
            ->setCreatedRole($admin->getRole() ? $admin->getRole()->value : 'ADMIN')
            ->setCreatedAt($now);

        $consultation2 = (new Consultation())
            ->setTypeConsultation('Urgence')
            ->setDateConsultation($now->modify('+1 day'))
            ->setHeureConsultation(new \DateTimeImmutable('14:15'))
            ->setLieu('Centre Médical')
            ->setEtatConsultation('réalisée')
            ->setPatient($patient)
            ->setPersonnelMedical($personnel)
            ->setCreatedBy($personnel)
            ->setCreatedRole($personnel->getRole() ? $personnel->getRole()->value : 'PERSONNEL_MEDICAL')
            ->setCreatedAt($now);

        $this->em->persist($consultation1);
        $this->em->persist($consultation2);
        $this->em->flush();

        $output->writeln('<info>2 demo consultations created.</info>');
        return Command::SUCCESS;
    }
}
