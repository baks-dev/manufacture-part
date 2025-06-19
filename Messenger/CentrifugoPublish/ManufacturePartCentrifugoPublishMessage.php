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

namespace BaksDev\Manufacture\Part\Messenger\CentrifugoPublish;

use BaksDev\Products\Product\Type\Event\ProductEventUid;
use BaksDev\Products\Product\Type\Offers\Id\ProductOfferUid;
use BaksDev\Products\Product\Type\Offers\Variation\Id\ProductVariationUid;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\Id\ProductModificationUid;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;

final class ManufacturePartCentrifugoPublishMessage
{
    /**
     * Идентификатор
     */
    private string $identifier;

    /**
     * Идентификатор профиля
     */
    private string $profile;

    /*
     * Uuid's товара
     */
    private ?string $event;
    private ?string $offer;
    private ?string $variation;
    private ?string $modification;


    /**
     * Количество для суммы всех товаров
     */
    private ?int $total;

    /**
     * Идентификатор manufacturePartEvent
     */
    private string|null $manufacturePartEvent;

    public function __construct(
        string $identifier,
        string $profile,
        int|null $total = null,
        string|null $manufacturePartEvent = null,

        ?string $event = null,
        ?string $offer = null,
        ?string $variation = null,
        ?string $modification = null,
    )
    {
        $this->identifier = $identifier;
        $this->profile = $profile;
        $this->total = $total;
        $this->manufacturePartEvent = $manufacturePartEvent;

        $this->event = $event;
        $this->offer = $offer;
        $this->variation = $variation;
        $this->modification = $modification;
    }


    /**
     * Идентификатор
     */
    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * Профиль пользователя
     */
    public function getProfile(): UserProfileUid|false
    {
        return $this->profile ? new UserProfileUid($this->profile) : false;
    }

    /**
     * Количество для суммы всех товаров
     */
    public function getTotal(): int|false
    {
        return $this->total ?? false;
    }

    /**
     * Идентификатор События
     */
    public function getManufacturePartEvent(): string|false
    {
        return $this->manufacturePartEvent ?? false;
    }


    public function getEvent(): ProductEventUid|false
    {
        return $this->event ? new ProductEventUid($this->event) : false;
    }

    public function getOffer(): ProductOfferUid|false
    {
        return $this->offer ? new ProductOfferUid($this->offer) : false;
    }

    public function getVariation(): ProductVariationUid|false
    {
        return $this->variation ? new ProductVariationUid($this->variation) : false;
    }

    public function getModification(): ProductModificationUid|false
    {
        return $this->modification ? new ProductModificationUid($this->modification) : false;
    }

}
