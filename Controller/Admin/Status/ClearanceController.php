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

declare (strict_types=1);

namespace BaksDev\Products\Supply\Controller\Admin\Status;

use BaksDev\Centrifugo\Server\Publish\CentrifugoPublishInterface;
use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Products\Supply\Entity\Event\ProductSupplyEvent;
use BaksDev\Products\Supply\Entity\ProductSupply;
use BaksDev\Products\Supply\Forms\Statuses\Clearance\ClearanceProductSupplyDTO;
use BaksDev\Products\Supply\Forms\Statuses\Clearance\ClearanceProductSupplyForm;
use BaksDev\Products\Supply\Forms\Statuses\ProductSupplyIdDTO;
use BaksDev\Products\Supply\Repository\CurrentProductSupplyEvent\CurrentProductSupplyEventInterface;
use BaksDev\Products\Supply\Type\Status\ProductSupplyStatus\Collection\ProductSupplyStatusClearance;
use BaksDev\Products\Supply\Type\Status\ProductSupplyStatus\Collection\ProductSupplyStatusNew;
use BaksDev\Products\Supply\UseCase\Admin\Clearance\ProductSupplyStatusClearanceDTO;
use BaksDev\Products\Supply\UseCase\Admin\Edit\EditProductSupplyHandler;
use BaksDev\Products\Supply\UseCase\Admin\Edit\Product\EditProductSupplyProductDTO;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[RoleSecurity('ROLE_PRODUCT_SUPPLY_STATUS')]
final class ClearanceController extends AbstractController
{
    /** Коллекция неудачных попыток обновления статуса поставок */
    private array|null $unsuccessful = null;

    /** Номера контейнеров обрабатываемых поставок */
    private array $numbers = [];

    /**
     * Растаможка поставок
     */
    #[Route('/admin/products/supply/clearance', name: 'admin.supply.clearance', methods: ['GET', 'POST'])]
    public function clearance(
        #[Target('productsSupplyLogger')] LoggerInterface $logger,
        Request $request,
        CentrifugoPublishInterface $publish,
        CurrentProductSupplyEventInterface $currentProductSupplyEventRepository,
        EditProductSupplyHandler $editProductSupplyHandler,
    ): Response
    {
        $clearanceProductSupplyForm = $this
            ->createForm(
                ClearanceProductSupplyForm::class,
                $ClearanceProductSuppliesDTO = new ClearanceProductSupplyDTO(),
                ['action' => $this->generateUrl('products-supply:admin.supply.clearance')],
            )
            ->handleRequest($request);

        if(
            $clearanceProductSupplyForm->isSubmitted() &&
            $clearanceProductSupplyForm->isValid() &&
            $clearanceProductSupplyForm->has('clearance')
        )
        {

            $this->refreshTokenForm($clearanceProductSupplyForm);

            /**
             * @var ProductSupplyIdDTO $ProductSupplyIdDTO
             */
            foreach($ClearanceProductSuppliesDTO->getSupplys() as $ProductSupplyIdDTO)
            {
                /** Скрываем поставку у всех пользователей */
                $publish
                    ->addData(['supply' => (string) $ProductSupplyIdDTO->getId()])
                    ->send('supplys');

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

                /** Проверяем, что поставка перемещена из статуса New */
                if(false === $ProductSupplyEvent->getStatus()->equals(ProductSupplyStatusNew::class))
                {
                    $this->unsuccessful[] = $ProductSupplyIdDTO->getId();

                    $logger->critical(
                        message: sprintf('Попытка присвоить ГТД для поставки %s (%s) не из статуса: %s',
                            $ProductSupplyEvent->getMain(),
                            $ProductSupplyEvent->getInvariable()->getContainer(),
                            ProductSupplyStatusNew::STATUS
                        ),
                        context: [self::class.':'.__LINE__]
                    );

                    continue;
                }

                $ProductSupplyStatusClearanceDTO = new ProductSupplyStatusClearanceDTO($ProductSupplyEvent->getId());
                $ProductSupplyEvent->getDto($ProductSupplyStatusClearanceDTO);

                $existUndefinedProduct = $ProductSupplyStatusClearanceDTO->getProduct()->exists(
                    fn(int $k, EditProductSupplyProductDTO $product) => null === $product->getProduct()
                );

                if(true === $existUndefinedProduct)
                {
                    $this->unsuccessful[] = $ProductSupplyIdDTO->getId();

                    $logger->critical(
                        message: sprintf('Невозможно изменить статус поставки %s с неопределенными продуктами',
                            $ProductSupplyIdDTO->getId()),
                        context: [self::class.':'.__LINE__]
                    );

                    continue;
                }

                /** Присваиваем номер ГТД - единый для всех выбранных поставок */
                $ProductSupplyStatusClearanceDTO->getInvariable()->setNumber($ClearanceProductSuppliesDTO->getNumber());

                $handle = $editProductSupplyHandler->handle($ProductSupplyStatusClearanceDTO);

                if(true === $handle instanceof ProductSupply)
                {
                    $logger->info(
                        message: sprintf('Статус поставки %s изменен на `%s`',
                            $handle->getId(), $ProductSupplyStatusClearanceDTO->getStatus()),
                        context: [self::class.':'.__LINE__]
                    );
                }

                if(false === $handle instanceof ProductSupply)
                {
                    $this->unsuccessful[] = $ProductSupplyIdDTO->getId();
                }
            }

            if(null !== $this->unsuccessful)
            {
                $this->addFlash(
                    'page.edit',
                    'danger.update',
                    'products-supply.admin',
                    $this->unsuccessful,
                );

                return $this->redirectToReferer();
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

        /**
         * @var ProductSupplyIdDTO $supply
         */
        foreach($ClearanceProductSuppliesDTO->getSupplys() as $supply)
        {
            /** Активное событие поставки */
            $supplyEvent = $currentProductSupplyEventRepository->find($supply->getId());

            if(false === ($supplyEvent instanceof ProductSupplyEvent))
            {
                continue;
            }

            $this->numbers[] = $supplyEvent->getInvariable()->getContainer();
        }

        return $this->render([
            'form' => $clearanceProductSupplyForm->createView(),
            'numbers' => $this->numbers,
            'status' => ProductSupplyStatusClearance::STATUS,
        ]);
    }
}
