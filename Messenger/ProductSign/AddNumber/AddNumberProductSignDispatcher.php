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

namespace BaksDev\Products\Supply\Messenger\ProductSign\AddNumber;


use BaksDev\Core\Messenger\MessageDelay;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Products\Sign\Entity\Event\ProductSignEvent;
use BaksDev\Products\Sign\Entity\ProductSign;
use BaksDev\Products\Sign\Repository\CurrentEvent\ProductSignCurrentEventInterface;
use BaksDev\Products\Sign\UseCase\Admin\Status\ProductSignStatusHandler;
use BaksDev\Products\Supply\UseCase\Admin\ProductsSign\Edit\AddNumberProductSignDTO;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * При присвоении поставке статуса cleared "Растаможены" -
 * присваивает ГТД для связанных Честных знаков как номер
 */
#[Autoconfigure(shared: false)]
#[AsMessageHandler(priority: 0)]
final readonly class AddNumberProductSignDispatcher
{
    public function __construct(
        #[Target('productsSignLogger')] private LoggerInterface $logger,
        private ProductSignCurrentEventInterface $ProductSignCurrentEventRepository,
        private MessageDispatchInterface $messageDispatch,
        private ProductSignStatusHandler $ProductSignStatusHandler
    ) {}

    public function __invoke(AddNumberProductSignMessage $message): void
    {
        $CurrentProductSignEvent = $this->ProductSignCurrentEventRepository
            ->forProductSign($message->getId())
            ->find();

        if(false === ($CurrentProductSignEvent instanceof ProductSignEvent))
        {
            $this->logger->critical(
                'products-sign: Не удалось найти событие Честного знака. Пробуем повторить попытку позже',
                [self::class.':'.__LINE__, var_export($message, true)],
            );

            $this->messageDispatch->dispatch(
                message: $message,
                stamps: [new MessageDelay('5 seconds')],
                transport: 'barcode',
            );

            return;
        }


        $CommentProductSignDTO = new AddNumberProductSignDTO();
        $CurrentProductSignEvent->getDto($CommentProductSignDTO);

        /** Присваиваем номер ГТД из поставки как номер Честному знаку */
        $CommentProductSignDTO->getInvariable()
            ->setNumber($message->getDeclaration());

        $handle = $this->ProductSignStatusHandler->handle($CommentProductSignDTO);

        if(false === ($handle instanceof ProductSign))
        {
            $this->logger->critical(
                message: sprintf(
                    'products-supply: Поставка %s: Не удалось присвоить номер ГТД для Честного знака id - %s при изменении статуса поставки. Ошибка %s',
                    $message->getNumber(),
                    $CurrentProductSignEvent->getMain(),
                    $handle,
                ),
                context: [
                    self::class.':'.__LINE__,
                    var_export($message, true),
                ],
            );

            return;
        }

        $this->logger->info(
            message: sprintf(
                'Поставка %s: Успешно присвоили номер ГТД Честного знака id - %s',
                $message->getNumber(),
                $handle->getId(),
            ),
            context: [
                self::class.':'.__LINE__,
                var_export($message, true),
            ],
        );
    }
}
