<?php

namespace App\Entity;

use App\Repository\PrescriptionDoseAckRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PrescriptionDoseAckRepository::class)]
#[ORM\Table(name: 'prescription_dose_ack')]
#[ORM\UniqueConstraint(name: 'uniq_prescription_slot', columns: ['prescription_id', 'scheduled_at'])]
class PrescriptionDoseAck
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Prescription::class)]
    #[ORM\JoinColumn(name: 'prescription_id', referencedColumnName: 'id_prescription', nullable: false, onDelete: 'CASCADE')]
    private ?Prescription $prescription = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $scheduledAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $doneAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPrescription(): ?Prescription
    {
        return $this->prescription;
    }

    public function setPrescription(Prescription $prescription): self
    {
        $this->prescription = $prescription;

        return $this;
    }

    public function getScheduledAt(): ?\DateTimeImmutable
    {
        return $this->scheduledAt;
    }

    public function setScheduledAt(\DateTimeImmutable $scheduledAt): self
    {
        $this->scheduledAt = $scheduledAt;

        return $this;
    }

    public function getDoneAt(): ?\DateTimeImmutable
    {
        return $this->doneAt;
    }

    public function setDoneAt(\DateTimeImmutable $doneAt): self
    {
        $this->doneAt = $doneAt;

        return $this;
    }
}

