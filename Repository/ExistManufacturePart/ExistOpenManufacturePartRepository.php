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
 */

declare(strict_types=1);

namespace BaksDev\Manufacture\Part\Repository\ExistManufacturePart;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Manufacture\Part\Entity\Event\ManufacturePartEvent;
use BaksDev\Manufacture\Part\Entity\ManufacturePart;
use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatus;
use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatus\ManufacturePartStatusOpen;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use InvalidArgumentException;


final class ExistOpenManufacturePartRepository implements ExistOpenManufacturePartInterface
{
    private UserProfileUid|false $profile = false;

    public function __construct(private readonly DBALQueryBuilder $DBALQueryBuilder) {}

    public function forProfile(UserProfile|UserProfileUid|string $profile): self
    {

        if(empty($profile))
        {
            throw new InvalidArgumentException('Invalid Argument UserProfile');
        }

        if(is_string($profile))
        {
            $profile = new UserProfileUid($profile);
        }

        if($profile instanceof UserProfile)
        {
            $profile = $profile->getId();
        }


        $this->profile = $profile;

        return $this;
    }

    /**
     * Метод проверяет, имеется ли открытая поставка у профиля
     */
    public function isExistByProfile(): bool
    {
        if(false === ($this->profile instanceof UserProfileUid))
        {
            throw new InvalidArgumentException('Invalid Argument UserProfile');
        }

        $qb = $this->DBALQueryBuilder->createQueryBuilder(self::class);

        $qb->from(ManufacturePart::class, 'part');

        $qb->join(
            'part',
            ManufacturePartEvent::class,
            'part_event',
            '
                part_event.id = part.event AND 
                part_event.fixed = :fixed AND
                part_event.status = :status
            '
        )
            ->setParameter(
                key: 'status',
                value: ManufacturePartStatusOpen::class,
                type: ManufacturePartStatus::TYPE
            )
            ->setParameter(
                key: 'fixed',
                value: $this->profile,
                type: UserProfileUid::TYPE
            );

        return $qb->fetchExist();
    }

}