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

namespace BaksDev\Products\Supply\Messenger\ProductSupply\UpdateProductSupplyProductIds\Tests;

use BaksDev\Products\Product\Type\Barcode\ProductBarcode;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Supply\Messenger\ProductSupply\UpdateProductSupplyProductIds\UpdateProductSupplyProductIdsDispatcher;
use BaksDev\Products\Supply\Messenger\ProductSupply\UpdateProductSupplyProductIds\UpdateProductSupplyProductIdsMessage;
use BaksDev\Products\Supply\Type\ProductSupplyUid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

#[When(env: 'test')]
class UpdateProductSupplyProductIdsDispatcherTest extends KernelTestCase
{
    public function testUseCase(): void
    {
        self::assertTrue(true);

        // Бросаем событие консольной команды
        $dispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        $event = new ConsoleCommandEvent(new Command(), new StringInput(''), new NullOutput());
        $dispatcher->dispatch($event, 'console.command');

        /**
         * @var UpdateProductSupplyProductIdsDispatcher $UpdateProductSupplyProductDispatcher
         */
        $UpdateProductSupplyProductDispatcher = self::getContainer()
            ->get(UpdateProductSupplyProductIdsDispatcher::class);

        $message = new UpdateProductSupplyProductIdsMessage(
            supply: new ProductSupplyUid('019a12a6-628c-766c-a0df-ad881bc2091c'),
            barcode: new ProductBarcode('4650198060810'),
            product: new ProductUid('01876b4b-886d-7cff-a70e-b73559356089'),
            offerConst: new ProductOfferConst('01878a7a-aa3f-76ee-bdbb-17dd06a90340'),
            variationConst: new ProductVariationConst('01878a7a-aa31-7747-94aa-f87c53214cae'),
            modificationConst: new ProductModificationConst('01878a7a-aa30-7d92-974d-76de0fa3c47b')
        );

        $UpdateProductSupplyProductDispatcher($message);
    }
}