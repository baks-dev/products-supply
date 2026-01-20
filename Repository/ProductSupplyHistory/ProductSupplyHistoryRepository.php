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

namespace BaksDev\Products\Supply\Repository\ProductSupplyHistory;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Products\Supply\Entity\Event\Modify\ProductSupplyModify;
use BaksDev\Products\Supply\Entity\Event\Personal\ProductSupplyPersonal;
use BaksDev\Products\Supply\Entity\Event\ProductSupplyEvent;
use BaksDev\Products\Supply\Entity\ProductSupply;
use BaksDev\Products\Supply\Type\ProductSupplyUid;
use BaksDev\Users\Profile\UserProfile\Entity\Event\Avatar\UserProfileAvatar;
use BaksDev\Users\Profile\UserProfile\Entity\Event\Info\UserProfileInfo;
use BaksDev\Users\Profile\UserProfile\Entity\Event\Personal\UserProfilePersonal;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use Generator;

final class ProductSupplyHistoryRepository implements ProductSupplyHistoryInterface
{
    private ProductSupplyUid|false $supply = false;

    public function __construct(
        private readonly DBALQueryBuilder $DBALQueryBuilder
    ) {}

    /** Поставка */
    public function supply(ProductSupply|ProductSupplyUid $supply): self
    {
        if($supply instanceof ProductSupply)
        {
            $supply = $supply->getId();
        }

        $this->supply = $supply;

        return $this;
    }

    /** История изменений */
    public function findAll(): Generator
    {

        $dbal = $this->DBALQueryBuilder->createQueryBuilder(self::class);

        $dbal
            ->addSelect('event.status')
            ->from(ProductSupplyEvent::class, 'event');

        if($this->supply instanceof ProductSupplyUid)
        {
            $dbal
                ->where('event.main = :supply')
                ->setParameter('supply', $this->supply, ProductSupplyUid::TYPE);
        }

        $dbal
            ->addSelect('modify.mod_date')
            ->addSelect('modify.action')
            ->join(
                'event',
                ProductSupplyModify::class,
                'modify',
                'modify.event = event.id'
            );

        $dbal
            ->addSelect('personal.profile AS supply_profile_id')
            ->join(
                'event',
                ProductSupplyPersonal::class,
                'personal',
                'personal.event = event.id'
            );

        $dbal->join(
            'modify',
            UserProfileInfo::class,
            'profile_info',
            'profile_info.usr = modify.usr AND profile_info.active = true'
        );

        $dbal
            ->addSelect('profile.id AS user_profile_id')
            ->leftJoin(
                'profile_info',
                UserProfile::class,
                'profile',
                'profile.id = profile_info.profile'
            );

        $dbal
            ->addSelect('profile_personal.username AS profile_username')
            ->leftJoin(
                'profile',
                UserProfilePersonal::class,
                'profile_personal',
                'profile_personal.event = profile.event'
            );

        $dbal
            ->addSelect('profile_avatar.name AS profile_avatar_name')
            ->addSelect('profile_avatar.ext AS profile_avatar_ext')
            ->addSelect('profile_avatar.cdn AS profile_avatar_cdn')
            ->leftJoin(
                'profile',
                UserProfileAvatar::class,
                'profile_avatar',
                'profile_avatar.event = profile.event'
            );

        $dbal->orderBy('modify.mod_date');

        return $dbal->fetchAllHydrate(ProductSupplyHistoryResult::class);
    }
}