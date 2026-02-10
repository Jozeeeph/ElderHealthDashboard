<?php

namespace App\Form;

use App\Entity\Commande;
use App\Entity\Equipement;
use App\Entity\Utilisateur;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CommandeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('dateCommande', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Date de commande',
                'required' => true,
                'attr' => ['class' => 'form-control'],
                'data' => new \DateTime(), // Default to today
            ])
            ->add('montantTotal', TextType::class, [
                'label' => 'Montant total',
                'required' => false,
                'disabled' => true,
                'attr' => ['class' => 'form-control', 'placeholder' => '0.00'],
                'help' => 'Ce montant sera calculé automatiquement',
            ])
            ->add('equipements', EntityType::class, [
                'class' => Equipement::class,
                'choices' => $options['equipements'],
                'choice_label' => 'nom',
                'multiple' => true,
                'expanded' => false,
                'by_reference' => false,
                'required' => true,
                'attr' => [
                    'class' => 'form-select',
                    'data-choices' => 'true',
                ],
                'help' => 'Sélectionnez un ou plusieurs équipements',
            ])
            ->add('remarques', TextareaType::class, [
                'required' => false,
                'label' => 'Remarques',
                'attr' => ['class' => 'form-control', 'rows' => 4, 'placeholder' => 'Remarques supplémentaires...'],
            ]);

        if ($options['can_choose_user']) {
            $builder->add('utilisateur', EntityType::class, [
                'class' => Utilisateur::class,
                'choices' => $options['users'],
                'choice_label' => function (Utilisateur $user) {
                    return $user->getEmail() . ' - ' . $user->getNom() . ' ' . $user->getPrenom();
                },
                'placeholder' => 'Sélectionner un utilisateur',
                'required' => true,
                'attr' => ['class' => 'form-select'],
            ]);
        }

        if ($options['can_edit_status']) {
            $builder->add('statutCommande', ChoiceType::class, [
                'choices' => [
                    'Panier' => 'panier',
                    'En attente' => 'en_attente',
                    'Validée' => 'validee',
                    'En préparation' => 'en_preparation',
                    'Expédiée' => 'expedie',
                    'Livrée' => 'livree',
                    'Annulée' => 'annulee',
                ],
                'placeholder' => 'Sélectionner un statut',
                'required' => true,
                'attr' => ['class' => 'form-select'],
                'data' => 'panier',
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Commande::class,
            'equipements' => [],
            'users' => [],
            'can_choose_user' => true,
            'can_edit_status' => true,
        ]);
        
        $resolver->setAllowedTypes('equipements', 'array');
        $resolver->setAllowedTypes('users', 'array');
        $resolver->setAllowedTypes('can_choose_user', 'bool');
        $resolver->setAllowedTypes('can_edit_status', 'bool');
    }
}
