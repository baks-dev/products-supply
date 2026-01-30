<?php
/*
 *  Copyright 2026.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Products\Supply\Repository\AllProductSupplyProduct;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Products\Supply\Entity\Event\Product\ProductSupplyProduct;
use BaksDev\Products\Supply\Entity\Event\ProductSupplyEvent;
use BaksDev\Products\Supply\Entity\ProductSupply;
use BaksDev\Products\Supply\Type\ProductSupplyUid;
use InvalidArgumentException;

final class AllProductSupplyProductRepository implements AllProductSupplyProductInterface
{
    private ProductSupplyUid|false $supply = false;

    public function __construct(
        private readonly DBALQueryBuilder $DBALQueryBuilder,
    ) {}

    public function forSupply(ProductSupplyUid $supply): self
    {
        $this->supply = $supply;
        return $this;
    }

    /**
     * Возвращает продукты из поставки
     */
    public function findAll(): AllProductSupplyProductResult|false
    {
        if(false === $this->supply instanceof ProductSupplyUid)
        {
            throw new InvalidArgumentException('Не передан обязательный параметр запроса ProductSupplyUid');
        }

        $dbal = $this->DBALQueryBuilder->createQueryBuilder(self::class);

        $dbal->from(ProductSupply::class, 'main');

        $dbal
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

        /** Продукты в Поставке */
        $dbal
            ->join(
                'event',
                ProductSupplyProduct::class,
                'product',
                'product.event = event.id'
            );

        $dbal->addSelect(
            "JSON_AGG
			( 
					JSONB_BUILD_OBJECT
					(
						'product', product.product,
						'offer_const', product.offer_const,
						'variation_const', product.variation_const,
						'modification_const', product.modification_const,
						'total', product.total,
						'received', product.received
					)
			)
			AS supply_products",
        );

        $dbal->allGroupByExclude();

        return $dbal->fetchHydrate(AllProductSupplyProductResult::class);
    }
}