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

namespace BaksDev\Products\Supply\UseCase\Admin\ProductStock;

use BaksDev\Products\Stocks\Entity\Stock\Event\Supply\ProductStockSupplyInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Объект для создания Честного знака при ИЗМЕНЕНИИ поставки
 *
 * @see ProductStockSupply
 */
final class ProductStockSupplyDTO implements ProductStockSupplyInterface
{
    /** Номер контейнера */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    private string $supply;

    public function getSupply(): string
    {
        return $this->supply;
    }

    public function setSupply(string $supply): self
    {
        $this->supply = $supply;
        return $this;
    }
}