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

namespace BaksDev\Products\Supply\Messenger\ProductSupply\ReceivedProductSupplyProduct\Tests;

use BaksDev\Products\Stocks\Messenger\ProductStockMessage;
use BaksDev\Products\Stocks\Type\Event\ProductStockEventUid;
use BaksDev\Products\Stocks\Type\Id\ProductStockUid;
use BaksDev\Products\Supply\Messenger\ProductSupply\ReceivedProductSupplyProduct\ReceivedProductSupplyProductDispatcher;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

#[When(env: 'test')]
class ReceivedProductSupplyProductDispatcherTest extends KernelTestCase
{
    public function testUseCase(): void
    {
        self::assertTrue(true);

        // Бросаем событие консольной команды
        $dispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        $event = new ConsoleCommandEvent(new Command(), new StringInput(''), new NullOutput());
        $dispatcher->dispatch($event, 'console.command');

        /** @var ReceivedProductSupplyProductDispatcher $UpdateProductSupplyProduct */
        $UpdateProductSupplyProduct = self::getContainer()
            ->get(ReceivedProductSupplyProductDispatcher::class);

        $message = new ProductStockMessage(
            new ProductStockUid('019a3474-aa13-7e73-8068-f55a9c33c579'),
            new ProductStockEventUid('019a3519-9d20-7417-87fc-59fe557b8839'),
        );

        $UpdateProductSupplyProduct($message);
    }
}