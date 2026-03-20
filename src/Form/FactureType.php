<?php

namespace App\Form;

use App\Entity\Facture;
use App\Entity\Client;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FactureType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('numeroFacture', TextType::class, [
                'label' => 'Numéro de facture',
                'attr' => [
                    'placeholder' => 'Ex: FAC-2025-001',
                    'class' => 'form-control'
                ]
            ])
            ->add('dateFacture', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Date de la facture',
                'attr' => ['class' => 'form-control']
            ])
            ->add('dateEcheance', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Date d\'échéance',
                'required' => false,
                'attr' => ['class' => 'form-control']
            ])
            ->add('fournisseur', EntityType::class, [
                'class' => Client::class,
                'choice_label' => 'nom',
                'placeholder' => 'Sélectionner un fournisseur existant',
                'required' => true,
                'help' => 'Factur-X : Requis',
            ])
            ->add('acheteur', EntityType::class, [
                'class' => Client::class,
                'choice_label' => 'nom',
                'placeholder' => 'Sélectionner un acheteur existant',
                'required' => true,
                'help' => 'Factur-X : Requis',
            ])
            ->add('natureOperation', ChoiceType::class, [
                'label' => 'Nature de l\'opération',
                'required' => true,
                'placeholder' => 'Sélectionner',
                'choices' => [
                    'Prestation de services' => 'services',
                    'Livraison de biens' => 'biens',
                    'Mixte' => 'mixte',
                ],
                'attr' => ['class' => 'form-control'],
                'help' => 'Factur-X : Obligatoire selon la réforme 2026',
            ])
            ->add('tvaDebits', CheckboxType::class, [
                'label' => 'TVA sur les débits',
                'required' => false,
            ])
            ->add('livraisonAdresse', TextType::class, [
                'label' => 'Adresse de livraison (si différente)',
                'required' => false,
                'attr' => ['placeholder' => 'Rue de livraison', 'class' => 'form-control'],
            ])
            ->add('livraisonVille', TextType::class, [
                'label' => 'Ville de livraison',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('livraisonCodePostal', TextType::class, [
                'label' => 'Code postal de livraison',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('livraisonCodePays', TextType::class, [
                'label' => 'Code pays de livraison',
                'required' => false,
                'attr' => ['placeholder' => 'FR', 'class' => 'form-control'],
            ])
            ->add('lignes', CollectionType::class, [
                'entry_type' => FactureLigneType::class,
                'label' => 'Lignes de facture',
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
                'attr' => [
                    'class' => 'facture-lignes-collection'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Facture::class,
        ]);
    }
}
