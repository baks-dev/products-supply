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
 */

declare(strict_types=1);

namespace BaksDev\Products\Supply\UseCase\Admin\ProductSupply;

use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Invariable\ProductInvariableUid;
use BaksDev\Products\Supply\Entity\Event\Product\ProductSupplyProductInterface;
use BaksDev\Products\Supply\Type\Event\ProductSupplyEventUid;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\Validator\Constraints as Assert;

/** @see ProductSupplyProduct */
final class ProductSupplyProductDTO implements ProductSupplyProductInterface
{
    /**
     * Идентификатор События
     */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    private readonly ProductSupplyEventUid $id;

    /** ID Invariable продукта */
    #[Assert\Uuid]
    private readonly ProductInvariableUid $product;

    /**
     * Количество добавляемой
     */
    #[Assert\NotBlank]
    private readonly int $append;


    public function getEvent(): ProductSupplyEventUid
    {
        return $this->id;
    }

    public function setEvent(ProductSupplyEventUid $event): self
    {
        $this->id = $event;
        return $this;
    }

    public function getProduct(): ProductInvariableUid
    {
        return $this->product;
    }

    public function setProduct(ProductInvariableUid $product): self
    {
        $this->product = $product;
        return $this;
    }

    public function getAppend(): int
    {
        return $this->append;
    }

    public function setAppend(int $append): self
    {
        $this->append = $append;
        return $this;
    }
}