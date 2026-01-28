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

namespace BaksDev\Products\Supply\UseCase\Admin\Tests\Edit;

use BaksDev\Products\Supply\Entity\Event\ProductSupplyEvent;
use BaksDev\Products\Supply\Entity\ProductSupply;
use BaksDev\Products\Supply\Type\ProductSupplyUid;
use BaksDev\Products\Supply\Type\Status\ProductSupplyStatus;
use BaksDev\Products\Supply\Type\Status\ProductSupplyStatus\Collection\ProductSupplyStatusClearance;
use BaksDev\Products\Supply\Type\Status\ProductSupplyStatus\ProductSupplyStatusCollection;
use BaksDev\Products\Supply\UseCase\Admin\Edit\EditProductSupplyHandler;
use BaksDev\Products\Supply\UseCase\Admin\Statuses\ProductSupplyStatusesDTO;
use Doctrine\ORM\EntityManagerInterface;
use NewProductSupplyHandlerTest;
use PHPUnit\Framework\Attributes\DependsOnClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Attribute\When;

/** next @see ProductSupplyStatusClearedTest */
#[Group('products-supply')]
#[Group('products-supply-usecase')]
#[When(env: 'test')]
class EditProductSupplyTest extends KernelTestCase
{
    /** Для переопределения корня */
    private const string MAIN = '';

    #[DependsOnClass(NewProductSupplyHandlerTest::class)]
    public function testUseCase(): void
    {
        /**
         * Инициализируем статусы
         *
         * @var ProductSupplyStatusCollection $ProductSupplyStatusCollection
         */
        $ProductSupplyStatusCollection = self::getContainer()->get(ProductSupplyStatusCollection::class);
        $ProductSupplyStatusCollection->cases();

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $ProductSupply = $em->getRepository(ProductSupply::class)
            ->find(empty(self::MAIN) ? ProductSupplyUid::TEST : self::MAIN);

        self::assertInstanceOf(ProductSupply::class, $ProductSupply);

        $ProductSupplyEvent = $em->getRepository(ProductSupplyEvent::class)
            ->find($ProductSupply->getEvent());

        self::assertInstanceOf(ProductSupplyEvent::class, $ProductSupplyEvent);

        $ProductSupplyStatusesDTO = new ProductSupplyStatusesDTO($ProductSupplyEvent->getId());
        $ProductSupplyStatusesDTO->setStatus(new ProductSupplyStatus(ProductSupplyStatusClearance::STATUS));

        /** @var EditProductSupplyHandler $EditProductSupplyHandler */
        $EditProductSupplyHandler = self::getContainer()->get(EditProductSupplyHandler::class);
        $handle = $EditProductSupplyHandler->handle($ProductSupplyStatusesDTO);

        self::assertTrue(($handle instanceof ProductSupply), $handle.': Ошибка ProductSupply');
    }
}