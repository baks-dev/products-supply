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

namespace BaksDev\Products\Supply\UseCase\Admin\ProductsSign\New;

use BaksDev\Products\Sign\Entity\Event\ProductSignEventInterface;
use BaksDev\Products\Sign\Type\Event\ProductSignEventUid;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus\ProductSignStatusError;
use BaksDev\Products\Supply\Type\ProductSign\Status\ProductSignStatusUndefined;
use BaksDev\Products\Supply\UseCase\Admin\ProductsSign\New\Code\ProductSignCodeDTO;
use BaksDev\Products\Supply\UseCase\Admin\ProductsSign\New\Invariable\ProductSignInvariableDTO;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Объект для создания Честного знака при СОЗДАНИИ поставки
 *
 * @see ProductSignEvent
 */
final class ProductSignNewDTO implements ProductSignEventInterface
{
    /**
     * Идентификатор события
     */
    #[Assert\Uuid]
    private ?ProductSignEventUid $id = null;

    /**
     * Код честного знака
     */
    #[Assert\Valid]
    private ProductSignCodeDTO $code;

    /**
     * Статус
     */
    #[Assert\NotBlank]
    private ProductSignStatus $status;

    /**
     * Код честного знака
     */
    #[Assert\Valid]
    private ProductSignInvariableDTO $invariable;

    public function __construct()
    {
        $this->status = new ProductSignStatus(ProductSignStatusUndefined::class);
        $this->code = new ProductSignCodeDTO();
        $this->invariable = new ProductSignInvariableDTO();
    }

    public function setId(ProductSignEventUid $id): void
    {
        $this->id = $id;
    }

    /**
     * Идентификатор события
     */
    public function getEvent(): ?ProductSignEventUid
    {
        return $this->id;
    }

    /**
     * Status
     */
    public function getStatus(): ProductSignStatus
    {
        return $this->status;
    }

    public function setErrorStatus(): self
    {
        $this->status = new ProductSignStatus(ProductSignStatusError::class);
        return $this;
    }

    /**
     * Code
     */
    public function getCode(): Code\ProductSignCodeDTO
    {
        return $this->code;
    }

    public function setCode(ProductSignCodeDTO $code): self
    {
        $this->code = $code;
        return $this;
    }

    /**
     * Invariable
     */
    public function getInvariable(): ProductSignInvariableDTO
    {
        return $this->invariable;
    }

    public function setInvariable(ProductSignInvariableDTO $invariable): self
    {
        $this->invariable = $invariable;
        return $this;
    }
}
