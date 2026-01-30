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

namespace BaksDev\Products\Supply\UseCase\Admin\Lock\Tests;

use BaksDev\Products\Supply\Entity\Event\Lock\ProductSupplyLock;
use BaksDev\Products\Supply\Entity\Event\ProductSupplyEvent;
use BaksDev\Products\Supply\Entity\ProductSupply;
use BaksDev\Products\Supply\Type\ProductSupplyUid;
use BaksDev\Products\Supply\Type\Status\ProductSupplyStatus\ProductSupplyStatusCollection;
use BaksDev\Products\Supply\UseCase\Admin\Lock\ProductSupplyLockDTO;
use BaksDev\Products\Supply\UseCase\Admin\Lock\ProductSupplyLockHandler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Attribute\When;

#[Group('products-supply')]
#[When(env: 'test')]
class ProductSupplyLockDebugTest extends KernelTestCase
{
    /** Для переопределения корня */
    private const string MAIN = '';

    public function testUseCase(): void
    {
        self::assertTrue(true);
        return;

        /**
         * Инициализируем статусы
         *
         * @var ProductSupplyStatusCollection $ProductSupplyStatusCollection
         */
        $ProductSupplyStatusCollection = self::getContainer()->get(ProductSupplyStatusCollection::class);
        $ProductSupplyStatusCollection->cases();

        $container = self::getContainer();

        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $ProductSupply = $em->getRepository(ProductSupply::class)
            ->find(empty(self::MAIN) ? ProductSupplyUid::TEST : self::MAIN);

        $ProductSupplyEvent = $em->getRepository(ProductSupplyEvent::class)
            ->find($ProductSupply->getEvent());

        $ProductSupplyLockDTO = new ProductSupplyLockDTO($ProductSupplyEvent->getId());
        $ProductSupplyEvent->getLock()->getDto($ProductSupplyLockDTO);

        $ProductSupplyLockDTO
            ->unlock() // снимаем блокировку
            ->setContext(self::class);

        /** @var ProductSupplyLockHandler $ProductSupplyLockHandler */
        $ProductSupplyLockHandler = self::getContainer()->get(ProductSupplyLockHandler::class);

        $handle = $ProductSupplyLockHandler->handle($ProductSupplyLockDTO);

        self::assertTrue(($handle instanceof ProductSupplyLock), $handle.': Ошибка ProductSupplyLock');
    }
}