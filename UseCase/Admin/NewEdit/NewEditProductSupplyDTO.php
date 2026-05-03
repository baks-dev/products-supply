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
 *
 */

declare(strict_types=1);

namespace BaksDev\Products\Supply\UseCase\Admin\NewEdit;

use BaksDev\Products\Supply\Entity\Event\ProductSupplyEventInterface;
use BaksDev\Products\Supply\Type\Event\ProductSupplyEventUid;
use BaksDev\Products\Supply\Type\Status\ProductSupplyStatus;
use BaksDev\Products\Supply\Type\Status\ProductSupplyStatus\Collection\ProductSupplyStatusNew;
use BaksDev\Products\Supply\UseCase\Admin\NewEdit\Files\NewEditProductSupplyFilesDTO;
use BaksDev\Products\Supply\UseCase\Admin\NewEdit\Invariable\AddProductSupplyInvariableDTO;
use BaksDev\Products\Supply\UseCase\Admin\NewEdit\Lock\AddProductSupplyLockDTO;
use BaksDev\Products\Supply\UseCase\Admin\NewEdit\Personal\AddProductSupplyPersonalDTO;
use Symfony\Component\Validator\Constraints as Assert;

/** @see ProductSupplyEvent */
final class NewEditProductSupplyDTO implements ProductSupplyEventInterface
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
    private AddProductSupplyInvariableDTO $invariable;

    /** Информация о пользователе */
    #[Assert\Valid]
    private AddProductSupplyPersonalDTO $personal;

    #[Assert\Valid]
    private AddProductSupplyLockDTO $lock;

    /**
     * Коллекция загружаемых файлов
     */
    private NewEditProductSupplyFilesDTO $files;

    public function __construct()
    {
        $this->status = new ProductSupplyStatus(ProductSupplyStatusNew::class);
        $this->invariable = new AddProductSupplyInvariableDTO();
        $this->personal = new AddProductSupplyPersonalDTO();
        $this->lock = new AddProductSupplyLockDTO();
        $this->files = new NewEditProductSupplyFilesDTO();
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
    public function getInvariable(): AddProductSupplyInvariableDTO
    {
        return $this->invariable;
    }

    public function setInvariable(AddProductSupplyInvariableDTO $invariable): self
    {
        $this->invariable = $invariable;
        return $this;
    }

    public function getPersonal(): AddProductSupplyPersonalDTO
    {
        return $this->personal;
    }

    /**
     * Personal
     */
    public function setPersonal(AddProductSupplyPersonalDTO $personal): self
    {
        $this->personal = $personal;
        return $this;
    }

    /**
     * Files
     */

    public function getFiles(): NewEditProductSupplyFilesDTO
    {
        return $this->files;
    }

    public function setFiles(NewEditProductSupplyFilesDTO $files): self
    {
        $this->files = $files;
        return $this;
    }

    public function getLock(): AddProductSupplyLockDTO
    {
        return $this->lock;
    }
}