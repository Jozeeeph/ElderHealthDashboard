<?php

namespace App\Form;

use App\Entity\Equipement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\FileType;

class EquipementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => "Nom de l'équipement",
                'required' => false, // La validation se fait dans l'Entity
            ])
            ->add('categorie', TextType::class, [
                'label' => 'Catégorie',
                'required' => false,
            ])
            ->add('prix', NumberType::class, [
                'label' => 'Prix (DT)',
                'required' => false,
                'html5' => true,
                'scale' => 2,
            ])
            ->add('quantiteDisponible', NumberType::class, [
                'label' => 'Quantité disponible',
                'required' => false,
                'html5' => true,
            ])
            ->add('statut', ChoiceType::class, [
                'label' => 'Statut',
                'required' => false,
                'choices' => [
                    'Disponible' => 'disponible',
                    'En rupture' => 'en_rupture',
                    'En maintenance' => 'en_maintenance',
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['rows' => 4],
            ])
            ->add('image', FileType::class, [
                'label' => 'Image',
                'required' => false,
                'mapped' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Equipement::class,
        ]);
    }
}