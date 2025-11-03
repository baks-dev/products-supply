<?php
/*
 *  Copyright 2025.  Baks.dev <admin@baks.dev>
 *  
 *  Permission is hereby granted, free of charge, to any person obtaining a copy
 *  of this software and associated documentation files (the "Software"), to deal
 *  in the Software without restriction, including without limitation the rights
 *  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 *  copies of the Software, and to permit persons to whom the Software is furnished
 *  to do so, subject to the following conditions:
 *  
 *  The above copyright notice and this permission notice shall be included in all
 *  copies or substantial portions of the Software.
 *  
 *  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 *  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 *  FITNESS FOR A PARTICULAR PURPOSE AND NON INFRINGEMENT. IN NO EVENT SHALL THE
 *  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 *  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 *  OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 *  THE SOFTWARE.
 *
 */
declare(strict_types=1);

namespace BaksDev\Products\Supply\Forms\Statuses\Delivery;

use BaksDev\Products\Supply\Forms\Statuses\ProductSupplyIdForm;
use BaksDev\Users\Profile\UserProfile\Repository\UserProfileChoice\UserProfileChoiceInterface;
use BaksDev\Users\Profile\UserProfile\Repository\UserProfileTokenStorage\UserProfileTokenStorageInterface;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use DateTimeImmutable;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class DeliveryProductSupplyForm extends AbstractType
{
    public function __construct(
        private readonly UserProfileChoiceInterface $userProfileChoice,
        private readonly UserProfileTokenStorageInterface $profileTokenStorage,
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('supplys', CollectionType::class,
            [
                'entry_type' => ProductSupplyIdForm::class,
                'entry_options' => ['label' => false],
                'label' => false,
                'allow_add' => true,
            ]
        );

        /**
         * Все профили пользователя
         */
        $profiles = $this->userProfileChoice
            ->getActiveUserProfile($this->profileTokenStorage->getUser());

        /** Склад назначения */
        $builder->add(
            'profile',
            ChoiceType::class,
            [
                'choices' => $profiles,
                'choice_value' => function(?UserProfileUid $profile) {
                    return $profile?->getValue();
                },
                'choice_label' => function(UserProfileUid $warehouse) {
                    return $warehouse->getAttr();
                },
                'label' => false,
                'required' => true,
            ]
        );

        $builder->add('arrival', DateType::class, [
            'widget' => 'single_text',
            'html5' => false,
            'attr' => ['class' => 'js-datepicker'],
            'required' => true,
            'format' => 'dd.MM.yyyy',
            'input' => 'datetime_immutable',
        ]);

        $builder->get('arrival')
            ->addModelTransformer(
                new CallbackTransformer(
                    function(?DateTimeImmutable $date) {
                        return $date;
                    },
                    function(?DateTimeImmutable $date) {
                        return ($date instanceof DateTimeImmutable) ? $date : new DateTimeImmutable();
                    },
                ),
            );

        $builder->add('comment', TextareaType::class);

        /** Сохранить */
        $builder->add(
            'delivery',
            SubmitType::class,
            ['label' => 'Save', 'label_html' => true, 'attr' => ['class' => 'btn-primary']]
        );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(
            [
                'data_class' => DeliveryProductSupplyDTO::class,
                'method' => 'POST',
                'attr' => ['class' => 'w-100'],
            ]
        );
    }
}