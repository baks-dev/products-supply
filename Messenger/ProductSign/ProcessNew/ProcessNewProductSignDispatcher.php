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

namespace BaksDev\Products\Supply\Messenger\ProductSign\ProcessNew;

use BaksDev\Core\Messenger\MessageDelay;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Products\Sign\Entity\Event\ProductSignEvent;
use BaksDev\Products\Sign\Entity\ProductSign;
use BaksDev\Products\Sign\UseCase\Admin\Status\ProductSignStatusHandler;
use BaksDev\Products\Supply\Entity\Event\ProductSupplyEvent;
use BaksDev\Products\Supply\Messenger\ProductSupplyMessage;
use BaksDev\Products\Supply\Repository\CurrentProductSupplyEvent\CurrentProductSupplyEventInterface;
use BaksDev\Products\Supply\Repository\ProductSign\AllProductSignEventsRelatedProductSupply\AllProductSignEventsRelatedProductSupplyInterface;
use BaksDev\Products\Supply\Type\ProductSign\Status\ProductSignStatusSupply;
use BaksDev\Products\Supply\Type\Status\ProductSupplyStatus\Collection\ProductSupplyStatusCompleted;
use BaksDev\Products\Supply\UseCase\Admin\ProductsSign\Edit\ProcessNewProductSignDTO;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * При присвоении поставке статуса completed "Выполнен" -
 * вводит Честные знаки в оборот, переводя из в статус New «Новый»
 */
#[AsMessageHandler(priority: 0)]
final readonly class ProcessNewProductSignDispatcher
{
    public function __construct(
        #[Target('productsSupplyLogger')] private LoggerInterface $logger,
        private MessageDispatchInterface $messageDispatch,
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

        /** Если статус поставки не completed (Выполнен) - прерываем работу */
        if(false === $ProductSupplyEvent->getStatus()->equals(ProductSupplyStatusCompleted::class))
        {
            return;
        }

        /** Честные знаки для ввода в оборот - перевод в статус NEW */
        $productSignForProcessNew = $this->allProductSignEventsRelatedProductSupplyRepository
            ->forSupply($ProductSupplyEvent->getMain())
            ->forStatus(new ProductSignStatusSupply)
            ->findAll();

        if(false === $productSignForProcessNew)
        {
            return;
        }

        foreach($productSignForProcessNew as $ProductSignEvent)
        {
            $ProcessNewProductSignDTO = new ProcessNewProductSignDTO();
            $ProductSignEvent->getDto($ProcessNewProductSignDTO);

            $handle = $this->ProductSignStatusHandler->handle($ProcessNewProductSignDTO);

            if(false === ($handle instanceof ProductSign))
            {
                $this->logger->critical(
                    message: sprintf(
                        'products-sign: Ошибка %s: Не удалось применить статус %s для Честного знака %s. Повторяем попытку через интервал',
                        $handle, $ProcessNewProductSignDTO->getStatus(), $ProductSignEvent->getMain(),
                    ),
                    context: [
                        self::class.':'.__LINE__,
                        var_export($message, true),
                    ],
                );

                $this->messageDispatch
                    ->dispatch(
                        message: $message,
                        stamps: [new MessageDelay('15 seconds')],
                        transport: 'products-sign',
                    );

                return;
            }

            if(true === ($handle instanceof ProductSign))
            {
                $this->logger->info(
                    message: sprintf(
                        'products-sign: Успешно применили статус %s для Честного знака %s',
                        $ProcessNewProductSignDTO->getStatus(), $handle->getId()
                    ),
                    context: [
                        self::class.':'.__LINE__,
                    ],
                );
            }

        }
    }
}