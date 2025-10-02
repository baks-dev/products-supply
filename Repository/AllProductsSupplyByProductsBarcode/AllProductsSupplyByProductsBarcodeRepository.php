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

namespace BaksDev\Products\Supply\Repository\AllProductsSupplyByProductsBarcode;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Products\Product\Entity\Info\ProductInfo;
use BaksDev\Products\Product\Entity\Offers\ProductOffer;
use BaksDev\Products\Product\Entity\Offers\Variation\Modification\ProductModification;
use BaksDev\Products\Product\Entity\Offers\Variation\ProductVariation;
use BaksDev\Products\Product\Entity\Product;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Supply\Entity\Event\Product\ProductSupplyProduct;
use BaksDev\Products\Supply\Entity\Event\ProductSupplyEvent;
use BaksDev\Products\Supply\Entity\ProductSupply;
use BaksDev\Products\Supply\Type\Status\ProductSupplyStatus;
use BaksDev\Products\Supply\Type\Status\ProductSupplyStatus\Collection\ProductSupplyStatusNew;
use Generator;
use InvalidArgumentException;

final class AllProductsSupplyByProductsBarcodeRepository implements AllProductsSupplyByProductsBarcodeInterface
{
    private ProductUid|false $product = false;

    public function __construct(private readonly DBALQueryBuilder $DBALQueryBuilder) {}

    /** Идентификатор продукта */
    public function forProduct(Product|ProductUid $product): self
    {
        if($product instanceof Product)
        {
            $product = $product->getId();
        }

        $this->product = $product;
        return $this;
    }

    /**
     * Возвращает идентификаторы продуктов,
     * у которых шрихкод совпадает со штрихкодом продукта из поставки
     *
     * @return Generator<int, AllProductsSupplyProductsBarcodeResult>|false
     */
    public function findAll(): Generator|false
    {
        $result = $this
            ->builder()
            ->fetchAllHydrate(AllProductsSupplyProductsBarcodeResult::class);

        return true === $result->valid() ? $result : false;
    }

    private function builder(): DBALQueryBuilder
    {
        if(false === ($this->product instanceof ProductUid))
        {
            throw new InvalidArgumentException('Не передан обязательный параметр запроса ProductUid');
        }

        $dbal = $this->DBALQueryBuilder->createQueryBuilder(self::class);

        $dbal
            ->select('product.id AS product_id')
            ->addSelect('product.event AS product_event')
            ->from(Product::class, 'product');

        $dbal
            ->where('product.id = :product')
            ->setParameter(
                'product',
                $this->product,
                ProductUid::TYPE,
            );

        $dbal
            ->leftJoin(
                'product',
                ProductInfo::class,
                'info',
                'info.product = product.id'
            );

        $dbal
            ->addSelect('offer.id AS offer_id')
            ->addSelect('offer.const AS offer_const')
            ->leftJoin(
                'product',
                ProductOffer::class,
                'offer',
                'offer.event = product.event',
            );

        $dbal
            ->addSelect('variation.id AS variation_id')
            ->addSelect('variation.const AS variation_const')
            ->leftJoin(
                'offer',
                ProductVariation::class,
                'variation',
                'variation.offer = offer.id',
            );

        $dbal
            ->addSelect('modification.id AS modification_id')
            ->addSelect('modification.const AS modification_const')
            ->leftJoin(
                'variation',
                ProductModification::class,
                'modification',
                'modification.variation = variation.id',
            );

        /** Штрихкод продукта */
        $dbal->addSelect(
            '
                    COALESCE(
                        modification.barcode,
                        variation.barcode,
                        offer.barcode,
                        info.barcode
                    ) AS barcode'
        );

        /** Только в статусе New (Новая) */
        $dbal
            //            ->addSelect('supply_event.status')
            ->join(
                'modification',
                ProductSupplyEvent::class,
                'supply_event',
                'supply_event.status = :status'
            )->setParameter(
                key: 'status',
                value: ProductSupplyStatusNew::STATUS,
                type: ProductSupplyStatus::TYPE,
            );

        /** Активное событие поставки */
        $dbal
            ->addSelect('supply.id AS supply_id')
            ->addSelect('supply.event AS supply_event')
            ->join(
                'supply_event',
                ProductSupply::class,
                'supply',
                'supply.event = supply_event.id'
            );

        /** Только НЕИЗВЕСТНЫЕ продукты из поставки */
        $dbal
            ->addSelect('supply_product.id AS supply_product')
            ->join(
                'supply',
                ProductSupplyProduct::class,
                'supply_product',
                'supply_product.event = supply.event AND supply_product.product IS NULL'
            );

        /** Соответствие шрихкоду продукта из поставки продукту в системе */
        $dbal->andWhere("
            (
                CASE
                    WHEN modification.barcode IS NOT NULL AND modification.barcode = supply_product.barcode
                    THEN supply_product.barcode

                    WHEN variation.barcode IS NOT NULL AND variation.barcode = supply_product.barcode
                    THEN supply_product.barcode

                    WHEN offer.barcode IS NOT NULL AND offer.barcode = supply_product.barcode
                    THEN supply_product.barcode

                    WHEN info.barcode IS NOT NULL AND info.barcode = supply_product.barcode
                    THEN supply_product.barcode
                    
                    ELSE NULL
                END
            ) IS NOT NULL
        ");

        $dbal->orderBy('supply.id');

        return $dbal;
    }
}