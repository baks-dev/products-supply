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

namespace BaksDev\Products\Supply\Repository\OneProductSupplyProduct;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Products\Product\Entity\Product;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Supply\Entity\Event\Product\ProductSupplyProduct;
use BaksDev\Products\Supply\Entity\Event\ProductSupplyEvent;
use BaksDev\Products\Supply\Entity\ProductSupply;
use BaksDev\Products\Supply\Type\ProductSupplyUid;
use InvalidArgumentException;

final class OneProductSupplyProductRepository implements OneProductSupplyProductInterface
{
    private ProductSupplyUid|false $supply = false;

    /** Фильтр по продукту */

    private ProductUid|false $product = false;

    private ProductOfferConst|false $offer = false;

    private ProductVariationConst|false $variation = false;

    private ProductModificationConst|false $modification = false;

    public function __construct(
        private readonly DBALQueryBuilder $DBALQueryBuilder,
    ) {}

    public function forSupply(ProductSupplyUid $supply): self
    {
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

    /**
     * Возвращает продукт из поставки по его идентификаторам
     */
    public function find(): OneProductSupplyProductResult|false
    {
        if(false === $this->supply instanceof ProductSupplyUid)
        {
            throw new InvalidArgumentException('Не передан обязательный параметр запроса ProductSupplyUid');
        }

        if(false === $this->product instanceof ProductUid)
        {
            throw new InvalidArgumentException('Не передан обязательный параметр запроса ProductUid');
        }

        $dbal = $this->DBALQueryBuilder->createQueryBuilder(self::class);

        $dbal->from(ProductSupply::class, 'main');

        $dbal
            ->addSelect('event.main AS id')
            ->addSelect('event.id AS event')
            ->addSelect('event.status')
            ->join(
                'main',
                ProductSupplyEvent::class,
                'event',
                '
                    event.id = main.event AND
                    event.main = :supply
                '
            )->setParameter(
                key: 'supply',
                value: $this->supply,
                type: ProductSupplyUid::TYPE,
            );

        /** Продукт в Поставке */

        $productParam = '= :product';
        $dbal->setParameter('product', $this->product, ProductUid::TYPE);

        $offerParam = ' IS NULL';
        $variationParam = ' IS NULL';
        $modificationParam = ' IS NULL';

        if(true === ($this->offer instanceof ProductOfferConst))
        {
            $offerParam = '= :offer';
            $dbal->setParameter('offer', $this->offer, ProductOfferConst::TYPE);
        }

        if(true === ($this->variation instanceof ProductVariationConst))
        {
            $variationParam = '= :variation';
            $dbal->setParameter('variation', $this->variation, ProductVariationConst::TYPE);
        }

        if(true === ($this->modification instanceof ProductModificationConst))
        {
            $modificationParam = '= :modification';
            $dbal->setParameter('modification', $this->modification, ProductModificationConst::TYPE);
        }

        $productCondition = 'product.event = event.id AND 
                    product.product '.$productParam.' AND
                    product.offer_const '.$offerParam.' AND
                    product.variation_const '.$variationParam.' AND
                    product.modification_const '.$modificationParam;

        $dbal
            ->addSelect('product.product')
            ->addSelect('product.offer_const')
            ->addSelect('product.variation_const')
            ->addSelect('product.modification_const')
            ->addSelect('product.received')
            ->join(
                'event',
                ProductSupplyProduct::class,
                'product',
                $productCondition
            )->setParameter(
                key: 'product',
                value: $this->product,
                type: ProductUid::TYPE,
            );

        return $dbal->fetchHydrate(OneProductSupplyProductResult::class);
    }
}