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

namespace BaksDev\Manufacture\Part\UseCase\ProductStocks\Products;

use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Stocks\Entity\Stock\Products\ProductStockProductInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class ProductStockDTO implements ProductStockProductInterface
{


    /** Продукт */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    private ProductUid $product;

    /** Торговое предложение */
    #[Assert\Uuid]
    private ?ProductOfferConst $offer = null;

    /** Множественный вариант */
    #[Assert\Uuid]
    private ?ProductVariationConst $variation = null;

    /** Модификация множественного варианта */
    #[Assert\Uuid]
    private ?ProductModificationConst $modification = null;

    /** Количество */
    #[Assert\NotBlank]
    #[Assert\Range(min: 1)]
    private int $total;


    /** Продукт */
    public function getProduct(): ProductUid
    {
        return $this->product;
    }

    public function setProduct(ProductUid $product): self
    {
        $this->product = $product;

        return $this;
    }

    /** Торговое предложение */
    public function getOfferConst(): ?ProductOfferConst
    {
        return $this->offer;
    }

    public function setOfferConst(ProductOfferConst|null|false $offer): self
    {
        $this->offer = empty($offer) ? null : $offer;

        return $this;
    }

    /** Множественный вариант */
    public function getVariationConst(): ?ProductVariationConst
    {
        return $this->variation;
    }

    public function setVariationConst(ProductVariationConst|null|false $variation): self
    {
        $this->variation = empty($variation) ? null : $variation;

        return $this;
    }

    /** Модификация множественного варианта */
    public function getModificationConst(): ?ProductModificationConst
    {
        return $this->modification;
    }

    public function setModificationConst(ProductModificationConst|null|false $modification): self
    {
        $this->modification = empty($modification) ? null : $modification;

        return $this;
    }

    /** Количество */
    public function getTotal(): int
    {
        return $this->total;
    }

    public function setTotal(int $total): self
    {
        $this->total = $total;

        return $this;
    }
}
