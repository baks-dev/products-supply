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

namespace BaksDev\Products\Supply\UseCase\Admin\Edit\Product;

use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class EditProductSupplyProductForm extends AbstractType
{
    public function __construct() {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('product', HiddenType::class);

        $builder->get('product')->addModelTransformer(
            new CallbackTransformer(
                function(?ProductUid $product) {
                    return $product instanceof ProductUid ? $product->getValue() : $product;
                },
                function(string $product) {
                    return new ProductUid($product);
                }
            )
        );

        $builder->add('offerConst', HiddenType::class);

        $builder->get('offerConst')->addModelTransformer(
            new CallbackTransformer(
                function(?ProductOfferConst $offer) {
                    return $offer instanceof ProductOfferConst ? $offer->getValue() : $offer;
                },
                function(string $offer) {
                    return new ProductOfferConst($offer);
                }
            )
        );

        $builder->add('variationConst', HiddenType::class);

        $builder->get('variationConst')->addModelTransformer(
            new CallbackTransformer(
                function(?ProductVariationConst $variation) {
                    return $variation instanceof ProductVariationConst ? $variation->getValue() : $variation;
                },
                function(string $variation) {
                    return new ProductVariationConst($variation);
                }
            )
        );

        $builder->add('modificationConst', HiddenType::class);

        $builder->get('modificationConst')->addModelTransformer(
            new CallbackTransformer(
                function(?ProductModificationConst $modification) {
                    return $modification instanceof ProductModificationConst ? $modification->getValue() : $modification;
                },
                function(string $modification) {
                    return new ProductModificationConst ($modification);
                }
            )
        );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => EditProductSupplyProductDTO::class,
            'method' => 'POST',
            'attr' => ['class' => 'w-100'],
        ]);
    }
}
