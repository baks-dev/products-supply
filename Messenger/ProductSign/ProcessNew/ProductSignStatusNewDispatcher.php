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

namespace BaksDev\Products\Supply\Messenger\ProductSign\ProcessNew;


use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Core\Messenger\MessageDelay;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Products\Sign\Entity\ProductSign;
use BaksDev\Products\Sign\Repository\CurrentEvent\ProductSignCurrentEventInterface;
use BaksDev\Products\Sign\UseCase\Admin\Status\ProductSignStatusHandler;
use BaksDev\Products\Supply\Type\ProductSign\Status\ProductSignStatusSupply;
use BaksDev\Products\Supply\UseCase\Admin\ProductsSign\Edit\ProcessNewProductSignDTO;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(priority: 0)]
final readonly class ProductSignStatusNewDispatcher
{
    public function __construct(
        #[Target('productsSignLogger')] private LoggerInterface $logger,
        private ProductSignCurrentEventInterface $ProductSignCurrentEventRepository,
        private DeduplicatorInterface $deduplicator,
        private ProductSignStatusHandler $ProductSignStatusHandler,
        private MessageDispatchInterface $messageDispatch,
    ) {}


    public function __invoke(ProductSignStatusNewMessage $message): void
    {
        $Deduplicator = $this->deduplicator
            ->namespace('products-supply')
            ->deduplication([$message->getId(), self::class]);

        if($Deduplicator->isExecuted())
        {
            return;
        }

        $ProductSignEvent = $this->ProductSignCurrentEventRepository
            ->forProductSign($message->getId())
            ->find();

        if(false === $ProductSignEvent->isStatusEquals(ProductSignStatusSupply::class))
        {
            $this->logger->warning(
                'Статус честного знака не является Supply «Поставка»',
                [
                    self::class.':'.__LINE__,
                    (string) $message->getId(),
                    (string) $ProductSignEvent->getStatus()],
            );

            return;
        }

        $ProcessNewProductSignDTO = new ProcessNewProductSignDTO();
        $ProductSignEvent->getDto($ProcessNewProductSignDTO);

        $handle = $this->ProductSignStatusHandler->handle($ProcessNewProductSignDTO);

        if(false === ($handle instanceof ProductSign))
        {
            $this->logger->critical(
                message: sprintf(
                    'products-supply: Ошибка %s: Не удалось применить статус %s для Честного знака %s. Повторяем попытку через интервал',
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
                    transport: 'products-supply',
                );

            return;
        }


        $this->logger->info(
            message: sprintf(
                'Успешно применили статус %s для Честного знака %s',
                $ProcessNewProductSignDTO->getStatus(), $handle->getId(),
            ),
            context: [
                self::class.':'.__LINE__,
            ],
        );

        $Deduplicator->save();

    }
}
