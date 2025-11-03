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

namespace BaksDev\Products\Supply\Repository\ProductSign\AllProductSignEventsByProductSupply;

use BaksDev\Core\Doctrine\ORMQueryBuilder;
use BaksDev\Products\Product\Type\Barcode\ProductBarcode;
use BaksDev\Products\Sign\Entity\Code\ProductSignCode;
use BaksDev\Products\Sign\Entity\Event\ProductSignEvent;
use BaksDev\Products\Sign\Entity\Event\Supply\ProductSignSupply;
use BaksDev\Products\Sign\Entity\Invariable\ProductSignInvariable;
use BaksDev\Products\Sign\Entity\Modify\ProductSignModify;
use BaksDev\Products\Sign\Entity\ProductSign;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus;
use BaksDev\Products\Supply\Entity\ProductSupply;
use BaksDev\Products\Supply\Type\ProductSign\Status\ProductSignStatusSupply;
use BaksDev\Products\Supply\Type\ProductSupplyUid;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\User\Entity\User;
use BaksDev\Users\User\Type\Id\UserUid;
use Doctrine\ORM\Query\Expr\Join;
use Generator;
use InvalidArgumentException;

final class AllProductSignByProductSupplyRepository implements AllProductSignByProductSupplyInterface
{
    private ProductSupplyUid|false $supply = false;

    private ProductBarcode|false $code = false;

    private UserUid|false $user = false;

    private UserProfileUid|false $profile = false;

    public function __construct(
        private readonly ORMQueryBuilder $ORMQueryBuilder,
    ) {}

    public function forUser(User|UserUid $user): self
    {
        if($user instanceof User)
        {
            $user = $user->getId();
        }

        $this->user = $user;
        return $this;
    }

    public function forProfile(UserProfile|UserProfileUid $profile): self
    {
        if($profile instanceof UserProfile)
        {
            $profile = $profile->getId();
        }

        $this->profile = $profile;
        return $this;
    }

    public function forSupply(ProductSupply|ProductSupplyUid $supply): self
    {
        if($supply instanceof ProductSupply)
        {
            $supply = $supply->getId();
        }

        $this->supply = $supply;
        return $this;
    }

    public function forCode(ProductBarcode $code): self
    {
        $this->code = $code;
        return $this;
    }

    /**
     * @return Generator<int, ProductSignEvent>|false
     */
    public function findAll(): Generator|false
    {
        if(
            false === ($this->supply instanceof ProductSupplyUid) ||
            false === ($this->code instanceof ProductBarcode) ||
            false === ($this->user instanceof UserUid) ||
            false === ($this->profile instanceof UserProfileUid)
        )
        {
            throw new InvalidArgumentException('Не определено одно из обязательных свойств: supply, code, user, profile');
        }

        $orm = $this->ORMQueryBuilder->createQueryBuilder(self::class);

        $orm->from(ProductSign::class, 'main');

        /**
         * ProductSignInvariable
         * без идентификатора продукта
         */
        $orm
            ->join(
                ProductSignInvariable::class,
                'invariable',
                Join::WITH,
                '
                    invariable.main = main.id AND 
                    invariable.usr = :usr AND
                    invariable.profile = :profile AND
                    invariable.product IS NULL
                    '
            )->setParameter(
                key: 'usr',
                value: $this->user,
                type: UserUid::TYPE,
            )
            ->setParameter(
                key: 'profile',
                value: $this->profile,
                type: UserProfileUid::TYPE,
            );

        /** Event */
        $orm
            ->select('event')
            ->join(
                ProductSignEvent::class,
                'event',
                Join::WITH,
                '
                    event.id = main.event AND
                    event.status = :status
            ')
            ->setParameter(
                key: 'status',
                value: ProductSignStatusSupply::class,
                type: ProductSignStatus::TYPE,
            );

        /**
         * Поставка
         */
        $orm
            ->join(
                ProductSignSupply::class,
                'supply',
                Join::WITH,
                '
                    supply.event = main.event AND
                    supply.supply = :supply
            ')
            ->setParameter(
                key: 'supply',
                value: $this->supply,
                type: ProductSupplyUid::TYPE,
            );

        /** Code */
        $orm
            ->join(
                ProductSignCode::class,
                'code',
                Join::WITH,
                "
                    code.event = main.event AND
                    code.code LIKE :code
                    "
            )
            ->setParameter(
                key: 'code',
                value: '%'.$this->code.'%',
                type: ProductBarcode::TYPE,

            );

        $orm
            ->join(
                ProductSignModify::class,
                'modify',
                Join::WITH,
                'modify.event = main.event',
            );

        /**
         *  Сортировка по профилю:
         *  если у владельца имеется Честный знак - возвращает
         *  если нет у владельца - берет у партнера
         */
        $orm->addOrderBy("CASE invariable.profile WHEN :profile THEN false ELSE true END");

        /** Сортируем по дате, выбирая самый старый знак и мешок */
        $orm->addOrderBy('modify.modDate');
        $orm->addOrderBy('invariable.part');

        $result = $orm->getResult();

        if(null === $result)
        {
            return false;
        }

        yield from $result;
    }
}
