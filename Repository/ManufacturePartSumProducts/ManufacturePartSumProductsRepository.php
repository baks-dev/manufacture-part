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

namespace BaksDev\Manufacture\Part\Repository\ManufacturePartSumProducts;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Manufacture\Part\Entity\Products\ManufacturePartProduct;
use BaksDev\Manufacture\Part\Type\Event\ManufacturePartEventUid;

final readonly class ManufacturePartSumProductsRepository implements ManufacturePartSumProductsInterface
{
    public function __construct(private DBALQueryBuilder $DBALQueryBuilder) {}

    public function sumManufacturePartProductsByEvent(ManufacturePartEventUid $event): int
    {
        $qb = $this->DBALQueryBuilder->createQueryBuilder(self::class);

        $qb
            ->select('SUM(products.total)')
            ->from(ManufacturePartProduct::class, 'products')
            ->where('products.event = :event')
            ->setParameter(
                key: 'event',
                value: $event,
                type: ManufacturePartEventUid::TYPE
            );

        return $qb->fetchOne() ?: 0;
    }
}