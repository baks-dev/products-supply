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

namespace BaksDev\Products\Supply\UseCase\Admin\New\Personal;

use BaksDev\Products\Supply\Entity\Event\Personal\ProductSupplyPersonalInterface;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\User\Type\Id\UserUid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Объект с информацией о пользователе
 * @see ProductSupplyPersonal
 */
final class NewProductSupplyPersonalDTO implements ProductSupplyPersonalInterface
{
    /**
     * Пользователь
     */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    private UserUid $usr;

    /**
     * Профиль
     */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    private UserProfileUid $profile;

    /**
     * User
     */
    public function setUsr(UserUid $usr): NewProductSupplyPersonalDTO
    {
        $this->usr = $usr;
        return $this;
    }

    public function getUsr(): UserUid
    {
        return $this->usr;
    }

    /**
     * Profile
     */
    public function setProfile(UserProfileUid $profile): NewProductSupplyPersonalDTO
    {
        $this->profile = $profile;
        return $this;
    }

    public function getProfile(): UserProfileUid
    {
        return $this->profile;
    }
}