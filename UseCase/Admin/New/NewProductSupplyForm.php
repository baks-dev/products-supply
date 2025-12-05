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

namespace BaksDev\Products\Supply\UseCase\Admin\New;

use BaksDev\Products\Supply\UseCase\Admin\File\ProductSupplyFilesForm;
use BaksDev\Products\Supply\UseCase\Admin\New\Invariable\NewProductSupplyInvariableForm;
use BaksDev\Products\Supply\UseCase\Admin\New\PreProduct\PreProductForm;
use BaksDev\Products\Supply\UseCase\Admin\New\Product\NewProductSupplyProductForm;
use BaksDev\Users\Profile\UserProfile\Repository\UserProfileTokenStorage\UserProfileTokenStorageInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class NewProductSupplyForm extends AbstractType
{
    public function __construct(private readonly UserProfileTokenStorageInterface $userProfileTokenStorage) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {

        /**
         * Данные пользователя
         */
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function(FormEvent $event): void {

            /** @var NewProductSupplyDTO $NewProductSupplyDTO */
            $NewProductSupplyDTO = $event->getData();

            $NewProductSupplyDTO->getPersonal()->setUsr($this->userProfileTokenStorage->getUser());
            $NewProductSupplyDTO->getPersonal()->setProfile($this->userProfileTokenStorage->getProfile());
        });

        /**
         * Неизменяемые данные
         */
        $builder->add('invariable', NewProductSupplyInvariableForm::class, ['label' => false]);

        /**
         * Продукты в поставке
         */

        /** Динамический поля продукта - для создания элементов коллекции продуктов */
        $builder->add('preProduct', PreProductForm::class, ['label' => false]);

        /** Коллекция */
        $builder->add('product', CollectionType::class, [
            'entry_type' => NewProductSupplyProductForm::class,
            'entry_options' => ['label' => false],
            'label' => false,
            'by_reference' => false,
            'allow_delete' => true,
            'allow_add' => true,
            'prototype_name' => '__product__',
        ]);

        $builder->add('files', ProductSupplyFilesForm::class, ['label' => false]);

        /**
         * Сохранить
         */
        $builder->add(
            'product_supply_new',
            SubmitType::class,
            ['label' => 'Save', 'label_html' => true, 'attr' => ['class' => 'btn-primary']]
        );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => NewProductSupplyDTO::class,
            'method' => 'POST',
            'attr' => ['class' => 'w-100'],
        ]);
    }
}