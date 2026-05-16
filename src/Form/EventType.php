<?php

namespace App\Form;

use App\Entity\Event;
use App\Entity\Organization;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;


class EventType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
        //basic fields
        ->add('title',TextType::class)
        ->add('description',TextareaType::class)
        ->add('location',TextType::class)
        //dates
        ->add('startDate',DateTimeType::class,[
            'widget'=>'single_text',
            'attr' => [
                'min' => (new \DateTimeImmutable())->format('Y-m-d\\TH:i'),
            ],
        ])
        ->add('endDate',DateTimeType::class,[
            'widget'=>'single_text',
            'attr' => [
                'min' => (new \DateTimeImmutable())->format('Y-m-d\\TH:i'),
            ],
        ])
        //type (Ouvert / Competition)
        ->add('type',ChoiceType::class,[
            'choices'=>[
                'Ouvert'=>Event::TYPE_OPEN,
                'Competition'=>Event::TYPE_COMPET,
            ],
        ])
        //Mode (individual/Organization)
        ->add('mode',ChoiceType::class,[
            'choices'=>[
                'Individuel'=>Event::MODE_INDIV,
                'Organisation'=>Event::MODE_ORG,
            ],
        ])

        ->add('imageFile', FileType::class, [
            'mapped' => false,
            'required' => false,
            'label' => 'Event image (optional)',
            'attr' => ['accept' => 'image/*'],
        ])

        //Optional Fields
        ->add('capacity',IntegerType::class,[
            'required'=>false,
        ])
        ->add('detailedReport',TextareaType::class,[
            'required'=>false,
        ])
        ->add('organization', EntityType::class, [
        'class' => Organization::class,
        'choices' => $options['user_organizations'],
        'choice_label' => 'name',
        'placeholder' => 'Select your organization',
            ]);

    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Event::class,
            'user_organizations' => [],
        ]);
    }
}
