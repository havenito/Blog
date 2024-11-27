<?php

namespace App\Form;

use App\Entity\Category;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CategoryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            // Champ pour le nom de la catégorie
            ->add('name', TextType::class, [
                'label' => 'Nom de la catégorie',
                'attr' => ['class' => 'form-control'],
            ])
            // Champ pour la description de la catégorie
            ->add('description', TextareaType::class, [
                'label' => 'Description de la catégorie',
                'attr' => ['class' => 'form-control', 'rows' => 4],
            ])
            // Bouton pour soumettre le formulaire
            ->add('submit', SubmitType::class, [
                'label' => 'Créer la catégorie',
                'attr' => ['class' => 'btn btn-primary mt-3'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Category::class,
        ]);
    }
}