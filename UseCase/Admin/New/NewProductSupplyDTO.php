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

namespace BaksDev\Products\Supply\UseCase\Admin\New;

use BaksDev\Products\Supply\Entity\Event\ProductSupplyEventInterface;
use BaksDev\Products\Supply\Type\Event\ProductSupplyEventUid;
use BaksDev\Products\Supply\Type\Status\ProductSupplyStatus;
use BaksDev\Products\Supply\Type\Status\ProductSupplyStatus\Collection\ProductSupplyStatusNew;
use BaksDev\Products\Supply\UseCase\Admin\New\Invariable\NewProductSupplyInvariableDTO;
use BaksDev\Products\Supply\UseCase\Admin\New\Personal\NewProductSupplyPersonalDTO;
use BaksDev\Products\Supply\UseCase\Admin\New\Product\NewProductSupplyProductDTO;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;

/** @see ProductSupplyEvent */
final class NewProductSupplyDTO implements ProductSupplyEventInterface
{
    /**
     * Идентификатор события
     */
    #[Assert\Uuid]
    private ?ProductSupplyEventUid $id = null;

    /** Статус поставки */
    #[Assert\NotBlank]
    private readonly ProductSupplyStatus $status;

    /** Номер контейнера у поставки */
    #[Assert\Valid]
    private NewProductSupplyInvariableDTO $invariable;

    /** Информация о пользователе */
    #[Assert\Valid]
    private NewProductSupplyPersonalDTO $personal;

    /**
     * Продукты в поставке
     * @var ArrayCollection<int, NewProductSupplyProductDTO> $product
     */
    #[Assert\Valid]
    private ArrayCollection $product;

    public function __construct()
    {
        $this->status = new ProductSupplyStatus(ProductSupplyStatusNew::class);
        $this->product = new ArrayCollection();
    }

    /**
     * Идентификатор события
     */
    public function getEvent(): ?ProductSupplyEventUid
    {
        return $this->id;
    }

    /**
     * Status
     */
    public function getStatus(): ProductSupplyStatus
    {
        return $this->status;
    }

    /**
     * Container
     */
    public function getInvariable(): NewProductSupplyInvariableDTO
    {
        return $this->invariable;
    }

    public function setInvariable(NewProductSupplyInvariableDTO $invariable): self
    {
        $this->invariable = $invariable;
        return $this;
    }

    /**
     * Personal
     */
    public function setPersonal(NewProductSupplyPersonalDTO $personal): NewProductSupplyDTO
    {
        $this->personal = $personal;
        return $this;
    }

    public function getPersonal(): NewProductSupplyPersonalDTO
    {
        return $this->personal;
    }

    /**
     * Коллекция продуктов в поставке
     * @return ArrayCollection<int, NewProductSupplyProductDTO>
     */
    public function getProduct(): ArrayCollection
    {
        return $this->product;
    }

    public function setProduct(ArrayCollection $product): void
    {
        $this->product = $product;
    }

    public function addProduct(NewProductSupplyProductDTO $product): void
    {
        /**
         * При создании поставки добавляем проверяем продукт на уникальность по штрихкоду
         */

        $exist = $this->product->exists(function($k, $value) use ($product) {
            /** @var NewProductSupplyProductDTO $product */
            return $value->getBarcode()->equals($product->getBarcode());
        });

        if(false === $exist)
        {
            $this->product->add($product);
        }
    }

    public function removeProduct(NewProductSupplyProductDTO $product): void
    {
        $this->product->removeElement($product);
    }
}