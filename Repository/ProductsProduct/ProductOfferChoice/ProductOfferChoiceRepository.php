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

namespace BaksDev\Products\Supply\Repository\ProductsProduct\ProductOfferChoice;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Products\Category\Entity\Offers\CategoryProductOffers;
use BaksDev\Products\Category\Entity\Offers\Trans\CategoryProductOffersTrans;
use BaksDev\Products\Product\Entity\Info\ProductInfo;
use BaksDev\Products\Product\Entity\Offers\ProductOffer;
use BaksDev\Products\Product\Entity\Product;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\Profile\UserProfile\Repository\UserProfileTokenStorage\UserProfileTokenStorageInterface;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Generator;
use InvalidArgumentException;

final class ProductOfferChoiceRepository implements ProductOfferChoiceInterface
{
    private ProductUid|false $product = false;

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

    public function forProduct(Product|ProductUid $product): self
    {
        if($product instanceof Product)
        {
            $product = $product->getId();
        }

        $this->product = $product;

        return $this;
    }

    public function findAll(): Generator
    {
        if(false === ($this->product instanceof ProductUid))
        {
            throw new InvalidArgumentException('Не передан обязательный параметр запроса product');
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
            'offer.event = product.event'
        );

        $dbal->join(
            'offer',
            CategoryProductOffers::class,
            'category_offer',
            'category_offer.id = offer.category_offer',
        );

        $dbal->leftJoin(
            'category_offer',
            CategoryProductOffersTrans::class,
            'category_offer_trans',
            '
                category_offer_trans.offer = category_offer.id AND 
                category_offer_trans.local = :local',
        );

        $dbal
            ->select('offer.const AS value')
            ->groupBy('offer.const');

        $dbal
            ->addSelect('offer.value AS attr')
            ->addGroupBy('offer.value');

        $dbal
            ->addSelect('category_offer_trans.name AS option')
            ->addGroupBy('category_offer_trans.name');

        $dbal
            ->addSelect('offer.postfix AS characteristic')
            ->addGroupBy('offer.postfix');

        $dbal
            ->addSelect('category_offer.reference AS reference')
            ->addGroupBy('category_offer.reference');

        return $dbal
            ->enableCache('products-product', '1 day')
            ->fetchAllHydrate(ProductOfferConst::class);
    }
}