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

use BaksDev\Products\Supply\Type\Status\ProductSupplyStatus;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use DateTimeImmutable;

final readonly class ProductSupplyHistoryResult
{
    public function __construct(
        private string $status,
        private string $mod_date,
        private string $action,
        private string $supply_profile_id,
        private ?string $user_profile_id,
        private ?string $profile_username,
        private ?string $profile_avatar_name,
        private ?string $profile_avatar_ext,
        private ?string $profile_avatar_cdn,
    ) {}

    public function getStatus(): ProductSupplyStatus
    {
        return new ProductSupplyStatus($this->status);
    }

    public function getModDate(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->mod_date);
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getSupplyProfileId(): UserProfileUid
    {
        return new UserProfileUid($this->supply_profile_id);
    }

    public function getProfileId(): ?UserProfileUid
    {
        return $this->user_profile_id ? new UserProfileUid($this->user_profile_id) : null;
    }

    public function getProfileUsername(): ?string
    {
        return $this->profile_username;
    }

    public function getProfileAvatarName(): ?string
    {
        return $this->profile_avatar_name;
    }

    public function getProfileAvatarExt(): ?string
    {
        return $this->profile_avatar_ext;
    }

    public function getProfileAvatarCdn(): ?string
    {
        return $this->profile_avatar_cdn;
    }
}