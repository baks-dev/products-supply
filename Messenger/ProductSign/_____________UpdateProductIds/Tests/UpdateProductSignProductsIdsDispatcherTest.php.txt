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

namespace BaksDev\Products\Supply\Messenger\ProductSign\UpdateProductIds\Tests;

use BaksDev\Products\Product\Type\Barcode\ProductBarcode;
use BaksDev\Products\Supply\Messenger\ProductSign\UpdateProductIds\UpdateProductSignProductsIdsDispatcher;
use BaksDev\Products\Supply\Messenger\ProductSign\UpdateProductIds\UpdateProductSignProductsIdsMessage;
use BaksDev\Products\Supply\Type\ProductSupplyUid;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\User\Type\Id\UserUid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

#[When(env: 'test')]
class UpdateProductSignProductsIdsDispatcherTest extends KernelTestCase
{
    public function testUseCase(): void
    {
        self::assertTrue(true);

        // Бросаем событие консольной команды
        $dispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        $event = new ConsoleCommandEvent(new Command(), new StringInput(''), new NullOutput());
        $dispatcher->dispatch($event, 'console.command');

        $user = $_SERVER['TEST_USER'] ?? UserUid::TEST;
        $profile = $_SERVER['TEST_PROFILE'] ?? UserProfileUid::TEST;

        /** @var UpdateProductSignProductsIdsDispatcher $UpdateProductSignProductsIdsDispatcher */
        $UpdateProductSignProductsIdsDispatcher = self::getContainer()->get(UpdateProductSignProductsIdsDispatcher::class);

        $productMessage = new UpdateProductSignProductsIdsMessage(
            new ProductSupplyUid('019a3bea-cec4-7ad4-982e-bd469a78a187'),
            new ProductBarcode('4650198063125'),
            new UserUid($user),
            new UserProfileUid($profile),
        );

        $UpdateProductSignProductsIdsDispatcher($productMessage);
    }
}