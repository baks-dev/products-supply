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

namespace BaksDev\Products\Supply\Type\Status;

use BaksDev\Products\Supply\Type\Status\ProductSupplyStatus\Collection\ProductSupplyStatusNew;
use BaksDev\Products\Supply\Type\Status\ProductSupplyStatus\ProductSupplyStatusInterface;
use InvalidArgumentException;

final class ProductSupplyStatus
{
    public const string TYPE = 'product_supply_status_type';

    public const string TEST = ProductSupplyStatusNew::class;

    private ProductSupplyStatusInterface $status;

    public function __construct(self|string|ProductSupplyStatusInterface $status)
    {

        if(is_string($status) && class_exists($status))
        {
            $instance = new $status();

            if($instance instanceof ProductSupplyStatusInterface)
            {
                $this->status = $instance;
                return;
            }
        }

        if($status instanceof ProductSupplyStatusInterface)
        {
            $this->status = $status;
            return;
        }

        if($status instanceof self)
        {
            $this->status = $status->getStatus();
            return;
        }

        /** @var ProductSupplyStatusInterface $declare */
        foreach(self::getDeclared() as $declare)
        {
            $instance = new self($declare);

            if($instance->getStatusValue() === $status)
            {
                $this->status = new $declare();
                return;
            }
        }

        throw new InvalidArgumentException(sprintf('Not found ProductSupplyStatus %s', $status));
    }

    public function __toString(): string
    {
        return $this->status->getValue();
    }

    public function getStatus(): ProductSupplyStatusInterface
    {
        return $this->status;
    }

    public function getStatusValue(): string
    {
        return $this->status->getValue();
    }

    public function getColor(): string
    {
        return $this->status::color();
    }

    public function equals(mixed $status): bool
    {
        $status = new self($status);
        return $this->getStatusValue() === $status->getStatusValue();
    }

    /** Находит предыдущий статус поставки относительно переданного */
    public function previous(ProductSupplyStatusInterface|string $currentStatus): ProductSupplyStatus|null
    {
        $prevStatus = null;

        if(false === $currentStatus instanceof ProductSupplyStatusInterface)
        {
            $currentStatus = new ProductSupplyStatus($currentStatus)->getStatus();
        }

        /** @var ProductSupplyStatus $status */
        foreach(self::cases() as $status)
        {
            if(null === $prevStatus)
            {
                $prevStatus = $status;

                if($status->equals($currentStatus))
                {
                    break;
                }

                continue;
            }

            if($status->equals($currentStatus))
            {
                break;
            }

            $prevStatus = $status;
        }

        return $prevStatus;
    }

    public static function cases(): array
    {
        $case = [];

        $classes = self::getDeclared();

        foreach($classes as $key => $declared)
        {
            /** @var ProductSupplyStatusInterface $declared */
            $class = new $declared();

            $case[$class::priority().$key] = new self($class);
        }

        ksort($case);

        return $case;
    }


    private static function getDeclared(): array
    {
        return array_filter(
            get_declared_classes(),
            static function($className) {
                return in_array(ProductSupplyStatusInterface::class, class_implements($className), true);
            }
        );
    }
}