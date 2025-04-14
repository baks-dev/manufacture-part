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

namespace BaksDev\Manufacture\Part\Repository\ProductsByManufacturePart;

use BaksDev\Manufacture\Part\Type\Product\ManufacturePartProductUid;
use BaksDev\Products\Product\Type\Event\ProductEventUid;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Id\ProductOfferUid;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Id\ProductVariationUid;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\Id\ProductModificationUid;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final readonly class ProductsByManufacturePartResult
{
    public function __construct(
        private string $part_product_id,
        private string $product_id,
        private string $product_event,
        private string $product_name,
        private string $product_article,

        private string|null $product_offer_id,
        private string|null $product_offer_const,
        private string|null $product_offer_value,
        private string|null $product_offer_postfix,
        private string|null $product_offer_reference,

        private string|null $product_variation_id,
        private string|null $product_variation_const,
        private string|null $product_variation_value,
        private string|null $product_variation_postfix,
        private string|null $product_variation_reference,

        private string|null $product_modification_id,
        private string|null $product_modification_const,
        private string|null $product_modification_value,
        private string|null $product_modification_postfix,
        private string|null $product_modification_reference,

        private int $product_total
    ) {}

    /**
     * PartProductId
     */
    public function getPartProductId(): ManufacturePartProductUid
    {
        return new ManufacturePartProductUid($this->part_product_id);
    }

    /**
     * ProductId
     */
    public function getProductId(): ProductUid
    {
        return new ProductUid($this->product_id);
    }

    /**
     * ProductEvent
     */
    public function getProductEvent(): ProductEventUid
    {
        return new ProductEventUid($this->product_event);
    }

    /**
     * ProductName
     */
    public function getProductName(): string
    {
        return $this->product_name;
    }

    /**
     * ProductArticle
     */
    public function getArticle(): string
    {
        return $this->product_article;
    }

    /**
     * ProductOffer
     */

    public function getProductOfferId(): ProductOfferUid|false
    {
        return $this->product_offer_id ? new ProductOfferUid($this->product_offer_id) : false;
    }

    public function getProductOfferConst(): ProductOfferConst|false
    {
        return $this->product_offer_const ? new ProductOfferConst($this->product_offer_const) : false;
    }

    public function getProductOfferValue(): ?string
    {
        return $this->product_offer_value;
    }

    public function getProductOfferPostfix(): ?string
    {
        return $this->product_offer_postfix;
    }

    public function getProductOfferReference(): ?string
    {
        return $this->product_offer_reference;
    }

    /**
     * ProductVariation
     */

    public function getProductVariationId(): ProductVariationUid|false
    {
        return $this->product_variation_id ? new ProductVariationUid($this->product_variation_id) : false;
    }

    public function getProductVariationConst(): ProductVariationConst|false
    {
        return $this->product_variation_const ? new ProductVariationConst($this->product_variation_const) : false;
    }

    public function getProductVariationValue(): ?string
    {
        return $this->product_variation_value;
    }

    public function getProductVariationPostfix(): ?string
    {
        return $this->product_variation_postfix;
    }

    public function getProductVariationReference(): ?string
    {
        return $this->product_variation_reference;
    }

    /**
     * ProductModification
     */

    public function getProductModificationId(): ProductModificationUid|false
    {
        return $this->product_modification_id ? new ProductModificationUid($this->product_modification_id) : false;
    }

    public function getProductModificationConst(): ProductModificationConst|false
    {
        return $this->product_modification_const ? new ProductModificationConst($this->product_modification_const) : false;
    }

    public function getProductModificationValue(): ?string
    {
        return $this->product_modification_value;
    }

    public function getProductModificationPostfix(): ?string
    {
        return $this->product_modification_postfix;
    }

    public function getProductModificationReference(): ?string
    {
        return $this->product_modification_reference;
    }

    /**
     * ProductTotal
     */
    public function getTotal(): int
    {
        return $this->product_total;
    }
}