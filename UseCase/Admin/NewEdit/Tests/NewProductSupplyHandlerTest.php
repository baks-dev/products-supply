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

use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Sign\UseCase\Admin\New\Tests\NewUndefinedProductSignHandlerTest;
use BaksDev\Products\Supply\Entity\Event\ProductSupplyEvent;
use BaksDev\Products\Supply\Entity\ProductSupply;
use BaksDev\Products\Supply\Type\ProductSupplyUid;
use BaksDev\Products\Supply\Type\Status\ProductSupplyStatus\ProductSupplyStatusCollection;
use BaksDev\Products\Supply\UseCase\Admin\NewEdit\Invariable\AddProductSupplyInvariableDTO;
use BaksDev\Products\Supply\UseCase\Admin\NewEdit\NewEditProductSupplyDTO;
use BaksDev\Products\Supply\UseCase\Admin\NewEdit\NewEditProductSupplyHandler;
use BaksDev\Products\Supply\UseCase\Admin\NewEdit\Personal\AddProductSupplyPersonalDTO;
use BaksDev\Products\Supply\UseCase\Admin\NewEdit\Product\AddProductSupplyProductDTO;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\User\Type\Id\UserUid;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Attribute\When;

/** next @see EditProductSupplyTest */
#[Group('products-supply')]
#[Group('products-supply-usecase')]
#[When(env: 'test')]
class NewProductSupplyHandlerTest extends KernelTestCase
{
    public static function setUpBeforeClass(): void
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
            ->find(ProductSupplyUid::TEST);

        if($ProductSupply)
        {
            $em->remove($ProductSupply);
        }

        $ProductSupplyEvent = $em->getRepository(ProductSupplyEvent::class)
            ->findBy(['main' => ProductSupplyUid::TEST]);

        foreach($ProductSupplyEvent as $remove)
        {
            $em->remove($remove);
        }

        $em->flush();

        /** Создаем тестовый Честный знак */
        NewUndefinedProductSignHandlerTest::setUpBeforeClass();
        new NewUndefinedProductSignHandlerTest('')->testUseCase();
    }

    public function testUseCase(): void
    {
        /** Поставка */
        $NewProductSupplyDTO = new NewEditProductSupplyDTO();

        /** Снимаем блокировку при тестировании */
        $NewProductSupplyDTO->getLock()->unLock();

        /**
         * Номер контейнера
         */
        $NewProductSupplyInvariableDTO = new AddProductSupplyInvariableDTO();
        $NewProductSupplyDTO->setInvariable($NewProductSupplyInvariableDTO);

        /**
         * Информация о пользователе
         */
        $NewProductSupplyPersonalDTO = new AddProductSupplyPersonalDTO();
        $NewProductSupplyPersonalDTO->setUsr(new UserUid(UserUid::TEST));
        $NewProductSupplyPersonalDTO->setProfile(new UserProfileUid(UserProfileUid::TEST));
        $NewProductSupplyDTO->setPersonal($NewProductSupplyPersonalDTO);

        /**
         * Продукт
         */
        $NewProductSupplyProductDTO = new AddProductSupplyProductDTO();
        $NewProductSupplyProductDTO->setTotal(1); // на один Честный знак один продукт !!!

        $NewProductSupplyProductDTO
            ->setProduct(new ProductUid)
            ->setOfferConst(new ProductOfferConst)
            ->setVariationConst(new ProductVariationConst)
            ->setModificationConst(new ProductModificationConst);

        $NewProductSupplyDTO->addProduct($NewProductSupplyProductDTO);

        /**
         * Сохранение
         */

        /** @var NewEditProductSupplyHandler $ProductSupplyHandler */
        $ProductSupplyHandler = self::getContainer()->get(NewEditProductSupplyHandler::class);
        $handle = $ProductSupplyHandler->handle($NewProductSupplyDTO);

        self::assertTrue(($handle instanceof ProductSupply), $handle.': Ошибка ProductSupply');
    }
}
