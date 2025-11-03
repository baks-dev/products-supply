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

namespace BaksDev\Products\Supply\Messenger\ProductSupply\FindProductSupplyProductForUpdate;

use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Products\Product\Messenger\ProductMessage;
use BaksDev\Products\Supply\Messenger\ProductSupply\UpdateProductSupplyProductIds\UpdateProductSupplyProductIdsMessage;
use BaksDev\Products\Supply\Repository\AllProductsSupplyByProductsBarcode\AllProductsSupplyByProductsBarcodeInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * При изменении продукта В СИСТЕМЕ находит соответствующие продукты В ПОСТАВКЕ
 */
#[AsMessageHandler(priority: 0)]
final readonly class FindProductSupplyProductForUpdateDispatcher
{
    public function __construct(
        #[Target('productsSupplyLogger')] private LoggerInterface $logger,
        private MessageDispatchInterface $messageDispatch,
        private AllProductsSupplyByProductsBarcodeInterface $allProductsSupplyByProductsBarcodeRepository,
    ) {}

    public function __invoke(ProductMessage $message): void
    {
        /**
         * Идентификаторы для обновления продукции в поставке, ЕСЛИ:
         * - были неизвестные продукты в поставке
         * - статус поставки New (Новая)
         */
        $updatedProductIds = $this->allProductsSupplyByProductsBarcodeRepository
            ->forProduct($message->getId())
            ->findAll();

        if(false !== $updatedProductIds && $updatedProductIds->valid())
        {
            $this->logger->info(
                message: sprintf('Обновляем идентификаторы продукта %s в поставках', $message->getId()),
                context: [
                    self::class.':'.__LINE__,
                    var_export($message, true),
                ],
            );
        }

        foreach($updatedProductIds as $productIds)
        {
            $this->messageDispatch
                ->dispatch(
                    message: new UpdateProductSupplyProductIdsMessage(
                        supply: $productIds->getSupplyId(),
                        barcode: $productIds->getBarcode(),
                        product: $productIds->getProductId(),
                        offerConst: $productIds->getProductOfferConst(),
                        variationConst: $productIds->getProductVariationConst(),
                        modificationConst: $productIds->getProductModificationConst()
                    ),
                    transport: 'products-supply',
                );
        }
    }
}