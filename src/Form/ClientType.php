<?php

namespace App\Form;

use App\Entity\Client;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ClientType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', null, ['label' => 'Raison Sociale', 'required' => true, 'attr' => ['class' => 'form-control']])
            ->add('siren', null, [
                'label' => 'SIREN',
                'required' => true,
                'attr' => ['class' => 'form-control'],
                'help' => 'Factur-X : Requis pour le routage (9 chiffres)'
            ])
            ->add('siret', null, ['label' => 'SIRET (Optionnel/14 chiffres)', 'attr' => ['class' => 'form-control']])
            ->add('numero_tva', null, [
                'label' => 'Numéro de TVA Intracommunautaire',
                'attr' => ['class' => 'form-control'],
                'help' => 'Obligatoire en dehors du régime exemption.'
            ])
            ->add('email', null, ['label' => 'Email (Recommandé)', 'attr' => ['class' => 'form-control']])
            ->add('adresse', null, [
                'label' => 'Adresse (N° et rue)',
                'required' => true,
                'attr' => ['class' => 'form-control'],
                'help' => 'Factur-X : Adresse requise'
            ])
            ->add('code_postal', null, [
                'label' => 'Code Postal',
                'required' => true,
                'attr' => ['class' => 'form-control']
            ])
            ->add('ville', null, [
                'label' => 'Ville',
                'required' => true,
                'attr' => ['class' => 'form-control']
            ])
            ->add('code_pays', null, [
                'label' => 'Code Pays',
                'required' => true,
                'attr' => ['placeholder' => 'ex: FR', 'class' => 'form-control'],
                'help' => 'Code ISO 3166-1 alpha-2'
            ])
            ->add('telephone', null, ['attr' => ['class' => 'form-control']])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Client::class,
        ]);
    }
}
