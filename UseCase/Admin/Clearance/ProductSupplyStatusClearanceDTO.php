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

namespace BaksDev\Products\Supply\UseCase\Admin\Clearance;

use BaksDev\Products\Supply\Entity\Event\ProductSupplyEventInterface;
use BaksDev\Products\Supply\Type\Event\ProductSupplyEventUid;
use BaksDev\Products\Supply\Type\Status\ProductSupplyStatus;
use BaksDev\Products\Supply\Type\Status\ProductSupplyStatus\Collection\ProductSupplyStatusClearance;
use BaksDev\Products\Supply\UseCase\Admin\Clearance\Invariable\ProductSupplyInvariableDTO;
use BaksDev\Products\Supply\UseCase\Admin\Edit\Product\EditProductSupplyProductDTO;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Объект для перемещения поставки в статус clearance (Растормаживается)
 * @see ProductSupplyEvent
 */
final class ProductSupplyStatusClearanceDTO implements ProductSupplyEventInterface
{
    /**
     * Идентификатор события
     */
    #[Assert\Uuid]
    private readonly ProductSupplyEventUid $id;

    /**
     * Статус поставки
     */
    #[Assert\NotBlank]
    private readonly ProductSupplyStatus $status;

    /** Номер контейнера у поставки */
    #[Assert\Valid]
    private ProductSupplyInvariableDTO $invariable;

    /**
     * Продукты в поставке
     * @var ArrayCollection<int, EditProductSupplyProductDTO> $product
     */
    #[Assert\Valid]
    private readonly ArrayCollection $product;

    public function __construct(ProductSupplyEventUid $id)
    {
        $this->id = $id;
        $this->status = new ProductSupplyStatus(ProductSupplyStatusClearance::class);
        $this->invariable = new ProductSupplyInvariableDTO();
        $this->product = new ArrayCollection();
    }

    /**
     * Идентификатор события
     */
    public function getEvent(): ProductSupplyEventUid
    {
        return $this->id;
    }

    public function getStatus(): ProductSupplyStatus
    {
        return $this->status;
    }

    public function getPersonal(): null
    {
        return null;
    }

    /**
     * Container
     */
    public function getInvariable(): ProductSupplyInvariableDTO
    {
        return $this->invariable;
    }

    public function setInvariable(ProductSupplyInvariableDTO $invariable): self
    {
        $this->invariable = $invariable;
        return $this;
    }

    /**
     * Коллекция продуктов в поставке
     *
     * @return ArrayCollection<int, EditProductSupplyProductDTO>
     */
    public function getProduct(): ArrayCollection
    {
        return $this->product;
    }

    public function addProduct(EditProductSupplyProductDTO $product): void
    {
        if(!$this->product->contains($product))
        {
            $this->product->add($product);
        }
    }
}