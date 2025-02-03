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

use BaksDev\Contacts\Region\Type\Call\ContactsRegionCallUid;
use BaksDev\Products\Category\Type\Id\CategoryProductUid;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEventInterface;
use BaksDev\Products\Stocks\Type\Event\ProductStockEventUid;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusIncoming;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusPurchase;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;

/** @see MaterialStockEvent */
final class ManufactureProductStockDTO implements ProductStockEventInterface
{
    /** Идентификатор */
    #[Assert\Uuid]
    #[Assert\IsNull]
    private ?ProductStockEventUid $id = null;

    /** Ответственное лицо (Профиль пользователя) */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    private readonly UserProfileUid $profile;

    /** Статус заявки - ПРИХОД */
    #[Assert\NotBlank]
    private readonly ProductStockStatus $status;

    /** Номер заявки */
    #[Assert\NotBlank]
    #[Assert\Type('string')]
    #[Assert\Length(max: 36)]
    private string $number;

    /** Коллекция продукции  */
    #[Assert\Valid]
    private ArrayCollection $product;

    public function __construct()
    {
        $this->number = number_format(microtime(true) * 100, 0, '.', '.');
        $this->status = new ProductStockStatus(ProductStockStatusIncoming::class);
        $this->product = new ArrayCollection();
    }

    public function getEvent(): ?ProductStockEventUid
    {
        return $this->id;
    }

    public function setId(ProductStockEventUid $id): void
    {
        $this->id = $id;
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

    public function addProduct(Products\ProductStockDTO $product): self
    {
        $filter = $this->product->filter(function(Products\ProductStockDTO $element) use ($product) {
            return $element->getProduct()->equals($product->getProduct()) &&
                $element->getOffer()?->equals($product->getOffer()) &&
                $element->getVariation()?->equals($product->getVariation()) &&
                $element->getModification()?->equals($product->getModification());
        });

        if($filter->isEmpty())
        {
            $this->product->add($product);
        }

        return $this;
    }

    public function removeProduct(Products\ProductStockDTO $product): void
    {
        $this->product->removeElement($product);
    }


    /** Ответственное лицо (Профиль пользователя) */
    public function getProfile(): UserProfileUid
    {
        return $this->profile;
    }

    public function setProfile(UserProfileUid $profile): self
    {
        $this->profile = $profile;
        return $this;
    }


    /** Статус заявки - ПРИХОД */
    public function getStatus(): ProductStockStatus
    {
        return $this->status;
    }

    /** Номер заявки */

    public function getNumber(): string
    {


        return $this->number;
    }

}
