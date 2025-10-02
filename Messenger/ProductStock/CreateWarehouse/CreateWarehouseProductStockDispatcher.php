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

namespace BaksDev\Products\Supply\Messenger\ProductStock\CreateWarehouse;

use BaksDev\Products\Stocks\Entity\Stock\ProductStock;
use BaksDev\Products\Stocks\UseCase\Admin\Warehouse\Products\ProductStockDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Warehouse\WarehouseProductStockDTO;
use BaksDev\Products\Stocks\UseCase\Admin\Warehouse\WarehouseProductStockHandler;
use BaksDev\Products\Supply\Repository\OneProductSupplyByEvent\OneProductSupplyByEventInterface;
use BaksDev\Products\Supply\Repository\OneProductSupplyByEvent\OneProductSupplyResult;
use BaksDev\Products\Supply\Repository\OneProductSupplyByEvent\ProductSupplyProductResult;
use BaksDev\Products\Supply\Type\Status\ProductSupplyStatus\Collection\ProductSupplyStatusDelivery;
use BaksDev\Products\Supply\UseCase\Admin\ProductStock\ProductStockSupplyDTO;
use BaksDev\Users\Profile\UserProfile\Repository\UserByUserProfile\UserByUserProfileInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Создает заявку для поступления на склад каждого продукта из поставки
 * prev @see DeliveryController
 */
#[AsMessageHandler(priority: 0)]
final readonly class CreateWarehouseProductStockDispatcher
{
    public function __construct(
        #[Target('productsStocksLogger')] private LoggerInterface $logger,
        private OneProductSupplyByEventInterface $oneProductSupplyByEventRepository,
        private UserByUserProfileInterface $userByUserProfileRepository,
        private WarehouseProductStockHandler $WarehouseProductStockHandler,
    ) {}

    public function __invoke(CreateWarehouseProductStockMessage $message): void
    {
        $OneProductSupply = $this->oneProductSupplyByEventRepository
            ->find($message->getSupply());

        if(false === ($OneProductSupply instanceof OneProductSupplyResult))
        {
            $this->logger->critical(
                message: sprintf('Не найдено информации о поставке %s', $message->getSupply()),
                context: [
                    self::class.':'.__LINE__,
                    var_export($message, true),
                ],
            );

            return;
        }

        /** Если статус поставки не delivery (Доставка) - прерываем работу */
        if(false === $OneProductSupply->getStatus()->equals(ProductSupplyStatusDelivery::class))
        {
            return;
        }

        /** Пользователь по ID профиля */
        $user = $this->userByUserProfileRepository
            ->forProfile($message->getProfile())
            ->find();

        /**
         * На каждый продукт из поставки создаем заявку на поступление
         * @var ProductSupplyProductResult $product
         */
        foreach($OneProductSupply->getProducts() as $product)
        {
            $WarehouseProductStockDTO = new WarehouseProductStockDTO();
            $WarehouseProductStockDTO->newId(); // для инстанса нового объекта

            /** getComment */
            $WarehouseProductStockDTO->setComment($message->getComment());

            /** Product */
            $ProductStockDTO = new ProductStockDTO();
            $ProductStockDTO
                ->setTotal($product->getTotal())
                ->setProduct($product->getProduct())
                ->setOffer($product->getOfferConst())
                ->setVariation($product->getVariationConst())
                ->setModification($product->getModificationConst());

            $WarehouseProductStockDTO->addProduct($ProductStockDTO);

            /** Invariable */
            $WarehouseProductStockDTO->getInvariable()
                ->setUsr($user->getId())
                ->setProfile($message->getProfile())
                ->setNumber($OneProductSupply->getContainer());

            /** Связь с поставкой */
            $ProductStockSupplyDTO = new ProductStockSupplyDTO();
            $ProductStockSupplyDTO->setSupply((string) $OneProductSupply->getId());
            $WarehouseProductStockDTO->setSupply($ProductStockSupplyDTO);

            $handle = $this->WarehouseProductStockHandler->handle($WarehouseProductStockDTO);

            if(false === ($handle instanceof ProductStock))
            {
                $this->logger->info(
                    message: sprintf(
                        'products-stocks: Ошибка при создании заявки %s на поступление продукции на склад для поставки %s',
                        $handle->getId(), $OneProductSupply->getId()
                    ),
                    context: [
                        self::class.':'.__LINE__,
                        var_export($message, true),
                    ],
                );

                continue;
            }

            if(true === ($handle instanceof ProductStock))
            {
                $this->logger->info(
                    message: sprintf(
                        'products-stocks: Создали заявку %s на поступление продукции на склад для поставки %s',
                        $handle->getId(), $OneProductSupply->getId()
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