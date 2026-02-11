<?php
/*
 * Copyright 2026.  Baks.dev <admin@baks.dev>
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
 */

declare(strict_types=1);

namespace BaksDev\Materials\Sign\Messenger\MaterialSignReissue;

use BaksDev\Orders\Order\Type\Event\OrderEventUid;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\User\Type\Id\UserUid;

final readonly class MaterialsSignsReissueMessage
{
    private string $orderEvent;

    private string $user;

    private string $profile;

    public function __construct(OrderEventUid $orderEvent, ?UserUid $user, UserProfileUid $profile)
    {
        $this->orderEvent = (string) $orderEvent;
        $this->user = (string) $user;
        $this->profile = (string) $profile;
    }

    public function getOrderEvent(): OrderEventUid
    {
        return new OrderEventUid($this->orderEvent);
    }

    public function getUser(): ?UserUid
    {
        return false === empty($this->user) ? new UserUid($this->user) : null;
    }

    public function getProfile(): UserProfileUid
    {
        return new UserProfileUid($this->profile);
    }
}