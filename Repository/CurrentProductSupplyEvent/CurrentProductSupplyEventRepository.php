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

namespace BaksDev\Products\Supply\Repository\CurrentProductSupplyEvent;

use BaksDev\Core\Doctrine\ORMQueryBuilder;
use BaksDev\Products\Supply\Entity\Event\ProductSupplyEvent;
use BaksDev\Products\Supply\Entity\ProductSupply;
use BaksDev\Products\Supply\Type\Event\ProductSupplyEventUid;
use BaksDev\Products\Supply\Type\ProductSupplyUid;
use InvalidArgumentException;

final class CurrentProductSupplyEventRepository implements CurrentProductSupplyEventInterface
{
    private ProductSupplyUid|false $main = false;

    private ProductSupplyEventUid|false $event = false;

    public function __construct(private readonly ORMQueryBuilder $ORMQueryBuilder) {}

    public function forMain(ProductSupplyUid $main): self
    {
        $this->main = $main;
        return $this;
    }

    public function forEvent(ProductSupplyEventUid $event): self
    {
        $this->event = $event;
        return $this;
    }

    /**
     * Метод возвращает текущее активное событие поставки
     */
    public function find(): ProductSupplyEvent|false
    {
        $orm = $this->ORMQueryBuilder->createQueryBuilder(self::class);

        if(false === ($this->main instanceof ProductSupplyUid) && false === ($this->event instanceof ProductSupplyEventUid))
        {
            throw new InvalidArgumentException('Invalid Argument ProductSupplyUid or ProductSupplyEventUid');
        }

        if(true === ($this->main instanceof ProductSupplyUid) && true === ($this->event instanceof ProductSupplyEventUid))
        {
            throw new InvalidArgumentException('Вызов двух аргументов ProductSupplyUid и ProductSupplyEventUid');
        }


        if(true === ($this->main instanceof ProductSupplyUid))
        {
            $orm
                ->from(ProductSupply::class, 'main')
                ->where('main.id = :supply')
                ->setParameter(
                    key: 'supply',
                    value: $this->main,
                    type: ProductSupplyUid::TYPE,
                );
        }

        if(true === ($this->event instanceof ProductSupplyEventUid))
        {
            $orm
                ->from(ProductSupplyEvent::class, 'tmp')
                ->where('tmp.id = :event')
                ->setParameter(
                    key: 'event',
                    value: $this->event,
                    type: ProductSupplyEventUid::TYPE,
                );

            $orm
                ->join(
                    ProductSupply::class,
                    'main',
                    'WITH',
                    'main.id = tmp.main',
                );
        }

        $orm
            ->select('event')
            ->join(
                ProductSupplyEvent::class,
                'event',
                'WITH',
                'event.id = main.event',
            );

        $result = $orm->getOneOrNullResult() ?: false;

        $this->main = false;
        $this->event = false;

        return $result;
    }
}
