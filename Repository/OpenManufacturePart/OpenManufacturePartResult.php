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

namespace BaksDev\Manufacture\Part\Repository\OpenManufacturePart;

use BaksDev\Delivery\Type\Id\DeliveryUid;
use BaksDev\Manufacture\Part\Type\Id\ManufacturePartUid;
use BaksDev\Products\Category\Type\Id\CategoryProductUid;
use BaksDev\Products\Product\Type\Event\ProductEventUid;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Id\ProductOfferUid;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Id\ProductVariationUid;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\Id\ProductModificationUid;
use BaksDev\Users\UsersTable\Entity\Actions\UsersTableActions;
use BaksDev\Users\UsersTable\Type\Actions\Event\UsersTableActionsEventUid;
use BaksDev\Users\UsersTable\Type\Actions\Id\UsersTableActionsUid;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final readonly class OpenManufacturePartResult
{
    public function __construct(

        private string $number, // " => "174.413.541.145"
        private int $quantity, // " => 0
        private string $id, // " => "01961692-572d-77c0-8022-4eea3503f56b"
        private string $event, // " => "01961692-572e-7548-a9e2-78b3ffd69fa0"

        private ?string $complete_id, // " => "stocks"
        private ?string $complete_name,

        /** Ответственный */
        private string $users_profile_username, // " => "admin"

        private string $actions_id, // " => "01961691-d9a3-777e-8cc5-18ab44580a71"
        private string $actions_name, // " => "Производство футболок"
        private string $actions_main,
        private ?string $category_id, // " => "01876af0-ddfc-70c3-ab25-5f85f55a9907"
        private ?string $category_name, // " => "Triangle"

        private ?string $product_id, // " => null
        private ?string $product_event, // " => null
        private ?string $product_name, // " => null
        private ?int $product_total, // " => null

        private ?string $product_offer_uid, // " => null
        private ?string $product_offer_const, // " => null
        private ?string $product_offer_value, // " => null
        private ?string $product_offer_postfix, // " => null
        private ?string $product_offer_reference, // " => null
        private ?string $product_offer_name, // " => null
        private ?string $product_offer_name_postfix, // " => null

        private ?string $product_variation_uid, // " => null
        private ?string $product_variation_const, // " => null
        private ?string $product_variation_value, // " => null
        private ?string $product_variation_postfix, // " => null
        private ?string $product_variation_reference, // " => null
        private ?string $product_variation_name, // " => null
        private ?string $product_variation_name_postfix, // " => null

        private ?string $product_modification_uid, // " => null
        private ?string $product_modification_const, // " => null
        private ?string $product_modification_value, // " => null
        private ?string $product_modification_postfix, // " => null
        private ?string $product_modification_reference, // " => null
        private ?string $product_modification_name, // " => null
        private ?string $product_modification_name_postfix, // " => null

        private ?string $product_image, // " => null
        private ?string $product_image_ext, // " => null
        private ?bool $product_image_cdn, // " => null

        private ?bool $actions_offer_offer,
        private ?bool $actions_offer_variation,
        private ?bool $actions_offer_modification,
    ) {}

    /**
     * Number
     */
    public function getNumber(): string
    {
        return $this->number;
    }

    /**
     * Quantity
     */
    public function getQuantity(): int
    {
        return $this->quantity ?: 0;
    }


    /**
     * Id
     */
    public function getManufacturePartId(): ManufacturePartUid
    {
        return new ManufacturePartUid($this->id);
    }

    /**
     * Event
     */
    public function getManufacturePartEvent(): string
    {
        return $this->event;
    }

    /**
     * Complete
     */

    public function getComplete(): DeliveryUid|false
    {
        return $this->complete_id && $this->complete_id !== 'stocks' ? new DeliveryUid($this->complete_id) : false;
    }

    public function getCompleteName(): ?string
    {
        return $this->complete_name;
    }


    /**
     * UsersProfileUsername
     */
    public function getUsersProfileUsername(): string
    {
        return $this->users_profile_username;
    }

    /**
     * getActionsId
     * @see UsersTableActionsEvent
     */
    public function getActionsId(): UsersTableActionsEventUid
    {
        return new UsersTableActionsEventUid($this->actions_id);
    }

    public function getActionsMain(): UsersTableActionsUid
    {
        return new UsersTableActionsUid($this->actions_main);
    }

    /**
     * ActionsName
     */
    public function getActionsName(): string
    {
        return $this->actions_name;
    }

    /**
     * CategoryId
     * @see UsersTableActionsEvent
     */
    public function getCategoryId(): ?CategoryProductUid
    {
//        return new CategoryProductUid($this->category_id);
        return $this->category_id ? new CategoryProductUid($this->category_id) : null;
    }

    /**
     * CategoryName
     */
    public function getCategoryName(): ?string
    {
        return $this->category_name;
    }


    /**
     * Product
     */

    public function getProductName(): ?string
    {
        return $this->product_name;
    }

    public function getProductTotal(): ?int
    {
        return $this->product_total;
    }

    public function getProductId(): ?ProductUid
    {
        return $this->product_id ? new ProductUid($this->product_id) : null;
    }

    public function getProductEvent(): ?ProductEventUid
    {
        return $this->product_event ? new ProductEventUid($this->product_event) : null;
    }


    /**
     * ProductOffer
     */

    public function getProductOfferId(): ProductOfferUid|false
    {
        return $this->product_offer_uid ? new ProductOfferUid($this->product_offer_uid) : false;
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

    public function getProductOfferName(): ?string
    {
        return $this->product_offer_name;
    }

    /**
     * ProductOfferNamePostfix
     */
    public function getProductOfferNamePostfix(): ?string
    {
        return $this->product_offer_name_postfix;
    }


    /**
     * ProductVariation
     */

    public function getProductVariationId(): ProductVariationUid|false
    {
        return $this->product_variation_uid ? new ProductVariationUid($this->product_variation_uid) : false;
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

    public function getProductVariationName(): ?string
    {
        return $this->product_variation_name;
    }

    public function getProductVariationNamePostfix(): ?string
    {
        return $this->product_variation_name_postfix;
    }


    /**
     * ProductModification
     */

    public function getProductModificationId(): ProductModificationUid|false
    {
        return $this->product_modification_uid ? new ProductModificationUid($this->product_modification_uid) : false;
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

    public function getProductModificationName(): ?string
    {
        return $this->product_modification_name;
    }

    public function getProductModificationNamePostfix(): ?string
    {
        return $this->product_modification_name_postfix;
    }


    /**
     * ProductImage
     */
    public function getProductImage(): ?string
    {
        return $this->product_image;
    }

    public function getProductImageExt(): ?string
    {
        return $this->product_image_ext;
    }

    public function getProductImageCdn(): bool
    {
        return $this->product_image_cdn === true;
    }

    /**
     * Action offer
     */
    public function getActionsOfferOffer(): bool
    {
        return $this->actions_offer_offer === true;
    }

    public function getActionsOfferVariation(): bool
    {
        return $this->actions_offer_variation === true;
    }

    public function getActionsOfferModification(): bool
    {
        return $this->actions_offer_modification === true;
    }

}