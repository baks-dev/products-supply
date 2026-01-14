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

namespace BaksDev\Products\Supply\UseCase\Admin\New\PreProduct;

use BaksDev\Products\Category\Repository\CategoryChoice\CategoryChoiceInterface;
use BaksDev\Products\Category\Type\Id\CategoryProductUid;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Supply\Repository\ProductsProduct\ProductChoice\ProductChoiceInterface;
use BaksDev\Products\Supply\Repository\ProductsProduct\ProductModificationChoice\ProductModificationChoiceInterface;
use BaksDev\Products\Supply\Repository\ProductsProduct\ProductOfferChoice\ProductOfferChoiceInterface;
use BaksDev\Products\Supply\Repository\ProductsProduct\ProductVariationChoice\ProductVariationChoiceInterface;
use BaksDev\Users\Profile\UserProfile\Repository\UserProfileTokenStorage\UserProfileTokenStorageInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

final class PreProductForm extends AbstractType
{
    public function __construct(
        #[AutowireIterator('baks.reference.choice')] private readonly iterable $reference,
        private readonly UserProfileTokenStorageInterface $UserProfileTokenStorage,
        private readonly CategoryChoiceInterface $categoryChoiceRepository,
        private readonly ProductChoiceInterface $productChoiceRepository,
        private readonly ProductOfferChoiceInterface $productOfferChoiceRepository,
        private readonly ProductVariationChoiceInterface $productVariationChoiceRepository,
        private readonly ProductModificationChoiceInterface $productModificationChoiceRepository,
        private readonly TranslatorInterface $translator
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /**
         * Категория
         */

        $builder->add('category', ChoiceType::class, [
            'choices' => $this->categoryChoiceRepository->findAll(),
            'choice_value' => function(?CategoryProductUid $category) {
                return $category?->getValue();
            },
            'choice_label' => function(CategoryProductUid $category) {
                return (is_int($category->getAttr()) ? str_repeat(' - ', $category->getAttr() - 1) : '').$category->getOptions();
            },
            'label' => false,
            'required' => false,
        ]);

        /**
         * Продукция
         */

        $builder->add('preProduct', HiddenType::class);

        $builder->get('preProduct')->addModelTransformer(
            new CallbackTransformer(
                function($product) {
                    return $product instanceof ProductUid ? $product->getValue() : $product;
                },
                function($product) {
                    return $product ? new ProductUid($product) : null;
                },
            ),
        );

        /**
         * Торговые предложения
         */

        $builder->add('preOfferConst', HiddenType::class);

        $builder->get('preOfferConst')->addModelTransformer(
            new CallbackTransformer(
                function($offer) {
                    return $offer instanceof ProductOfferConst ? $offer->getValue() : $offer;
                },
                function($offer) {
                    return $offer ? new ProductOfferConst($offer) : null;
                },
            ),
        );

        /**
         * Множественный вариант торгового предложения
         */

        $builder->add('preVariationConst', HiddenType::class);

        $builder->get('preVariationConst')->addModelTransformer(
            new CallbackTransformer(
                function(?ProductVariationConst $variation) {
                    return $variation instanceof ProductVariationConst ? $variation->getValue() : $variation;
                },
                function(?string $variation) {
                    return $variation ? new ProductVariationConst($variation) : null;
                },
            ),
        );

        /**
         * Модификация множественного варианта торгового предложения
         */

        $builder->add('preModificationConst', HiddenType::class);

        $builder->get('preModificationConst')->addModelTransformer(
            new CallbackTransformer(
                function($modification) {
                    return $modification instanceof ProductModificationConst ? $modification->getValue() : $modification;
                },
                function($modification) {
                    return $modification ? new ProductModificationConst($modification) : null;
                },
            ),
        );

        /**
         * Количество
         */
        $builder->add('preTotal', IntegerType::class, ['required' => false]);

        /**
         * Событие на изменение
         */

        $builder->get('preVariationConst')->addEventListener(
            FormEvents::POST_SUBMIT,
            function(FormEvent $event): void {

                $parent = $event->getForm()->getParent();

                if(!$parent)
                {
                    return;
                }

                $category = $parent->get('category')->getData();
                $product = $parent->get('preProduct')->getData();
                $offer = $parent->get('preOfferConst')->getData();
                $variation = $parent->get('preVariationConst')->getData();

                if($category)
                {
                    $this->formProductModifier($event->getForm()->getParent(), $category);
                }

                if($product)
                {
                    $this->formOfferModifier($event->getForm()->getParent(), $product);
                }

                if($offer)
                {
                    $this->formVariationModifier($event->getForm()->getParent(), $product, $offer);
                }

                if($variation)
                {
                    $this->formModificationModifier($event->getForm()->getParent(), $product, $offer, $variation);
                }
            },
        );

    }

    /**
     * Изменение продукта
     */
    private function formProductModifier(FormInterface $form, CategoryProductUid $category): void
    {
        /** Список имеющейся продукции только у текущего профиля */
        $productChoice = $this->productChoiceRepository
            ->forProfile($this->UserProfileTokenStorage->getProfile())
            ->forCategory($category)
            ->findAll();

        $form->add(
            'preProduct',
            ChoiceType::class,
            [
                'choices' => $productChoice,
                'choice_value' => function(?ProductUid $product) {
                    return $product?->getValue();
                },
                'choice_label' => function(ProductUid $product) {
                    return $product->getAttr();
                },
                'choice_attr' => function(?ProductUid $product) {

                    if(!$product)
                    {
                        return [];
                    }

                    if($product)
                    {
                        $attr['data-name'] = $product->getAttr();
                    }

                    if($product?->getOption())
                    {
                        $attr['data-filter'] = '('.$product->getOption().')';
                    }

                    return $attr;
                },
                'label' => false,
            ],
        );
    }

    /**
     * Изменение ТП
     */
    private function formOfferModifier(FormInterface $form, ProductUid $product): void
    {
        /** Список торговых предложений продукции */
        $offer = $this->productOfferChoiceRepository
            ->forProfile($this->UserProfileTokenStorage->getProfile())
            ->forProduct($product)
            ->findAll();

        /** Если список пустой - возвращаем hidden поле */
        if(false === $offer->valid())
        {
            $form->add('preOfferConst', HiddenType::class, ['data' => null]);
            return;
        }

        $currentOffer = $offer->current();
        $label = $currentOffer->getOption();
        $domain = null;

        if($currentOffer->getReference())
        {
            /** Если торговое предложение Справочник - ищем домен переводов */
            foreach($this->reference as $reference)
            {
                if($reference->type() === $currentOffer->getReference())
                {
                    $domain = $reference->domain();
                }
            }
        }

        $form
            ->add(
                'preOfferConst',
                ChoiceType::class,
                [
                    'choices' => $offer,
                    'choice_value' => function(?ProductOfferConst $offer) {
                        return $offer?->getValue();
                    },
                    'choice_label' => function(ProductOfferConst $offer) {
                        return $offer->getAttr();
                    },
                    'choice_attr' => function(?ProductOfferConst $offer) {

                        if(!$offer)
                        {
                            return [];
                        }

                        if($offer->getAttr())
                        {
                            $attr['data-name'] = $this->translator->trans(
                                id: $offer->getAttr(),
                                domain: $offer->getReference(),
                            );
                        }

                        if($offer?->getCharacteristic())
                        {
                            $attr['data-filter'] = '('.$offer->getCharacteristic().')';
                        }

                        return $attr;

                    },

                    'attr' => ['data-select' => 'select2'],
                    'label' => $label,
                    'translation_domain' => $domain,
                    'placeholder' => sprintf('Выберите %s из списка...', $label),
                ],
            );
    }

    /**
     * Изменение варианта ТП
     */
    private function formVariationModifier(FormInterface $form, ProductUid $product, ProductOfferConst $offer): void
    {
        /** Только текущего профиля */
        $variations = $this->productVariationChoiceRepository
            ->forProfile($this->UserProfileTokenStorage->getProfile())
            ->product($product)
            ->offerConst($offer)
            ->findAll();

        /** Если список пустой - возвращаем hidden поле */
        if(false === $variations->valid())
        {
            $form->add('preVariationConst', HiddenType::class);
            return;
        }

        $currentVariation = $variations->current();
        $label = $currentVariation->getOption();
        $domain = null;

        if($currentVariation->getReference())
        {
            /** Если торговое предложение Справочник - ищем домен переводов */
            foreach($this->reference as $reference)
            {
                if($reference->type() === $currentVariation->getReference())
                {
                    $domain = $reference->domain();
                }
            }
        }

        $form
            ->add(
                'preVariationConst',
                ChoiceType::class,
                [
                    'choices' => $variations,
                    'choice_value' => function(?ProductVariationConst $variation) {
                        return $variation?->getValue();
                    },
                    'choice_label' => function(ProductVariationConst $variation) {
                        return trim($variation->getAttr());
                    },
                    'choice_attr' => function(?ProductVariationConst $variation) {
                        if(!$variation)
                        {
                            return [];
                        }

                        if($variation->getAttr())
                        {
                            $attr['data-name'] = $this->translator->trans(
                                id: $variation->getAttr(),
                                domain: $variation->getReference(),
                            );
                        }

                        if($variation?->getCharacteristic())
                        {
                            $attr['data-filter'] = '('.$variation->getCharacteristic().')';
                        }

                        return $attr;
                    },
                    'attr' => ['data-select' => 'select2'],
                    'label' => $label,
                    'translation_domain' => $domain,
                    'placeholder' => sprintf('Выберите %s из списка...', $label),
                ],
            );
    }

    private function formModificationModifier(
        FormInterface $form,
        ProductUid $product,
        ProductOfferConst $offer,
        ProductVariationConst $variation,
    ): void
    {
        /** Список Modification по профилю */
        $modifications = $this->productModificationChoiceRepository
            ->forProfile($this->UserProfileTokenStorage->getProfile())
            ->product($product)
            ->offerConst($offer)
            ->variationConst($variation)
            ->findAll();

        /** Если список пустой - возвращаем hidden поле */
        if(false === $modifications->valid())
        {
            $form->add('preModificationConst', HiddenType::class);
            return;
        }

        $currentModification = $modifications->current();
        $label = $currentModification->getOption();
        $domain = null;

        if($currentModification->getReference())
        {
            /** Если Справочник - ищем домен переводов */
            foreach($this->reference as $reference)
            {
                if($reference->type() === $currentModification->getReference())
                {
                    $domain = $reference->domain();
                }
            }
        }

        $form
            ->add(
                'preModificationConst',
                ChoiceType::class,
                [
                    'choices' => $modifications,
                    'choice_value' => function(?ProductModificationConst $modification) {
                        return $modification?->getValue();
                    },
                    'choice_label' => function(ProductModificationConst $modification) {
                        return trim($modification->getAttr());
                    },
                    'choice_attr' => function(?ProductModificationConst $modification) {

                        if(!$modification)
                        {
                            return [];
                        }

                        if($modification->getAttr())
                        {
                            $attr['data-name'] = $this->translator->trans(
                                id: $modification->getAttr(),
                                domain: $modification->getReference(),
                            );
                        }

                        if($modification?->getCharacteristic())
                        {
                            $attr['data-filter'] = '('.$modification?->getCharacteristic().')';
                        }

                        return $attr;
                    },
                    'attr' => ['data-select' => 'select2'],
                    'label' => $label,
                    'translation_domain' => $domain,
                    'placeholder' => sprintf('Выберите %s из списка...', $label),
                ],
            );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PreProductDTO::class,
            'method' => 'POST',
            'attr' => ['class' => 'w-100'],
        ]);
    }
}