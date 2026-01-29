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

namespace BaksDev\Products\Supply\Messenger\ProductSign\ProcessReservation;

use BaksDev\Core\Messenger\MessageDelay;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Products\Sign\Entity\Event\ProductSignEvent;
use BaksDev\Products\Sign\Entity\ProductSign;
use BaksDev\Products\Sign\UseCase\Admin\Status\ProductSignStatusHandler;
use BaksDev\Products\Supply\Entity\Event\ProductSupplyEvent;
use BaksDev\Products\Supply\Repository\CurrentProductSupplyEvent\CurrentProductSupplyEventInterface;
use BaksDev\Products\Supply\Repository\ProductSign\OneProductSignSupply\OneProductSignEventInterface;
use BaksDev\Products\Supply\UseCase\Admin\ProductsSign\Edit\ProcessReservationProductSignDTO;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Бронирует Честный знак за продутом из поставки
 *
 * @see NewStatusProductSupplyDispatcher
 * @note обработчик отработает на каждую единицу продукции в поставке, количество которых может быть десятки тысяч
 */
#[AsMessageHandler(priority: 0)]
final readonly class ProcessReservationProductSignDispatcher
{
    public function __construct(
        #[Target('productsSupplyLogger')] private LoggerInterface $logger,
        private MessageDispatchInterface $messageDispatch,
        private ProductSignStatusHandler $ProductSignStatusHandler,
        private CurrentProductSupplyEventInterface $currentProductSupplyEventRepository,
        private OneProductSignEventInterface $productSignSupplyRepository,
    ) {}

    public function __invoke(ProcessReservationProductSignMessage $message): void
    {
        /** Текущее событие поставки */
        $ProductSupplyEvent = $this->currentProductSupplyEventRepository
            ->find($message->getSupply());

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

        /** Ищем свободный честный знак в статусе Undefined «Не определен» */
        $ProductSignEvent = $this->productSignSupplyRepository
            ->forUser($message->getUser())
            ->forProfile($message->getProfile())
            ->forProduct($message->getProduct())
            ->forOfferConst($message->getOfferConst())
            ->forVariationConst($message->getVariationConst())
            ->forModificationConst($message->getModificationConst())
            ->getOneUndefined();

        if(false === ($ProductSignEvent instanceof ProductSignEvent))
        {
            //            $this->logger->info(
            //                message: sprintf(
            //                    'Поставка %s: Повторная попытка зарезервировать Честный знак',
            //                    $ProductSupplyEvent->getInvariable()->getNumber()),
            //                context: [
            //                    self::class.':'.__LINE__,
            //                    var_export($message, true),
            //                ],
            //            );

            /** Повтор найти Честный знак, не принадлежащий какой-либо поставке */
            $this->messageDispatch
                ->dispatch(
                    message: $message,
                    stamps: [new MessageDelay('15 seconds')],
                    transport: 'products-supply-low',
                );

            return;
        }

        /** Редактируем текущее событие */
        $ProductSignProcessDTO = new ProcessReservationProductSignDTO($message->getSupply());
        $ProductSignEvent->getDto($ProductSignProcessDTO);

        $handle = $this->ProductSignStatusHandler->handle($ProductSignProcessDTO);

        if(false === ($handle instanceof ProductSign))
        {
            $this->logger->critical(
                message: sprintf('Поставка %s: Ошибка при обновлении статуса ЧЗ: %s',
                    $ProductSupplyEvent->getInvariable()->getNumber(), $handle
                ),
                context: [
                    self::class.':'.__LINE__,
                    var_export($message, true),
                ],
            );

            throw new InvalidArgumentException('Ошибка при обновлении статуса ЧЗ');
        }

        //        $this->logger->info(
        //            message: sprintf('Поставка %s: Зарезервировали ЧЗ со статусом Supply «Поставка»',
        //                $ProductSupplyEvent->getInvariable()->getNumber()
        //            ),
        //            context: [
        //                self::class.':'.__LINE__,
        //                var_export($message, true),
        //            ],
        //        );
    }
}