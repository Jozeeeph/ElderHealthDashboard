<?php

namespace App\Form;

use App\Entity\Commande;
use App\Entity\Equipement;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CommandeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('utilisateur', EntityType::class, [
                'class' => User::class,
                'choices' => $options['users'],
                'choice_label' => 'email',
                'placeholder' => 'Sélectionner un utilisateur',
                'required' => true,
                'attr' => ['class' => 'form-select'],
            ])
            ->add('equipements', EntityType::class, [
                'class' => Equipement::class,
                'choices' => $options['equipements'],
                'choice_label' => 'nom',
                'multiple' => true,
                'expanded' => false,
                'required' => true,
                'attr' => [
                    'class' => 'form-select',
                    'data-choices' => 'true',
                ],
                'help' => 'Sélectionnez un ou plusieurs équipements',
            ])
            ->add('statut', ChoiceType::class, [
                'choices' => [
                    'En attente' => 'en_attente',
                    'Confirmée' => 'confirmee',
                    'Expédiée' => 'expediee',
                    'Livrée' => 'livree',
                    'Annulée' => 'annulee',
                ],
                'placeholder' => 'Sélectionner un statut',
                'required' => true,
                'attr' => ['class' => 'form-select'],
            ])
            ->add('adresseLivraison', TextType::class, [
                'required' => false,
                'label' => 'Adresse de livraison',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Adresse complète'],
            ])
            ->add('telephone', TextType::class, [
                'required' => false,
                'label' => 'Téléphone',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Numéro de téléphone'],
            ])
            ->add('email', EmailType::class, [
                'required' => false,
                'label' => 'Email de contact',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Email de contact'],
            ])
            ->add('methodePaiement', ChoiceType::class, [
                'choices' => [
                    'Carte bancaire' => 'carte',
                    'Espèces' => 'espece',
                    'Virement bancaire' => 'virement',
                ],
                'placeholder' => 'Sélectionner une méthode',
                'required' => false,
                'attr' => ['class' => 'form-select'],
            ])
            ->add('remarques', TextareaType::class, [
                'required' => false,
                'label' => 'Remarques',
                'attr' => ['class' => 'form-control', 'rows' => 4, 'placeholder' => 'Remarques supplémentaires...'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Commande::class,
            'equipements' => [],
            'users' => [],
        ]);
        
        $resolver->setAllowedTypes('equipements', 'array');
        $resolver->setAllowedTypes('users', 'array');
    }
}