<?php
/*
 *  Copyright 2023.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Manufacture\Part\UseCase\Admin\Completed;

use BaksDev\Manufacture\Part\Entity\Working\ManufacturePartWorkingInterface;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\UsersTable\Type\Actions\Working\UsersTableActionsWorkingUid;
use ReflectionProperty;
use Symfony\Component\Validator\Constraints as Assert;

/** @see ManufacturePartWorking */
final class ManufacturePartWorkingDTO implements ManufacturePartWorkingInterface
{
    /**
     * Профиль сотрудника
     */
    #[Assert\IsNull]
    private readonly ?UserProfileUid $profile;

    /**
     * Состояние производственного процесса
     */
    #[Assert\IsNull]
    private readonly ?UsersTableActionsWorkingUid $working;


    /**
     * Profile
     */
    public function getProfile(): ?UserProfileUid
    {
        return null;
    }

    public function setProfile(?UserProfileUid $profile): self
    {
        if(!(new ReflectionProperty(self::class, 'profile'))->isInitialized($this))
        {
            $this->working = null;
        }

        return $this;
    }

    /**
     * Working
     */
    public function getWorking(): ?UsersTableActionsWorkingUid
    {
        return null;
    }

    public function setWorking(?UsersTableActionsWorkingUid $working): self
    {
        if(!(new ReflectionProperty(self::class, 'working'))->isInitialized($this))
        {
            $this->working = null;
        }

        return $this;
    }
}