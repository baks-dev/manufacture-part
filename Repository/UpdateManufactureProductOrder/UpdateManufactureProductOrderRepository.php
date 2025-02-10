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

namespace BaksDev\Manufacture\Part\Repository\UpdateManufactureProductOrder;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Manufacture\Part\Entity\Products\ManufacturePartProduct;
use BaksDev\Manufacture\Part\Type\Product\ManufacturePartProductUid;
use BaksDev\Orders\Order\Entity\Order;
use BaksDev\Orders\Order\Type\Id\OrderUid;
use InvalidArgumentException;


final class UpdateManufactureProductOrderRepository implements UpdateManufactureProductOrderInterface
{
    private ManufacturePartProductUid|false $id = false;

    private OrderUid|false $order = false;

    public function __construct(private readonly DBALQueryBuilder $DBALQueryBuilder) {}

    public function forPartProduct(ManufacturePartProductUid|string $id): self
    {
        if(empty($id))
        {
            throw new InvalidArgumentException('Invalid Argument ManufacturePartProduct');
        }

        if(is_string($id))
        {
            $id = new ManufacturePartProductUid($id);
        }

        $this->id = $id;

        return $this;
    }

    public function order(Order|OrderUid|string $order): self
    {
        if(empty($order))
        {
            throw new InvalidArgumentException('Invalid Argument Order');
        }

        if(is_string($order))
        {
            $order = new OrderUid($order);
        }
        if($order instanceof Order)
        {
            $order = $order->getId();
        }

        $this->order = $order;

        return $this;
    }

    /**
     * Метод присваивает идентификатор заказа продукту производства
     */
    public function update(): int
    {
        if(false === ($this->id instanceof ManufacturePartProductUid))
        {
            throw new InvalidArgumentException('Invalid Argument ManufacturePartProduct');
        }

        if(false === ($this->order instanceof OrderUid))
        {
            throw new InvalidArgumentException('Invalid Argument Order');
        }

        $dbal = $this->DBALQueryBuilder->createQueryBuilder(self::class);

        $dbal->update(ManufacturePartProduct::class);

        $dbal
            ->set('ord', ':ord')
            ->setParameter(
                key: 'ord',
                value: $this->order,
                type: OrderUid::TYPE
            );

        $dbal
            ->where('id = :product')
            ->setParameter(
                key: 'product',
                value: $this->id,
                type: ManufacturePartProductUid::TYPE
            );

        return (int) $dbal->executeStatement();
    }
}