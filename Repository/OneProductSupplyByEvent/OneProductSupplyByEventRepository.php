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

namespace BaksDev\Products\Supply\Repository\OneProductSupplyByEvent;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Products\Supply\Entity\Event\Invariable\ProductSupplyInvariable;
use BaksDev\Products\Supply\Entity\Event\Product\ProductSupplyProduct;
use BaksDev\Products\Supply\Entity\Event\ProductSupplyEvent;
use BaksDev\Products\Supply\Entity\ProductSupply;
use BaksDev\Products\Supply\Type\ProductSupplyUid;

final readonly class OneProductSupplyByEventRepository implements OneProductSupplyByEventInterface
{
    public function __construct(
        private DBALQueryBuilder $DBALQueryBuilder,
    ) {}

    /** Возвращает информацию об одной поставке */
    public function find(ProductSupply|ProductSupplyUid $supply): OneProductSupplyResult|false
    {
        $dbal = $this->DBALQueryBuilder->createQueryBuilder(self::class);

        $dbal
            ->addSelect('main.id')
            ->addSelect('main.event')
            ->from(ProductSupply::class, 'main');

        $dbal
            ->addSelect('event.status as supply_status')
            ->join(
                'main',
                ProductSupplyEvent::class,
                'event',
                '
                    event.id = main.event AND 
                    event.main = :supply'
            )
            ->setParameter(
                key: 'supply',
                value: ($supply instanceof ProductSupply ? $supply->getId() : $supply),
                type: ProductSupplyUid::TYPE,
            );

        /** Invariable */
        $dbal
            ->addSelect('product_supply_invariable.number AS supply_number')
            ->addSelect('product_supply_invariable.declaration AS supply_declaration')
            ->join(
                'event',
                ProductSupplyInvariable::class,
                'product_supply_invariable',
                'product_supply_invariable.event = event.id'
            );

        /** Продукты в Поставке */
        $dbal
            ->join(
                'event',
                ProductSupplyProduct::class,
                'product_supply_product',
                'product_supply_product.event = event.id'
            );

        $dbal->addSelect(
            "JSON_AGG
			( 
					JSONB_BUILD_OBJECT
					(
						'product', product_supply_product.product,
						'offer_const', product_supply_product.offer_const,
						'variation_const', product_supply_product.variation_const,
						'modification_const', product_supply_product.modification_const,
						'total', product_supply_product.total,
						'received', product_supply_product.received
					)
			)
			AS supply_products",
        );

        $dbal->allGroupByExclude();

        return $dbal->fetchHydrate(OneProductSupplyResult::class);
    }
}