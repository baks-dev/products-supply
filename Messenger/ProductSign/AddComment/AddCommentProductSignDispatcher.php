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

namespace BaksDev\Products\Supply\Messenger\ProductSign\AddComment;

use BaksDev\Products\Sign\Entity\Event\ProductSignEvent;
use BaksDev\Products\Sign\Entity\ProductSign;
use BaksDev\Products\Sign\UseCase\Admin\Status\ProductSignStatusHandler;
use BaksDev\Products\Supply\Entity\Event\ProductSupplyEvent;
use BaksDev\Products\Supply\Messenger\ProductSupplyMessage;
use BaksDev\Products\Supply\Repository\CurrentProductSupplyEvent\CurrentProductSupplyEventInterface;
use BaksDev\Products\Supply\Repository\ProductSign\AllProductSignEventsRelatedProductSupply\AllProductSignEventsRelatedProductSupplyInterface;
use BaksDev\Products\Supply\Type\Status\ProductSupplyStatus\Collection\ProductSupplyStatusCleared;
use BaksDev\Products\Supply\UseCase\Admin\ProductsSign\Edit\AddCommentProductSignDTO;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * При присвоении поставке статуса cleared "Растормаживается" -
 * присваивает ГТД для связанных Честных знаков в виде комментария
 */
#[AsMessageHandler(priority: 0)]
final readonly class AddCommentProductSignDispatcher
{
    public function __construct(
        #[Target('productsSupplyLogger')] private LoggerInterface $logger,
        private CurrentProductSupplyEventInterface $currentProductSupplyEventRepository,
        private AllProductSignEventsRelatedProductSupplyInterface $allProductSignEventsRelatedProductSupplyRepository,
        private ProductSignStatusHandler $ProductSignStatusHandler,
    ) {}

    public function __invoke(ProductSupplyMessage $message): void
    {
        /** Текущее событие поставки */
        $ProductSupplyEvent = $this->currentProductSupplyEventRepository
            ->find($message->getId());

        if(false === ($ProductSupplyEvent instanceof ProductSupplyEvent))
        {
            $this->logger->critical(
                message: 'Событие ProductSupplyEvent не найдено',
                context: [
                    self::class.':'.__LINE__,
                    var_export($message, true),
                ],
            );

            return;
        }

        /** Если статус поставки не cleared (Растаможены) - прерываем работу */
        if(false === $ProductSupplyEvent->getStatus()->equals(ProductSupplyStatusCleared::class))
        {
            return;
        }

        /** Честные знаки связанные с поставкой */
        $productSignForComment = $this->allProductSignEventsRelatedProductSupplyRepository
            ->forSupply($ProductSupplyEvent->getMain())
            ->findAll();

        if(false === $productSignForComment || false === $productSignForComment->valid())
        {
            $this->logger->critical(
                message: sprintf(
                    'products-sign: Не найдено Честных знаков для присвоения комментария при смене статуса %s поставки %s',
                    $message->getId(), $ProductSupplyEvent->getStatus(),
                ),
                context: [
                    self::class.':'.__LINE__,
                    var_export($message, true),
                ],
            );

            return;
        }

        foreach($productSignForComment as $ProductSignEvent)
        {
            $CommentProductSignDTO = new AddCommentProductSignDTO();
            $ProductSignEvent->getDto($CommentProductSignDTO);

            /** Присваиваем номер ГТД из поставки Честному знаку в виде комментария */
            $CommentProductSignDTO->setComment($ProductSupplyEvent->getInvariable()->getDeclaration());

            $handle = $this->ProductSignStatusHandler->handle($CommentProductSignDTO);

            if(false === ($handle instanceof ProductSign))
            {
                $this->logger->critical(
                    message: sprintf(
                        'products-sign: ошибка %s: Не удалось присвоить номер ГТД для Честного знака %s при изменении статуса поставки %s',
                        $handle, $ProductSignEvent->getMain(), $message->getId(),
                    ),
                    context: [
                        self::class.':'.__LINE__,
                        var_export($message, true),
                    ],
                );

                continue;
            }

            if(true === ($handle instanceof ProductSign))
            {
                $this->logger->info(
                    message: sprintf(
                        'products-sign: Успешно присвоили номер ГТД Честного знака %s',
                        $handle->getId(),
                    ),
                    context: [
                        self::class.':'.__LINE__,
                        var_export($message, true),
                    ],
                );
            }
        }
    }
}