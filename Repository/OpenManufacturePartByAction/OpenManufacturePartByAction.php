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

namespace BaksDev\Manufacture\Part\Repository\OpenManufacturePartByAction;

use BaksDev\Core\Doctrine\ORMQueryBuilder;
use BaksDev\Manufacture\Part\Entity\Event\ManufacturePartEvent;
use BaksDev\Manufacture\Part\Entity\ManufacturePart;
use BaksDev\Manufacture\Part\Type\Marketplace\ManufacturePartMarketplace;
use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatus\ManufacturePartStatusOpen;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;

final class OpenManufacturePartByAction implements OpenManufacturePartByActionInterface
{
    private ORMQueryBuilder $ORMQueryBuilder;

    public function __construct(ORMQueryBuilder $ORMQueryBuilder)
    {
        $this->ORMQueryBuilder = $ORMQueryBuilder;
    }

    /**
     * Возвращает событие (ManufacturePartEvent) открытой активной производственной партии ответственного лица
     */
    public function findManufacturePartEventOrNull(
        UserProfileUid $profile,
    ): ?ManufacturePartEvent
    {
        $qb = $this->ORMQueryBuilder->createQueryBuilder(self::class);

        $qb->select('event');
        $qb->from(ManufacturePartEvent::class, 'event');

        $qb
            ->andWhere('event.profile = :profile')
            ->setParameter('profile', $profile, UserProfileUid::TYPE);

        $qb
            ->andWhere('event.status = :status')
            ->setParameter('status', ManufacturePartStatusOpen::STATUS);


        $qb->join(
            ManufacturePart::class,
            'part',
            'WITH',
            'part.event = event.id'
        );

        

        return $qb->getOneOrNullResult();
    }
}