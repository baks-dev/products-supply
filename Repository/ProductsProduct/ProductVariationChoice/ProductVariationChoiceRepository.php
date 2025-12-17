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

namespace BaksDev\Products\Supply\Repository\ProductsProduct\ProductVariationChoice;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Products\Category\Entity\Offers\Variation\CategoryProductVariation;
use BaksDev\Products\Category\Entity\Offers\Variation\Trans\CategoryProductVariationTrans;
use BaksDev\Products\Product\Entity\Info\ProductInfo;
use BaksDev\Products\Product\Entity\Offers\ProductOffer;
use BaksDev\Products\Product\Entity\Offers\Variation\ProductVariation;
use BaksDev\Products\Product\Entity\Product;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\Profile\UserProfile\Repository\UserProfileTokenStorage\UserProfileTokenStorageInterface;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Generator;
use InvalidArgumentException;

final class ProductVariationChoiceRepository implements ProductVariationChoiceInterface
{
    private ProductUid|false $product = false;

    private ProductOfferConst|false $offer = false;

    private UserProfileUid|false $profile = false;

    public function __construct(
        private readonly DBALQueryBuilder $DBALQueryBuilder,
        private readonly UserProfileTokenStorageInterface $UserProfileTokenStorage,
    ) {}

    public function forProfile(UserProfile|UserProfileUid|null|false $profile): self
    {
        if(empty($profile))
        {
            $this->profile = false;
            return $this;
        }

        if($profile instanceof UserProfile)
        {
            $profile = $profile->getId();
        }

        $this->profile = $profile;

        return $this;
    }

    public function product(Product|ProductUid|null|false $product): self
    {
        if(empty($product))
        {
            $this->product = false;
            return $this;
        }

        if($product instanceof Product)
        {
            $product = $product->getId();
        }

        $this->product = $product;

        return $this;
    }

    public function offerConst(ProductOfferConst|null|false $offer): self
    {
        if(empty($offer))
        {
            $this->offer = false;
            return $this;
        }

        $this->offer = $offer;

        return $this;
    }

    public function findAll(): Generator
    {
        if(false === ($this->product instanceof ProductUid))
        {
            throw new InvalidArgumentException('Не передан обязательный параметр запроса product');
        }

        if(false === ($this->offer instanceof ProductOfferConst))
        {
            throw new InvalidArgumentException('Не передан обязательный параметр запроса offer');
        }

        $dbal = $this->DBALQueryBuilder
            ->createQueryBuilder(self::class)
            ->bindLocal();

        /**
         * Product
         */

        $dbal
            ->from(Product::class, 'product')
            ->where('product.id = :product')
            ->setParameter(
                key: 'product',
                value: $this->product,
                type: ProductUid::TYPE,
            );

        $dbal
            ->join(
                'product',
                ProductInfo::class,
                'info',
                'info.product = product.id',
            )
            ->setParameter(
                key: 'profile',
                value: $this->profile instanceof UserProfileUid
                    ? $this->profile
                    : $this->UserProfileTokenStorage->getProfile(),
                type: UserProfileUid::TYPE,
            );

        /**
         * Offer
         */

        $dbal->join(
            'product',
            ProductOffer::class,
            'offer',
            '
                offer.event = product.event AND
                offer.const = :const',
        )->setParameter(
            key: 'const',
            value: $this->offer,
            type: ProductOfferConst::TYPE,
        );

        /**
         * Variation
         */

        $dbal->join(
            'product',
            ProductVariation::class,
            'variation',
            'variation.offer = offer.id',
        );

        $dbal
            ->join(
                'variation',
                CategoryProductVariation::class,
                'category_variation',
                'category_variation.id = variation.category_variation',
            );

        $dbal
            ->leftJoin(
                'category_variation',
                CategoryProductVariationTrans::class,
                'category_variation_trans',
                '
                    category_variation_trans.variation = category_variation.id AND 
                    category_variation_trans.local = :local',
            );

        $dbal
            ->select('variation.const AS value')
            ->groupBy('variation.const');

        $dbal
            ->addSelect('variation.value AS attr')
            ->addGroupBy('variation.value');

        $dbal
            ->addSelect('category_variation_trans.name AS option')
            ->addGroupBy('category_variation_trans.name');

        $dbal
            ->addSelect('variation.postfix AS characteristic')
            ->addGroupBy('variation.postfix');

        $dbal
            ->addSelect('category_variation.reference AS reference')
            ->addGroupBy('category_variation.reference');

        return $dbal
            ->enableCache('products-products', '1 day')
            ->fetchAllHydrate(ProductVariationConst::class);
    }
}