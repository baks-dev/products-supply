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

namespace BaksDev\Products\Supply\Type\Status\Tests;

use BaksDev\Products\Supply\Type\Status\ProductSupplyStatus;
use BaksDev\Products\Supply\Type\Status\ProductSupplyStatus\ProductSupplyStatusCollection;
use BaksDev\Products\Supply\Type\Status\ProductSupplyStatus\ProductSupplyStatusInterface;
use BaksDev\Products\Supply\Type\Status\ProductSupplyStatusType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

#[Group('products-supply')]
#[When(env: 'test')]
final class ProductSupplyStatusTest extends KernelTestCase
{
    public function testStatusCollection(): void
    {
        // Бросаем событие консольной команды
        $dispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        $event = new ConsoleCommandEvent(new Command(), new StringInput(''), new NullOutput());
        $dispatcher->dispatch($event, 'console.command');

        /** @var ProductSupplyStatusCollection $ProductSupplyStatusCollection */
        $ProductSupplyStatusCollection = self::getContainer()->get(ProductSupplyStatusCollection::class);

        $statuses = $ProductSupplyStatusCollection->cases();

        /** @var ProductSupplyStatusInterface $status */
        foreach($statuses as $status)
        {
            $ProductSupplyStatus = new ProductSupplyStatus($status->getValue());

            self::assertTrue($ProductSupplyStatus->equals($status::class)); // немспейс интерфейса
            self::assertTrue($ProductSupplyStatus->equals($status)); // объект интерфейса
            self::assertTrue($ProductSupplyStatus->equals($status->getValue())); // срока
            self::assertTrue($ProductSupplyStatus->equals($ProductSupplyStatus)); // объект класса

            $ProductSupplyStatusType = new ProductSupplyStatusType();
            $platform = $this
                ->getMockBuilder(AbstractPlatform::class)
                ->getMock();

            $convertToDatabase = $ProductSupplyStatusType->convertToDatabaseValue($ProductSupplyStatus, $platform);
            self::assertEquals($ProductSupplyStatus->getStatusValue(), $convertToDatabase);

            $convertToPHP = $ProductSupplyStatusType->convertToPHPValue($convertToDatabase, $platform);
            self::assertInstanceOf(ProductSupplyStatus::class, $convertToPHP);
            self::assertEquals($status, $convertToPHP->getStatus());
        }

        self::assertTrue(true);
    }
}
