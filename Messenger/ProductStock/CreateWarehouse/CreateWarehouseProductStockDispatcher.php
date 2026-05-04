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

namespace BaksDev\Products\Supply\Messenger\ProductStock\CreateWarehouse;

use BaksDev\Products\Product\Repository\CurrentProductIdentifier\CurrentProductIdentifierByInvariableInterface;
use BaksDev\Products\Product\Repository\CurrentProductIdentifier\CurrentProductIdentifierResult;
use BaksDev\Products\Stocks\Entity\Stock\ProductStock;
use BaksDev\Products\Stocks\UseCase\Admin\Warehouse\Products\ProductStockDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Warehouse\WarehouseProductStockDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Warehouse\WarehouseProductStockHandler;
use BaksDev\Products\Supply\Entity\Event\Product\ProductSupplyProduct;
use BaksDev\Products\Supply\Entity\Event\ProductSupplyEvent;
use BaksDev\Products\Supply\Repository\CurrentProductSupplyEvent\CurrentProductSupplyEventInterface;
use BaksDev\Products\Supply\Type\Status\ProductSupplyStatus\Collection\ProductSupplyStatusDelivery;
use BaksDev\Products\Supply\UseCase\Admin\ProductStock\ProductStockSupplyDTO;
use BaksDev\Users\Profile\UserProfile\Repository\UserByUserProfile\UserByUserProfileInterface;
use BaksDev\Users\User\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Создает складскую заявку для поступления на склад каждого продукта из поставки
 * prev @see DeliveryController
 */
#[Autoconfigure(shared: false)]
#[AsMessageHandler(priority: 0)]
final readonly class CreateWarehouseProductStockDispatcher
{
    public function __construct(
        #[Target('productsStocksLogger')] private LoggerInterface $logger,
        private UserByUserProfileInterface $userByUserProfileRepository,
        private WarehouseProductStockHandler $WarehouseProductStockHandler,
        private CurrentProductSupplyEventInterface $CurrentProductSupplyEventRepository,
        private CurrentProductIdentifierByInvariableInterface $CurrentProductIdentifierByInvariableRepository
    ) {}

    public function __invoke(CreateWarehouseProductStockMessage $message): void
    {
        $ProductSupplyEvent = $this->CurrentProductSupplyEventRepository
            ->forMain($message->getSupply())
            ->find();

        if(false === ($ProductSupplyEvent instanceof ProductSupplyEvent))
        {
            $this->logger->critical(
                'products-supply: Событие ProductSupplyEvent не найдено',
                [self::class.':'.__LINE__, var_export($message, true)],
            );

            return;
        }

        if(false === $ProductSupplyEvent->getStatus()->equals(ProductSupplyStatusDelivery::class))
        {
            return;
        }

        if(true === $ProductSupplyEvent->getProduct()->isEmpty())
        {
            return;
        }

        /** Пользователь по ID профиля */
        $User = $this->userByUserProfileRepository
            ->forProfile($message->getProfile())
            ->find();

        if(false === ($User instanceof User))
        {
            $this->logger->critical(
                'products-supply: Пользователя по идентификатору профиля не найдено',
                [self::class.':'.__LINE__, var_export($message, true)],
            );

            return;
        }

        /**
         * На каждый продукт из поставки создаем заявку на поступление
         *
         * @var ProductSupplyProduct $ProductSupplyProduct
         */
        foreach($ProductSupplyEvent->getProduct() as $ProductSupplyProduct)
        {
            $CurrentProductIdentifierResult = $this->CurrentProductIdentifierByInvariableRepository
                ->forProductInvariable($ProductSupplyProduct->getProduct())
                ->find();


            if(false === ($CurrentProductIdentifierResult instanceof CurrentProductIdentifierResult))
            {
                $this->logger->critical(
                    'products-supply: Активные идентификаторы продукта не найдены',
                    [self::class.':'.__LINE__, $ProductSupplyProduct->getProduct()],
                );

                continue;
            }

            $WarehouseProductStockDTO = new WarehouseProductStockDTO();
            $WarehouseProductStockDTO->newId(); // для инстанса нового объекта
            $WarehouseProductStockDTO->setComment($message->getComment());

            /** Product */
            $ProductStockDTO = new ProductStockDTO();
            $ProductStockDTO
                ->setTotal($ProductSupplyProduct->getTotal())
                ->setProduct($CurrentProductIdentifierResult->getProduct())
                ->setOffer($CurrentProductIdentifierResult->getOfferConst())
                ->setVariation($CurrentProductIdentifierResult->getVariationConst())
                ->setModification($CurrentProductIdentifierResult->getModificationConst());

            $WarehouseProductStockDTO->addProduct($ProductStockDTO);

            /** Invariable */
            $WarehouseProductStockDTO->getInvariable()
                ->setUsr($User->getId())
                ->setProfile($message->getProfile())
                ->setNumber($ProductSupplyEvent->getInvariable()->getNumber());

            /** Связь с поставкой */
            $ProductStockSupplyDTO = new ProductStockSupplyDTO()
                ->setValue((string) $ProductSupplyEvent->getMain());
            $WarehouseProductStockDTO->setSupply($ProductStockSupplyDTO);

            $handle = $this->WarehouseProductStockHandler->handle($WarehouseProductStockDTO);

            if(false === ($handle instanceof ProductStock))
            {
                $this->logger->info(
                    message: sprintf(
                        'products-supply: Ошибка при создании заявки %s на поступление продукции на склад для поставки %s',
                        $handle->getId(),
                        $ProductSupplyEvent->getMain(),
                    ),
                    context: [
                        self::class.':'.__LINE__,
                        var_export($message, true),
                    ],
                );

                continue;
            }


            $this->logger->info(
                message: sprintf(
                    'Создали заявку %s на поступление продукции на склад для поставки %s',
                    $handle->getId(),
                    $message->getSupply(),
                ),
                context: [
                    self::class.':'.__LINE__,
                    var_export($message, true),
                ],
            );
        }
    }
}