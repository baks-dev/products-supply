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

namespace BaksDev\Products\Supply\Repository\ExistProductSupplyByContainerNumber;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Products\Supply\Entity\Event\Invariable\ProductSupplyInvariable;
use BaksDev\Products\Supply\Entity\Event\ProductSupplyEvent;
use BaksDev\Products\Supply\Entity\ProductSupply;
use BaksDev\Products\Supply\Type\Status\ProductSupplyStatus;
use BaksDev\Products\Supply\Type\Status\ProductSupplyStatus\ProductSupplyStatusInterface;
use BaksDev\Products\Supply\UseCase\Admin\New\Invariable\NewProductSupplyInvariableDTO;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\User\Entity\User;
use BaksDev\Users\User\Type\Id\UserUid;
use Doctrine\DBAL\Types\Types;

/**
 * Проверяет существование записи с поставкой по номеру контейнера и принадлежности к пользователю
 */
final class ExistProductSupplyByContainerNumberRepository implements ExistProductSupplyByContainerNumberInterface
{
    private UserUid|false $user = false;

    private UserProfileUid|false $profile = false;

    private ?ProductSupplyStatus $status = null;

    public function __construct(
        private readonly DBALQueryBuilder $DBALQueryBuilder,
    ) {}

    public function forStatus(ProductSupplyStatus|ProductSupplyStatusInterface|string $status): self
    {
        $this->status = $status instanceof ProductSupplyStatus ? $status : new ProductSupplyStatus($status);
        return $this;
    }

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

    public function isExist(NewProductSupplyInvariableDTO $container): bool
    {
        $dbal = $this->DBALQueryBuilder->createQueryBuilder(self::class);

        $dbal->from(ProductSupply::class, 'product_supply');

        $dbal
            ->join(
                'product_supply',
                ProductSupplyEvent::class,
                'product_supply_event',
                '
                    product_supply_event.id = product_supply.event AND 
                    product_supply_event.status = :status'
            )->setParameter(
                key: 'status',
                value: $this->status,
                type: ProductSupplyStatus::TYPE,
            );

        /** Invariable */
        $dbal
            ->addSelect('product_supply_invariable.number AS supply_number')
            ->join(
                'product_supply_event',
                ProductSupplyInvariable::class,
                'product_supply_invariable',
                '
                    product_supply_invariable.event = product_supply_event.id AND
                    product_supply_invariable.number = :number'
            )->setParameter(
                key: 'number',
                value: $container->getNumber(),
                type: Types::STRING,
            );

        return $dbal->fetchExist();
    }
}