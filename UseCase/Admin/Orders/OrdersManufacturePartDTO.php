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

namespace BaksDev\Manufacture\Part\UseCase\Admin\Orders;

use BaksDev\Manufacture\Part\Entity\Event\ManufacturePartEventInterface;
use BaksDev\Manufacture\Part\Type\Event\ManufacturePartEventUid;
use BaksDev\Manufacture\Part\UseCase\Admin\Orders\Invariable\ManufacturePartInvariableDTO;
use BaksDev\Manufacture\Part\UseCase\Admin\Orders\Products\ManufacturePartProductsDTO;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;

/** @see ManufacturePartEvent */
final class OrdersManufacturePartDTO implements ManufacturePartEventInterface
{
    /**
     * Идентификатор события
     */
    #[Assert\Uuid]
    private ?ManufacturePartEventUid $id = null;

    private ArrayCollection $product;

    public function __construct()
    {
        $this->product = new ArrayCollection();
    }

    /**
     * Идентификатор события
     */
    public function getEvent(): ?ManufacturePartEventUid
    {
        return $this->id;
    }

    public function setId(?ManufacturePartEventUid $id): self
    {
        $this->id = $id;
        return $this;
    }

    /**
     * Product
     */
    public function getProduct(): ArrayCollection
    {
        return $this->product;
    }

    public function addProduct(ManufacturePartProductsDTO $product): self
    {
        $this->product->add($product);

        return $this;
    }
}