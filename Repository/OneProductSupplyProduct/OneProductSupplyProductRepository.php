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
use BaksDev\Products\Product\Type\Invariable\ProductInvariableUid;
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

    private ProductInvariableUid|false $product = false;


    public function __construct(
        private readonly DBALQueryBuilder $DBALQueryBuilder,
    ) {}

    public function forSupply(ProductSupplyUid $supply): self
    {
        $this->supply = $supply;
        return $this;
    }

    public function forProduct(ProductInvariableUid $product): self
    {
        $this->product = $product;
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

        if(false === $this->product instanceof ProductInvariableUid)
        {
            throw new InvalidArgumentException('Не передан обязательный параметр запроса ProductInvariableUid');
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
                ',
            )->setParameter(
                key: 'supply',
                value: $this->supply,
                type: ProductSupplyUid::TYPE,
            );

        /** Продукт в Поставке */

        $dbal
            ->addSelect('product.product')
            ->addSelect('product.received')
            ->join(
                'event',
                ProductSupplyProduct::class,
                'product',
                'product.event = event.id AND product.product = :product',
            )
            ->setParameter(
                key: 'product',
                value: $this->product,
                type: ProductInvariableUid::TYPE,
            );

        return $dbal->fetchHydrate(OneProductSupplyProductResult::class);
    }
}