<?php

namespace App\Controller\Admin;

use App\Entity\Consultation;
use App\Entity\Utilisateur;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TimeField;

class ConsultationCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Consultation::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Consultation')
            ->setEntityLabelInPlural('Consultations')
            ->setDefaultSort(['dateConsultation' => 'DESC', 'heureConsultation' => 'DESC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();

        yield AssociationField::new('patient')
            ->setLabel('Patient')
            ->setFormTypeOption('choice_label', static fn (Utilisateur $u) => trim((string) $u->getPrenom() . ' ' . (string) $u->getNom()))
            ->autocomplete();

        yield AssociationField::new('personnelMedical')
            ->setLabel('Personnel medical')
            ->setFormTypeOption('choice_label', static fn (Utilisateur $u) => trim((string) $u->getPrenom() . ' ' . (string) $u->getNom()))
            ->autocomplete();

        yield ChoiceField::new('typeConsultation')
            ->setLabel('Type')
            ->setChoices([
                'Consultation generale' => 'consultation_generale',
                'Suivi' => 'suivi',
                'Urgence' => 'urgence',
                'Teleconsultation' => 'teleconsultation',
            ]);

        yield DateField::new('dateConsultation')->setLabel('Date');
        yield TimeField::new('heureConsultation')->setLabel('Heure');
        yield TextField::new('lieu')->setLabel('Lieu');

        yield ChoiceField::new('etatConsultation')
            ->setLabel('Etat')
            ->setChoices([
                'En cours' => 'en_cours',
                'Terminee' => 'terminee',
                'Planifiee' => 'planifiee',
                'Realisee' => 'realisee',
                'Annulee' => 'annulee',
            ]);

        yield NumberField::new('poidsKg')->setLabel('Poids (kg)');
        yield NumberField::new('tensionSystolique')->setLabel('TA systolique');
        yield NumberField::new('tensionDiastolique')->setLabel('TA diastolique');
        yield TextEditorField::new('notesConsultation')->setLabel('Notes');
    }
}
