<?php

namespace App\Form;

use App\Entity\TypeEvent;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TypeEventType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('libelle', TextType::class, [
                'label' => 'Libellé',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
            ])
            ->add('couleur', ChoiceType::class, [
                'label' => 'Couleur',
                'required' => false,
                'placeholder' => 'Choisir une couleur',
                'choices' => [
                    'Rouge' => 'red',
                    'Bleu' => 'blue',
                    'Vert' => 'green',
                    'Jaune' => 'yellow',
                    'Orange' => 'orange',
                    'Violet' => 'purple',
                    'Gris' => 'gray',
                    'Noir' => 'black',
                ],
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Actif ?',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TypeEvent::class,
        ]);
    }
}
