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

namespace BaksDev\Manufacture\Part\Repository\ManufacturePartActionEvent;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Manufacture\Part\Entity\Event\ManufacturePartEvent;
use BaksDev\Manufacture\Part\Entity\ManufacturePart;
use BaksDev\Manufacture\Part\Type\Id\ManufacturePartUid;
use BaksDev\Users\UsersTable\Type\Actions\Event\UsersTableActionsEventUid;

final class ManufacturePartActionEventRepository implements ManufacturePartActionEventInterface
{
    private DBALQueryBuilder $DBALQueryBuilder;

    public function __construct(DBALQueryBuilder $DBALQueryBuilder,)
    {
        $this->DBALQueryBuilder = $DBALQueryBuilder;
    }

    /**
     * Возвращает идентификатор события категории производства партии (настройки табелей сотрудников)
     */
    public function getManufacturePartActionEventUid(ManufacturePartUid $part): ?UsersTableActionsEventUid
    {
        $qb = $this->DBALQueryBuilder->createQueryBuilder(self::class);

        $qb->from(ManufacturePart::TABLE, 'part');
        $qb->where('part.id = :part');
        $qb->setParameter('part', $part, ManufacturePartUid::TYPE);


        $qb->addSelect('part_event.action');
        $qb->leftJoin(
            'part',
            ManufacturePartEvent::TABLE,
            'part_event',
            'part_event.main = part.id'
        );

        $action = $qb->enableCache('manufacture-part', 86400)->fetchOne();

        return $action ? new UsersTableActionsEventUid($action) : null;
    }
}