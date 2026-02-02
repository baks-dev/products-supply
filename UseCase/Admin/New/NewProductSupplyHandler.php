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

namespace BaksDev\Products\Supply\UseCase\Admin\New;

use BaksDev\Core\Entity\AbstractHandler;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Core\Validator\ValidatorCollectionInterface;
use BaksDev\Files\Resources\Upload\File\FileUploadInterface;
use BaksDev\Files\Resources\Upload\Image\ImageUploadInterface;
use BaksDev\Products\Supply\Entity\Event\ProductSupplyEvent;
use BaksDev\Products\Supply\Entity\ProductSupply;
use BaksDev\Products\Supply\Messenger\ProductSupplyMessage;
use BaksDev\Products\Supply\Repository\ExistProductSupplyByContainerNumber\ExistProductSupplyByContainerNumberInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;

final class NewProductSupplyHandler extends AbstractHandler
{
    public function __construct(
        #[Target('productsSupplyLogger')] private readonly LoggerInterface $logger,
        private readonly ExistProductSupplyByContainerNumberInterface $existProductSupplyByContainerNumber,

        EntityManagerInterface $entityManager,
        MessageDispatchInterface $messageDispatch,
        ValidatorCollectionInterface $validatorCollection,
        ImageUploadInterface $imageUpload,
        FileUploadInterface $fileUpload
    )
    {
        parent::__construct($entityManager, $messageDispatch, $validatorCollection, $imageUpload, $fileUpload);
    }

    public function handle(NewProductSupplyDTO $command): ProductSupply|string
    {
        $personal = $command->getPersonal();

        /** Проверка поставки на дубли */
        $isExistsSupply = $this->existProductSupplyByContainerNumber
            ->forStatus($command->getStatus())
            ->forUser($personal->getUsr())
            ->forProfile($personal->getProfile())
            ->isExist($command->getInvariable());

        if(true === $isExistsSupply)
        {
            $error = uniqid('', true);

            $this->logger->warning(
                message: sprintf('%s: Поставка с данным номером уже существует: %s',
                    $error,
                    $command->getInvariable()->getNumber()),
                context: [
                    self::class.':'.__LINE__,
                    $command,
                ],
            );

            return $error;
        }

        $this
            ->setCommand($command)
            ->preEventPersistOrUpdate(ProductSupply::class, ProductSupplyEvent::class);

        /** Валидация всех объектов */
        if($this->validatorCollection->isInvalid())
        {
            return $this->validatorCollection->getErrorUniqid();
        }

        $this->flush();

        $this->logger->warning(
            message: sprintf(
                'Поставка %s: Заблокировали изменения',
                $command->getInvariable()->getNumber()
            ),
            context: [
                'main' => (string) $this->main->getId(),
                'event' => (string) $this->main->getEvent(),
                self::class.':'.__LINE__,
            ],
        );

        $this->messageDispatch
            ->addClearCacheOther('products-sign')
            ->dispatch(
                message: new ProductSupplyMessage($this->main->getId(), $this->main->getEvent(), $command->getEvent()),
                transport: 'products-supply'
            );

        return $this->main;
    }
}