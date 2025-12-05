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

namespace BaksDev\Products\Supply\Controller\Admin\Statuses;

use BaksDev\Centrifugo\Server\Publish\CentrifugoPublishInterface;
use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Products\Supply\Entity\Event\ProductSupplyEvent;
use BaksDev\Products\Supply\Entity\ProductSupply;
use BaksDev\Products\Supply\Forms\Statuses\ProductSupplyIdDTO;
use BaksDev\Products\Supply\Forms\Supplys\ProductSupplysDTO;
use BaksDev\Products\Supply\Forms\Supplys\ProductSupplysForm;
use BaksDev\Products\Supply\Repository\CurrentProductSupplyEvent\CurrentProductSupplyEventInterface;
use BaksDev\Products\Supply\Repository\ExistProductSupplyByStatus\ExistProductSupplyByStatusInterface;
use BaksDev\Products\Supply\Type\Status\ProductSupplyStatus\ProductSupplyStatusCollection;
use BaksDev\Products\Supply\UseCase\Admin\Edit\EditProductSupplyHandler;
use BaksDev\Products\Supply\UseCase\Admin\Statuses\ProductSupplyStatusesDTO;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Изменяет статус поставки на тот, который был передан в url
 * @see assets/products-supply/supply_draggable.js
 */
#[AsController]
#[RoleSecurity('ROLE_PRODUCTS_SUPPLY_STATUS')]
final class StatusController extends AbstractController
{
    public const string NAME = 'admin.supply.status';

    /** Коллекция неудачных попыток обновления статуса поставок */
    private array|null $unsuccessful = null;

    #[Route(
        path: '/admin/products/supply/status/{status}',
        name: self::NAME,
        methods: ['POST'],
        condition: "request.headers.get('X-Requested-With') === 'XMLHttpRequest'",
    )]
    public function status(
        #[Target('productsSupplyLogger')] LoggerInterface $logger,
        Request $request,
        CentrifugoPublishInterface $publish,
        ProductSupplyStatusCollection $productSupplyStatusCollection,
        CurrentProductSupplyEventInterface $currentProductSupplyEventRepository,
        ExistProductSupplyByStatusInterface $existProductSupplyByStatusRepository,
        EditProductSupplyHandler $editProductSupplyHandler,

        string $status,
    ): Response
    {
        $ProductSupplysForm = $this->createForm(
            type: ProductSupplysForm::class,
            data: $ProductSupplysDTO = new ProductSupplysDTO(),
            options: ['action' => $this->generateUrl('products-supply:'.self::NAME, ['status' => $status])]
        )
            ->handleRequest($request);

        if($ProductSupplysForm->isSubmitted())
        {
            /**
             * @var ProductSupplyIdDTO $ProductSupplyIdDTO
             */
            foreach($ProductSupplysDTO->getSupplys() as $ProductSupplyIdDTO)
            {
                /** Скрываем поставку у всех пользователей */
                $publish
                    ->addData(['supply' => (string) $ProductSupplyIdDTO->getId()])
                    ->addData(['profile' => (string) $this->getCurrentProfileUid()])
                    ->send('supplys');

                /** Активное событие поставки */
                $ProductSupplyEvent = $currentProductSupplyEventRepository
                    ->find($ProductSupplyIdDTO->getId());

                if(false === ($ProductSupplyEvent instanceof ProductSupplyEvent))
                {
                    $this->unsuccessful[] = $ProductSupplyIdDTO->getId();

                    $logger->critical(
                        message: sprintf('Не найдено событие ProductSupplyEvent по ID: %s',
                            $ProductSupplyIdDTO->getId()),
                        context: [self::class.':'.__LINE__]
                    );

                    continue;
                }

                /** Инициализируем объект по переданному статусу */
                $newProductSupplyStatus = $productSupplyStatusCollection->from($status);

                /** Текущий статус */
                $currentProductSupplyStatus = $ProductSupplyEvent->getStatus();
                $previousStatus = $currentProductSupplyStatus->previous($newProductSupplyStatus->getStatus());

                /**
                 * Статус поставки можно двигать только вперед
                 */
                if(false === ($currentProductSupplyStatus->equals($previousStatus)))
                {
                    $this->unsuccessful[] = $ProductSupplyIdDTO->getId();

                    $logger->info(
                        message: sprintf('Неудачная попытка изменения статуса для поставки %s (%s): %s -> %s ',
                            $ProductSupplyEvent->getMain(),
                            $ProductSupplyEvent->getInvariable()->getNumber(),
                            $currentProductSupplyStatus->getStatus()->getValue(),
                            $newProductSupplyStatus->getStatus()->getValue()
                        ),
                        context: [self::class.':'.__LINE__]
                    );

                    continue;
                }

                /**
                 * Невозможно применить повторно статус
                 */
                $isExistsStatus = $existProductSupplyByStatusRepository
                    ->forProductSupply($ProductSupplyIdDTO->getId())
                    ->forStatus($newProductSupplyStatus)
                    ->isExists();

                if(true === $isExistsStatus)
                {
                    $this->unsuccessful[] = $ProductSupplyIdDTO->getId();

                    $logger->info(
                        message: sprintf('Невозможно применить повторно статус для поставки %s (%s): %s -> %s ',
                            $ProductSupplyEvent->getMain(),
                            $ProductSupplyEvent->getInvariable()->getNumber(),
                            $currentProductSupplyStatus->getStatus()->getValue(),
                            $newProductSupplyStatus->getStatus()->getValue()
                        ),
                        context: [self::class.':'.__LINE__]
                    );

                    continue;
                }

                $ProductSupplyStatusesDTO = new ProductSupplyStatusesDTO($ProductSupplyEvent->getId());
                $ProductSupplyStatusesDTO->setStatus($newProductSupplyStatus);

                $ProductSupply = $editProductSupplyHandler->handle($ProductSupplyStatusesDTO);

                if(true === $ProductSupply instanceof ProductSupply)
                {
                    $logger->info(
                        message: sprintf('Статус поставки %s изменен на %s',
                            $ProductSupply->getId(),
                            $ProductSupplyStatusesDTO->getStatus()
                        ),
                        context: [self::class.':'.__LINE__]
                    );
                }

                if(false === $ProductSupply instanceof ProductSupply)
                {
                    $this->unsuccessful[] = $ProductSupplyIdDTO->getId();
                }
            }

            /** Ответ в случае какой-либо ошибки при изменении статуса */
            if(null !== $this->unsuccessful)
            {
                return new JsonResponse(
                    [
                        'type' => 'danger',
                        'header' => 'Ошибка обновления статуса поставок',
                        'message' => sprintf('Статусы для поставок #%s не обновлены', implode(', ', $this->unsuccessful)),
                        'status' => 400,
                    ],
                    400,
                );
            }

            return new JsonResponse(
                [
                    'type' => 'success',
                    'header' => 'Изменение статуса поставок',
                    'message' => 'Статусы поставок успешно обновлены',
                    'status' => 200,
                ],
                200,
            );
        }

        return new JsonResponse(
            [
                'type' => 'danger',
                'header' => 'Ошибка обновления статуса поставок',
                'message' => 'Невозможно изменить статус',
                'status' => 400,
            ],
        );
    }
}
