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

namespace BaksDev\Products\Supply\Repository\ProductSign\AllProductSignEventsRelatedProductSupply;

use BaksDev\Core\Doctrine\ORMQueryBuilder;
use BaksDev\Products\Sign\Entity\Code\ProductSignCode;
use BaksDev\Products\Sign\Entity\Event\ProductSignEvent;
use BaksDev\Products\Sign\Entity\Event\Supply\ProductSignSupply;
use BaksDev\Products\Sign\Entity\Invariable\ProductSignInvariable;
use BaksDev\Products\Sign\Entity\Modify\ProductSignModify;
use BaksDev\Products\Sign\Entity\ProductSign;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus\Collection\ProductSignStatusInterface;
use BaksDev\Products\Supply\Entity\ProductSupply;
use BaksDev\Products\Supply\Type\ProductSign\Status\ProductSignStatusUndefined;
use BaksDev\Products\Supply\Type\ProductSupplyUid;
use Doctrine\ORM\Query\Expr\Join;
use Generator;
use InvalidArgumentException;

final class AllProductSignEventsRelatedProductSupplyRepository implements AllProductSignEventsRelatedProductSupplyInterface
{
    private ProductSupplyUid|false $supply = false;

    private ProductSignStatus|false $status = false;

    public function __construct(
        private readonly ORMQueryBuilder $ORMQueryBuilder
    ) {}

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

    /** Фильтр по статусу Честного знака */
    public function forStatus(ProductSignStatus|ProductSignStatusInterface $status): self
    {
        if($status instanceof ProductSignStatusInterface)
        {
            $status = new ProductSignStatus($status);
        }

        $this->status = $status;
        return $this;
    }

    /**
     * Возвращает объекты Честного знака, связанные с поставкой
     * @return Generator<int, ProductSignEvent>|false
     */
    public function findAll(): Generator|false
    {
        if(false === $this->supply instanceof ProductSupplyUid)
        {
            throw new InvalidArgumentException('Не передан обязательный параметр запроса ProductSupplyUid');
        }

        $orm = $this->ORMQueryBuilder->createQueryBuilder(self::class);

        $orm->from(ProductSign::class, 'main');

        /**
         * Invariable
         */
        $orm
            ->select('event')
            ->join(
                ProductSignInvariable::class,
                'invariable',
                Join::WITH,
                'invariable.main = main.id'
            );

        /**
         * Event
         */

        $statusCondition = 'event.id = main.event AND event.status != :undefined';

        if(true === ($this->status instanceof ProductSignStatus))
        {
            $statusCondition .= ' AND event.status = :status';

            $orm->setParameter(
                key: 'status',
                value: $this->status,
                type: ProductSignStatus::TYPE,
            );
        }

        $orm
            ->join(
                ProductSignEvent::class,
                'event',
                Join::WITH,
                $statusCondition
            )
            ->setParameter(
                key: 'undefined',
                value: ProductSignStatusUndefined::STATUS,
                type: ProductSignStatus::TYPE,
            );

        /**
         * Поставка
         */
        $orm
            ->join(
                ProductSignSupply::class,
                'supply',
                Join::WITH,
                '
                    supply.event = main.event AND
                    supply.supply = :supply
            ')
            ->setParameter(
                key: 'supply',
                value: $this->supply,
                type: ProductSupplyUid::TYPE,
            );

        /** Code */
        $orm
            ->join(
                ProductSignCode::class,
                'code',
                Join::WITH,
                "code.event = main.event"
            );

        $orm
            ->join(
                ProductSignModify::class,
                'modify',
                Join::WITH,
                'modify.event = main.event',
            );

        /** Сортируем по дате, выбирая самый старый знак и мешок */
        $orm->addOrderBy('modify.modDate');
        $orm->addOrderBy('invariable.part');

        $result = $orm->getResult();

        if(null === $result)
        {
            return false;
        }

        yield from $result;
    }
}