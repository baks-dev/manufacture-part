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

namespace BaksDev\Manufacture\Part\UseCase\Admin\Defect\Event;

use BaksDev\Manufacture\Part\Entity\Products\ManufacturePartProductInterface;
use BaksDev\Products\Product\Type\Event\ProductEventUid;
use BaksDev\Products\Product\Type\Offers\Id\ProductOfferUid;
use BaksDev\Products\Product\Type\Offers\Variation\Id\ProductVariationUid;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\Id\ProductModificationUid;
use Doctrine\DBAL\Types\Types;
use ReflectionProperty;
use Symfony\Component\Validator\Constraints as Assert;

/** @see ManufacturePartEvent */
final class ManufacturePartProductsDTO implements ManufacturePartProductInterface
{

    /**
     * Идентификатор События!!! продукта
     */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    private readonly ProductEventUid $product;

    /**
     * Идентификатор торгового предложения
     */
    #[Assert\Uuid]
    private readonly ?ProductOfferUid $offer;

    /**
     * Идентификатор множественного варианта торгового предложения
     */
    #[Assert\Uuid]
    private readonly ?ProductVariationUid $variation;

    /**
     * Идентификатор модификации множественного варианта торгового предложения
     */
    #[Assert\Uuid]
    private readonly ?ProductModificationUid $modification;

    /**
     * Количество данного товара в заявке
     */
    #[Assert\NotBlank]
    #[Assert\Range(min: 0)]
    private int $total;

    /** Порядок сортировки */
    #[Assert\NotBlank]
    private readonly int $sort;

    /**
     * Идентификатор События!!! продукта
     */
    public function getProduct(): ?ProductEventUid
    {
        return $this->product;
    }


    /**
     * Идентификатор торгового предложения
     */

    public function getOffer(): ?ProductOfferUid
    {
        return $this->offer;
    }


    /**
     * Идентификатор множественного варианта торгового предложения
     */

    public function getVariation(): ?ProductVariationUid
    {
        return $this->variation;
    }


    /**
     * Идентификатор модификации множественного варианта торгового предложения
     */

    public function getModification(): ?ProductModificationUid
    {
        return $this->modification;
    }


    /**
     * Total
     */
    
    public function setDefect(int $defect): self
    {
        $this->total -= $defect;
        return $this;
    }


    public function getTotal(): int
    {
        return $this->total;
    }

    /**
     * Sort
     */
    public function getSort(): int
    {
        $this->sort ?: $this->sort = time();

        return $this->sort;
    }

    public function setSort(?int $sort): self
    {
        if($sort && false === (new ReflectionProperty(self::class, 'sort')->isInitialized($this)))
        {
            $this->sort = $sort;
        }

        return $this;
    }

}