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

namespace BaksDev\Products\Supply\Repository\ProductsProduct\ProductModificationChoice;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Products\Category\Entity\Offers\Variation\Modification\CategoryProductModification;
use BaksDev\Products\Category\Entity\Offers\Variation\Modification\Trans\CategoryProductModificationTrans;
use BaksDev\Products\Product\Entity\Info\ProductInfo;
use BaksDev\Products\Product\Entity\Offers\ProductOffer;
use BaksDev\Products\Product\Entity\Offers\Variation\Modification\ProductModification;
use BaksDev\Products\Product\Entity\Offers\Variation\ProductVariation;
use BaksDev\Products\Product\Entity\Product;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\Profile\UserProfile\Repository\UserProfileTokenStorage\UserProfileTokenStorageInterface;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Generator;
use InvalidArgumentException;

final class ProductModificationChoiceRepository implements ProductModificationChoiceInterface
{
    private UserProfileUid|false $profile = false;

    private ProductUid|false $product = false;

    private ProductOfferConst|false $offer = false;

    private ProductVariationConst|false $variation = false;

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

    public function variationConst(ProductVariationConst|null|false $variation): self
    {
        if(empty($variation))
        {
            $this->variation = false;
            return $this;
        }

        $this->variation = $variation;

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

        if(false === ($this->variation instanceof ProductVariationConst))
        {
            throw new InvalidArgumentException('Не передан обязательный параметр запроса variation');
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
                offer.const = :offerConst',
        )->setParameter(
            key: 'offerConst',
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
            '
                variation.offer = offer.id AND
                variation.const = :variationConst',
        )->setParameter(
            key: 'variationConst',
            value: $this->variation,
            type: ProductVariationConst::TYPE,
        );

        /**
         * Modification
         */

        $dbal->join(
            'product',
            ProductModification::class,
            'modification',
            'modification.variation = variation.id',
        );

        $dbal->join(
            'modification',
            CategoryProductModification::class,
            'category_modification',
            'category_modification.id = modification.category_modification',
        );

        $dbal->leftJoin(
            'category_modification',
            CategoryProductModificationTrans::class,
            'category_modification_trans',
            '
                category_modification_trans.modification = category_modification.id AND
                category_modification_trans.local = :local',
        );

        $dbal
            ->select('modification.const AS value')
            ->groupBy('modification.const');

        $dbal
            ->addSelect('modification.value AS attr')
            ->addGroupBy('modification.value');

        $dbal
            ->addSelect('category_modification_trans.name AS option')
            ->addGroupBy('category_modification_trans.name');

        $dbal
            ->addSelect('modification.postfix AS characteristic')
            ->addGroupBy('modification.postfix');

        $dbal
            ->addSelect('category_modification.reference AS reference')
            ->addGroupBy('category_modification.reference');

        return $dbal
            ->enableCache('products-products', '1 day')
            ->fetchAllHydrate(ProductModificationConst::class);
    }
}