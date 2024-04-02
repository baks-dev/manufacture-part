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

namespace BaksDev\Manufacture\Part\Repository\ActionTableByExistManufacturePart;

use BaksDev\Core\Doctrine\ORMQueryBuilder;
use BaksDev\Manufacture\Part\Entity\Event\ManufacturePartEvent;
use BaksDev\Manufacture\Part\Entity\Products\ManufacturePartProduct;
use BaksDev\Manufacture\Part\Entity\Working\ManufacturePartWorking;
use BaksDev\Manufacture\Part\Type\Event\ManufacturePartEventUid;
use BaksDev\Manufacture\Part\Type\Product\ManufacturePartProductUid;
use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatus;
use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatus\ManufacturePartStatusPackage;
use BaksDev\Users\UsersTable\Entity\Actions\Working\Trans\UsersTableActionsWorkingTrans;

final class ActionTableByExistManufacturePartRepository implements ActionTableByExistManufacturePartInterface
{
    private ORMQueryBuilder $ORMQueryBuilder;

    public function __construct(ORMQueryBuilder $ORMQueryBuilder)
    {
        $this->ORMQueryBuilder = $ORMQueryBuilder;
    }

    /**
     * Возвращает коллекцию выполненных этапов производства указанного продукта
     */
    public function getCollection(ManufacturePartProductUid $product): ?array
    {
        $qb = $this->ORMQueryBuilder->createQueryBuilder(self::class);

        $select = sprintf('new %s(working_event.id, working_trans.name)', ManufacturePartEventUid::class);
        $qb->select($select);
        $qb->from(ManufacturePartProduct::class, 'products');
        $qb->where('products.id = :products');
        $qb->setParameter('products', $product, ManufacturePartProductUid::TYPE);


        $qb->join(ManufacturePartEvent::class, 'event',
            'WITH', 'event.id = products.event'
        );

        $qb->join(ManufacturePartEvent::class, 'working_event',
            'WITH', 'working_event.main = event.main AND working_event.status = :status'
        );
        /** Только те, что учавствовали и в производстве */
        $qb->setParameter('status', new ManufacturePartStatus(ManufacturePartStatusPackage::class));


        $qb->join(ManufacturePartWorking::class, 'working',
            'WITH', 'working.event = working_event.id AND working.profile IS NOT NULL'
        );

        $qb->leftJoin(

            UsersTableActionsWorkingTrans::class, 'working_trans',
            'WITH', 'working_trans.working = working.working AND working_trans.local = :local'
        )
            ->bindLocal();


        $qb->groupBy('working.working');
        $qb->addGroupBy('working_event.id');
        $qb->addGroupBy('working_trans.name');

        return $qb->getResult() ?: [];
    }
}