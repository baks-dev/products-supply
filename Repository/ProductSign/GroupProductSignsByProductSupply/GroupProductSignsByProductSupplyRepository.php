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

namespace BaksDev\Products\Supply\Repository\ProductSign\GroupProductSignsByProductSupply;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Products\Category\Entity\Offers\CategoryProductOffers;
use BaksDev\Products\Category\Entity\Offers\Variation\CategoryProductVariation;
use BaksDev\Products\Category\Entity\Offers\Variation\Modification\CategoryProductModification;
use BaksDev\Products\Product\Entity\Event\ProductEvent;
use BaksDev\Products\Product\Entity\Info\ProductInfo;
use BaksDev\Products\Product\Entity\Offers\ProductOffer;
use BaksDev\Products\Product\Entity\Offers\Variation\Modification\ProductModification;
use BaksDev\Products\Product\Entity\Offers\Variation\ProductVariation;
use BaksDev\Products\Product\Entity\Product;
use BaksDev\Products\Product\Entity\Trans\ProductTrans;
use BaksDev\Products\Sign\Entity\Code\ProductSignCode;
use BaksDev\Products\Sign\Entity\Event\ProductSignEvent;
use BaksDev\Products\Sign\Entity\Event\Supply\ProductSignSupply;
use BaksDev\Products\Sign\Entity\Invariable\ProductSignInvariable;
use BaksDev\Products\Sign\Entity\ProductSign;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus\Collection\ProductSignStatusInterface;
use BaksDev\Products\Supply\Entity\ProductSupply;
use BaksDev\Products\Supply\Type\ProductSupplyUid;
use Generator;
use InvalidArgumentException;

final class GroupProductSignsByProductSupplyRepository implements GroupProductSignsByProductSupplyInterface
{
    private ProductSupplyUid|false $supply = false;

    private ProductSignStatus|false $status = false;

    public function __construct(private readonly DBALQueryBuilder $DBALQueryBuilder) {}

    /** Идентификатор поставки */
    public function forSupply(ProductSupply|ProductSupplyUid $supply): self
    {
        if($supply instanceof ProductSupply)
        {
            $supply = $supply->getId();
        }

        $this->supply = $supply;
        return $this;
    }

    /** Статус Честного знака */
    public function forStatus(ProductSignStatus|ProductSignStatusInterface|string $status): self
    {
        if(false === $status instanceof ProductSignStatus)
        {
            $status = new ProductSignStatus($status);
        }

        $this->status = $status;
        return $this;
    }

    /**
     * Находит Честные знаки, забронированные для поставки и группирует их по id поставки
     *
     * @return Generator<int, GroupProductSignsByProductSupplyResult>|false
     */
    public function findAll(): Generator|false
    {
        if(false === ($this->supply instanceof ProductSupplyUid))
        {
            throw new InvalidArgumentException('Не передан обязательный параметр запроса supply');
        }

        if(false === ($this->status instanceof ProductSignStatus))
        {
            throw new InvalidArgumentException('Не передан обязательный параметр запроса status');
        }

        $dbal = $this->DBALQueryBuilder
            ->createQueryBuilder(self::class)
            ->bindLocal();

        // /** Через событие */
        //        $dbal->from(ProductSignEvent::class, 'event');
        //
        //        $dbal
        //            ->join(
        //                'event',
        //                ProductSign::class,
        //                'main',
        //                'main.event = event.id'
        //            );

        //        $dbal
        //            ->where('event.status = :status')
        //            ->setParameter(
        //                key: 'status',
        //                value: ProductSignStatusSupply::STATUS,
        //                type: ProductSignStatus::TYPE
        //            );

        $dbal->from(ProductSign::class, 'main');

        $dbal
            ->join(
                'main',
                ProductSignEvent::class,
                'event',
                '
                    event.id = main.event AND
                    event.status = :status 
                ',
            )->setParameter(
                key: 'status',
                value: $this->status,
                type: ProductSignStatus::TYPE,
            );

        /** Только для конкретной поставки */
        $dbal
            ->addSelect('sign_supply.supply as product_supply_id')
            ->join(
                'event',
                ProductSignSupply::class,
                'sign_supply',
                '
                    sign_supply.event = main.event AND
                    sign_supply.supply = :supply',
            )
            ->setParameter(
                'supply',
                $this->supply,
                ProductSupplyUid::TYPE,
            );

        $dbal
            ->addSelect('COUNT(*) AS counter')
            ->addSelect('invariable.part AS sign_part')
            ->join(
                'event',
                ProductSignInvariable::class,
                'invariable',
                'invariable.main = main.id',
            );

        $dbal
            ->leftJoin(
                'invariable',
                ProductSignCode::class,
                'code',
                'code.main = main.id',
            );

        /** Product */
        $dbal
            ->addSelect('product.id as product_id')
            ->join(
                'invariable',
                Product::class,
                'product',
                'product.id = invariable.product',
            );

        $dbal->join(
            'product',
            ProductEvent::class,
            'product_event',
            'product_event.id = product.event',
        );

        /**
         * Продукт
         */

        $dbal
            ->leftJoin(
                'product',
                ProductInfo::class,
                'product_info',
                'product_info.product = product.id',
            );

        $dbal
            ->addSelect('product_trans.name as product_name')
            ->join(
                'product',
                ProductTrans::class,
                'product_trans',
                'product_trans.event = product.event AND product_trans.local = :local',
            );

        /**
         * Торговое предложение
         */
        $dbal
            ->addSelect('product_offer.const as product_offer_const')
            ->addSelect('product_offer.value as product_offer_value')
            ->addSelect('product_offer.postfix as product_offer_postfix')
            ->leftJoin(
                'product',
                ProductOffer::class,
                'product_offer',
                'product_offer.event = product.event AND product_offer.const = invariable.offer',
            );

        /** Получаем тип торгового предложения */
        $dbal
            ->addSelect('category_offer.reference as product_offer_reference')
            ->leftJoin(
                'product_offer',
                CategoryProductOffers::class,
                'category_offer',
                'category_offer.id = product_offer.category_offer',
            );

        /** Множественные варианты торгового предложения */
        $dbal
            ->addSelect('product_variation.const as product_variation_const')
            ->addSelect('product_variation.value as product_variation_value')
            ->addSelect('product_variation.postfix as product_variation_postfix')
            ->leftJoin(
                'product_offer',
                ProductVariation::class,
                'product_variation',
                'product_variation.offer = product_offer.id AND product_variation.const = invariable.variation',
            );

        /** Получаем тип множественного варианта */
        $dbal
            ->addSelect('category_variation.reference as product_variation_reference')
            ->leftJoin(
                'product_variation',
                CategoryProductVariation::class,
                'category_variation',
                'category_variation.id = product_variation.category_variation',
            );

        /** Модификация множественного варианта торгового предложения */
        $dbal
            ->addSelect('product_modification.const as product_modification_const')
            ->addSelect('product_modification.value as product_modification_value')
            ->addSelect('product_modification.postfix as product_modification_postfix')
            ->leftJoin(
                'product_variation',
                ProductModification::class,
                'product_modification',
                'product_modification.variation = product_variation.id AND product_modification.const = invariable.modification',
            );

        /** Получаем тип модификации множественного варианта */
        $dbal
            ->addSelect('category_offer_modification.reference as product_modification_reference')
            ->leftJoin(
                'product_modification',
                CategoryProductModification::class,
                'category_offer_modification',
                'category_offer_modification.id = product_modification.category_modification',
            );

        /** Артикул продукта */
        $dbal->addSelect(
            '
            COALESCE(
                product_modification.article,
                product_variation.article,
                product_offer.article,
                product_info.article
            ) AS product_article',
        );

        $dbal
            ->addOrderBy('product_modification.value')
            ->addOrderBy('product_variation.value')
            ->addOrderBy('product_offer.value')
            ->addOrderBy('product.id');

        $dbal->allGroupByExclude();

        $result = $dbal->fetchAllHydrate(GroupProductSignsByProductSupplyResult::class);

        return true === $result->valid() ? $result : false;
    }
}