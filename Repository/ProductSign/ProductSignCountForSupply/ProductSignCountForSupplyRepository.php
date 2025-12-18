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

namespace BaksDev\Products\Supply\Repository\ProductSign\ProductSignCountForSupply;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Products\Product\Entity\Product;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Sign\Entity\Event\ProductSignEvent;
use BaksDev\Products\Sign\Entity\Event\Supply\ProductSignSupply;
use BaksDev\Products\Sign\Entity\Invariable\ProductSignInvariable;
use BaksDev\Products\Sign\Entity\ProductSign;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus;
use BaksDev\Products\Supply\Entity\ProductSupply;
use BaksDev\Products\Supply\Type\ProductSign\Status\ProductSignStatusSupply;
use BaksDev\Products\Supply\Type\ProductSupplyUid;
use InvalidArgumentException;

final class ProductSignCountForSupplyRepository implements ProductSignCountForSupplyInterface
{
    /** Фильтр по поставке */

    private ProductSupplyUid|false $supply = false;

    private string|false $part = false;

    private ProductSignStatus $status;

    /** Фильтр по продукту */

    private ProductUid|false $product = false;

    private ProductOfferConst|false $offer = false;

    private ProductVariationConst|false $variation = false;

    private ProductModificationConst|false $modification = false;

    public function __construct(
        private readonly DBALQueryBuilder $DBALQueryBuilder
    )
    {
        /** По умолчанию возвращаем знаки со статусом Supply «Поставка» */
        $this->status = new ProductSignStatus(ProductSignStatusSupply::class);
    }

    public function forPart(string $part): self
    {
        $this->part = $part;
        return $this;
    }

    public function forSupply(ProductSupply|ProductSupplyUid $supply): self
    {
        if($supply instanceof ProductSupply)
        {
            $supply = $supply->getId();
        }

        $this->supply = $supply;
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

    public function forOffer(ProductOfferConst|null $offer): self
    {
        if(null === $offer)
        {
            $this->offer = false;
            return $this;
        }

        $this->offer = $offer;
        return $this;
    }

    public function forVariation(ProductVariationConst|null $variation): self
    {
        if(null === $variation)
        {
            $this->variation = false;
            return $this;
        }

        $this->variation = $variation;
        return $this;
    }

    public function forModification(ProductModificationConst|null $modification): self
    {
        if(null === $modification)
        {
            $this->modification = false;
            return $this;
        }

        $this->modification = $modification;
        return $this;
    }

    public function count(): int
    {
        if(false === ($this->supply instanceof ProductSupplyUid))
        {
            throw new InvalidArgumentException('Не передан обязательный параметр запроса supply');
        }

        $dbal = $this->DBALQueryBuilder->createQueryBuilder(self::class);

        /** Активное событие */
        $dbal
            ->from(ProductSignEvent::class, 'event');

        /** Статус */
        $dbal
            ->where('event.status = :status')
            ->setParameter('status', $this->status, ProductSignStatus::TYPE);

        $dbal
            ->join(
                'event',
                ProductSign::class,
                'main',
                'main.event = event.id'
            );

        /** Только для конкретной поставки */
        $dbal
            ->join(
                'event',
                ProductSignSupply::class,
                'sign_supply',
                '
                    sign_supply.event = main.event AND
                    sign_supply.supply = :supply'
            )
            ->setParameter(
                'supply',
                $this->supply,
                ProductSupplyUid::TYPE
            );

        /**
         * Продукт
         */

        $invariableCondition = 'invariable.main = main.id';

        if(true === ($this->product instanceof ProductUid))
        {
            $productParam = '= :product';
            $dbal->setParameter('product', $this->product, ProductUid::TYPE);

            $offerParam = ' IS NULL';
            $variationParam = ' IS NULL';
            $modificationParam = ' IS NULL';

            /**
             * Offer
             */
            if(true === ($this->offer instanceof ProductOfferConst))
            {
                $offerParam = '= :offer';
                $dbal->setParameter('offer', $this->offer, ProductOfferConst::TYPE);
            }

            /**
             * Variation
             */
            if(true === ($this->variation instanceof ProductVariationConst))
            {
                $variationParam = '= :variation';
                $dbal->setParameter('variation', $this->variation, ProductVariationConst::TYPE);
            }

            /**
             * Modification
             */
            if(true === ($this->modification instanceof ProductModificationConst))
            {
                $modificationParam = '= :modification';
                $dbal->setParameter('modification', $this->modification, ProductModificationConst::TYPE);
            }

            $invariableCondition = 'invariable.main = main.id AND 
                    invariable.product '.$productParam.' AND
                    invariable.offer '.$offerParam.' AND
                    invariable.variation '.$variationParam.' AND
                    invariable.modification '.$modificationParam;
        }

        /** По номеру партии */
        if(false !== $this->part)
        {
            $invariableCondition .= ' AND invariable.part = :part';
            $dbal
                ->setParameter(
                    key: 'part',
                    value: $this->part,
                );
        }

        $dbal
            ->join(
                'event',
                ProductSignInvariable::class,
                'invariable',
                $invariableCondition
            );

        return $dbal->count();
    }
}
