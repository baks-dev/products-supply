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
use BaksDev\Products\Product\Type\Invariable\ProductInvariableUid;
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

    private ProductInvariableUid|false $product = false;

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

    public function forProduct(ProductInvariableUid $product): self
    {
        $this->product = $product;

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
                'main.event = event.id',
            );

        /** Только для конкретной поставки */
        $dbal
            ->select('COUNT(*)')
            ->join(
                'event',
                ProductSignSupply::class,
                'sign_supply',
                '
                    sign_supply.event = main.event AND
                    sign_supply.value = :supply',
            )
            ->setParameter(
                'supply',
                $this->supply,
                ProductSupplyUid::TYPE,
            );

        /**
         * Продукт
         */

        $invariableCondition = 'invariable.main = main.id';

        if(true === ($this->product instanceof ProductInvariableUid))
        {

            $dbal->setParameter('product', $this->product, ProductInvariableUid::TYPE);

            $invariableCondition = 'invariable.main = main.id AND 
                    invariable.product = :product';
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
                $invariableCondition,
            );

        return (int) $dbal->fetchOne();
    }
}
