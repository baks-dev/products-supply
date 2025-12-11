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

namespace BaksDev\Products\Supply\Repository\ExistProductSupplyByStatus;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Products\Supply\Entity\Event\ProductSupplyEvent;
use BaksDev\Products\Supply\Entity\ProductSupply;
use BaksDev\Products\Supply\Type\ProductSupplyUid;
use BaksDev\Products\Supply\Type\Status\ProductSupplyStatus;
use BaksDev\Products\Supply\Type\Status\ProductSupplyStatus\ProductSupplyStatusInterface;
use InvalidArgumentException;

final class ExistProductSupplyByStatusRepository implements ExistProductSupplyByStatusInterface
{
    private ProductSupplyUid|false $supply = false;

    private ProductSupplyStatus|false $status = false;

    public function __construct(private readonly DBALQueryBuilder $DBALQueryBuilder) {}

    public function forProductSupply(ProductSupply|ProductSupplyUid $order): self
    {
        if($order instanceof ProductSupply)
        {
            $order = $order->getId();
        }

        $this->supply = $order;

        return $this;
    }

    public function forStatus(ProductSupplyStatus|ProductSupplyStatusInterface $status): self
    {
        if($status instanceof ProductSupplyStatusInterface)
        {
            $status = new ProductSupplyStatus($status);
        }

        $this->status = $status;

        return $this;
    }

    /**
     * Метод проверяет, имеется ли событие у заказа с указанным статусом
     */
    public function isExists(): bool
    {
        if(false === ($this->supply instanceof ProductSupplyUid))
        {
            throw new InvalidArgumentException('Invalid Argument ProductSupplyUid');
        }

        if(false === ($this->status instanceof ProductSupplyStatus))
        {
            throw new InvalidArgumentException('Invalid Argument ProductSupplyStatus');
        }

        $dbal = $this->DBALQueryBuilder->createQueryBuilder(self::class);

        $dbal->from(ProductSupplyEvent::class, 'event');

        $dbal
            ->where('event.main = :main')
            ->setParameter(
                key: 'main',
                value: $this->supply,
                type: ProductSupplyUid::TYPE,
            );

        $dbal
            ->andWhere('event.status = :status')
            ->setParameter(
                key: 'status',
                value: $this->status,
                type: ProductSupplyStatus::TYPE,
            );

        return $dbal->fetchExist();
    }
}
