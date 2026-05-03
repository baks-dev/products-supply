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

namespace BaksDev\Products\Supply\Messenger\ProductSupply\Lock;


use BaksDev\Centrifugo\Server\Publish\CentrifugoPublishInterface;
use BaksDev\Centrifugo\Services\Notification\CentrifugoNotification;
use BaksDev\Centrifugo\Services\Notification\CentrifugoNotificationDTO;
use BaksDev\Products\Supply\Entity\Event\Lock\ProductSupplyLock;
use BaksDev\Products\Supply\Entity\Event\ProductSupplyEvent;
use BaksDev\Products\Supply\Repository\CurrentProductSupplyEvent\CurrentProductSupplyEventInterface;
use BaksDev\Products\Supply\UseCase\Admin\Lock\ProductSupplyLockDTO;
use BaksDev\Products\Supply\UseCase\Admin\Lock\ProductSupplyLockHandler;
use BaksDev\Users\User\Type\Id\UserUid;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Autoconfigure(shared: false)]
#[AsMessageHandler(priority: 0)]
final readonly class ProductSupplyLockDispatcher
{
    public function __construct(
        #[Target('productsSignLogger')] private LoggerInterface $logger,
        private TranslatorInterface $translator,
        private CurrentProductSupplyEventInterface $CurrentProductSupplyEventRepository,
        private ProductSupplyLockHandler $ProductSupplyLockHandler,
        private CentrifugoPublishInterface $CentrifugoPublish,
        private CentrifugoNotification $centrifugoNotification,
    ) {}

    public function __invoke(ProductSupplyUnlockMessage|ProductSupplyLockMessage $message): void
    {
        $ProductSupplyEvent = $this->CurrentProductSupplyEventRepository
            ->forMain($message->getProductSupply())
            ->find();

        if(false === ($ProductSupplyEvent instanceof ProductSupplyEvent))
        {
            $this->logger->critical(
                'products-supply: Не найдено событие поставки',
                [self::class.':'.__LINE__, var_export($message, true)],
            );

            return;
        }

        $ProductSupplyLockDTO = new ProductSupplyLockDTO($ProductSupplyEvent->getMain());

        /** В зависимости от типа сообщения - блокируем либо снимаем блокировку */
        match (true)
        {
            $message instanceof ProductSupplyLockMessage => $ProductSupplyLockDTO->lock(),
            $message instanceof ProductSupplyUnlockMessage => $ProductSupplyLockDTO->unlock(),
        };

        $ProductSupplyLock = $this->ProductSupplyLockHandler->handle($ProductSupplyLockDTO);


        if(false === $ProductSupplyLock instanceof ProductSupplyLock)
        {
            $this->logger->critical(
                sprintf('products-supply: Ошибка %s при обновлении блокировки поставки', $ProductSupplyLock),
                [self::class.':'.__LINE__, var_export($message, true)],
            );

            return;
        }

        /**
         * Отправляем сокет для обновления карточки поставки
         */

        $socket = $this->CentrifugoPublish
            ->addData(['supply' => (string) $ProductSupplyEvent->getMain()])
            ->addData(['lock' => false]) // разблокировка перетаскивания карточки на UI
            ->send('supplys'); // канал поставок

        if($socket && $socket->isError())
        {
            $this->logger->critical(
                message: 'products-supply: Ошибка при отправке информации о блокировке в Centrifugo',
                context: [
                    $socket->getMessage(),
                    var_export($message, true),
                    self::class.':'.__LINE__,
                ],
            );
        }

        /** Получатель - пользователь, создавший поставку */
        $receiver = $ProductSupplyEvent->getModifyUser();

        /** Если получатель не определен - не отправляем уведомление */
        if(false === ($receiver instanceof UserUid))
        {
            return;
        }

        /**
         * Отправляем системное уведомление
         */

        $header = $this->translator->trans(
            id: 'success.header',
            parameters: ['%number%' => $ProductSupplyEvent->getInvariable()->getNumber()],
            domain: 'products-supply.notify',
        );

        $notify = $this->translator->trans(
            id: 'success.message',
            domain: 'products-supply.notify',
        );

        $notification = new CentrifugoNotificationDTO(
            type: 'success',
            header: $header,
            message: $notify,
            identifier: (string) $receiver,
        );

        $this->centrifugoNotification
            ->addNotification($notification)
            ->receiver($receiver)
            ->notify(save: true);

    }
}
