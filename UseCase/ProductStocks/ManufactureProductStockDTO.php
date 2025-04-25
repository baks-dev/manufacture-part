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

namespace BaksDev\Manufacture\Part\UseCase\ProductStocks;

use BaksDev\Manufacture\Part\UseCase\ProductStocks\Invariable\ManufactureProductStocksInvariableDTO;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEventInterface;
use BaksDev\Products\Stocks\Type\Event\ProductStockEventUid;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusIncoming;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;

/** @see ProductStockEvent */
final class ManufactureProductStockDTO implements ProductStockEventInterface
{
    /** Идентификатор */
    #[Assert\Uuid]
    #[Assert\IsNull]
    private ?ProductStockEventUid $id = null;

    /** Статус заявки - ПРИХОД */
    #[Assert\NotBlank]
    private readonly ProductStockStatus $status;


    /** Коллекция продукции  */
    #[Assert\Valid]
    private ArrayCollection $product;

    #[Assert\Valid]
    private ManufactureProductStocksInvariableDTO $invariable;

    public function __construct()
    {
        $this->status = new ProductStockStatus(ProductStockStatusIncoming::class);
        $this->product = new ArrayCollection();
        $this->invariable = new ManufactureProductStocksInvariableDTO();
    }

    public function getEvent(): ?ProductStockEventUid
    {
        return $this->id;
    }

    public function setId(ProductStockEventUid $id): void
    {
        $this->id = $id;
    }

    public function addProduct(Products\ProductStockDTO $product): self
    {
        $filter = $this->product->filter(function(Products\ProductStockDTO $element) use ($product) {
            return $element->getProduct()->equals($product->getProduct()) &&
                $element->getOfferConst()?->equals($product->getOfferConst()) &&
                $element->getVariationConst()?->equals($product->getVariationConst()) &&
                $element->getModificationConst()?->equals($product->getModificationConst());
        });

        if($filter->isEmpty())
        {
            $this->product->add($product);
        }

        return $this;
    }

    /** Коллекция продукции  */
    public function getProduct(): ArrayCollection
    {
        return $this->product;
    }

    public function setProduct(ArrayCollection $product): void
    {
        $this->product = $product;
    }

    public function removeProduct(Products\ProductStockDTO $product): void
    {
        $this->product->removeElement($product);
    }


    /** Статус заявки - ПРИХОД */
    public function getStatus(): ProductStockStatus
    {
        return $this->status;
    }

    /**
     * Invariable
     */
    public function getInvariable(): ManufactureProductStocksInvariableDTO
    {
        return $this->invariable;
    }

}
