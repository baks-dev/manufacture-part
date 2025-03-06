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

namespace BaksDev\Manufacture\Part\Entity\Products;

use BaksDev\Core\Entity\EntityEvent;
use BaksDev\Manufacture\Part\Entity\Event\ManufacturePartEvent;
use BaksDev\Manufacture\Part\Entity\Products\Orders\ManufacturePartProductOrder;
use BaksDev\Manufacture\Part\Type\Product\ManufacturePartProductUid;
use BaksDev\Orders\Order\Type\Id\OrderUid;
use BaksDev\Products\Product\Type\Event\ProductEventUid;
use BaksDev\Products\Product\Type\Offers\Id\ProductOfferUid;
use BaksDev\Products\Product\Type\Offers\Variation\Id\ProductVariationUid;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\Id\ProductModificationUid;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Symfony\Component\Validator\Constraints as Assert;

/* Перевод ManufacturePartProduct */

#[ORM\Entity]
#[ORM\Table(name: 'manufacture_part_product')]
#[ORM\Index(columns: ['event'])]
#[ORM\Index(columns: ['product', 'offer', 'variation', 'modification'])]
class ManufacturePartProduct extends EntityEvent
{
    /**
     * Идентификатор
     */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    #[ORM\Id]
    #[ORM\Column(type: ManufacturePartProductUid::TYPE)]
    private ManufacturePartProductUid $id;

    /** Связь на событие */
    #[Assert\NotBlank]
    #[ORM\ManyToOne(targetEntity: ManufacturePartEvent::class, inversedBy: "product")]
    #[ORM\JoinColumn(name: 'event', referencedColumnName: "id")]
    private ManufacturePartEvent $event;

    /** Порядок сортировки */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private int $sort;

    /**
     * Идентификатор События!!! продукта
     */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    #[ORM\Column(type: ProductEventUid::TYPE)]
    private ProductEventUid $product;

    /**
     * Идентификатор торгового предложения
     */
    #[Assert\Uuid]
    #[ORM\Column(type: ProductOfferUid::TYPE, nullable: true)]
    private ?ProductOfferUid $offer;

    /**
     * Идентификатор множественного варианта торгового предложения
     */
    #[Assert\Uuid]
    #[ORM\Column(type: ProductVariationUid::TYPE, nullable: true)]
    private ?ProductVariationUid $variation;

    /**
     * Идентификатор модификации множественного варианта торгового предложения
     */
    #[Assert\Uuid]
    #[ORM\Column(type: ProductModificationUid::TYPE, nullable: true)]
    private ?ProductModificationUid $modification;

    /**
     * Общее количество в партии
     */
    #[Assert\NotBlank]
    #[Assert\Range(min: 1)]
    #[ORM\Column(type: Types::INTEGER)]
    private int $total;

    /**
     * Коллекция заказов, закрепленных за продуктом
     */
    #[Assert\Valid]
    #[ORM\OneToMany(targetEntity: ManufacturePartProductOrder::class, mappedBy: 'product', cascade: ['all'])]
    private Collection $ord;


    public function __construct(ManufacturePartEvent $event)
    {
        $this->id = new ManufacturePartProductUid();
        $this->event = $event;
    }

    public function __clone()
    {
        $this->id = clone $this->id;
    }

    public function __toString(): string
    {
        return (string) $this->id;
    }

    /**
     * Id
     */
    public function getId(): ManufacturePartProductUid
    {
        return $this->id;
    }


    public function productDefect(int $defect)
    {
        $this->total -= $defect;
    }

    /**
     * Event
     */
    public function getEvent(): ManufacturePartEvent
    {
        return $this->event;
    }

    public function getDto($dto): mixed
    {
        $dto = is_string($dto) && class_exists($dto) ? new $dto() : $dto;

        if($dto instanceof ManufacturePartProductInterface)
        {
            return parent::getDto($dto);
        }

        throw new InvalidArgumentException(sprintf('Class %s interface error', $dto::class));
    }

    public function setEntity($dto): mixed
    {
        if($dto instanceof ManufacturePartProductInterface || $dto instanceof self)
        {
            return parent::setEntity($dto);
        }

        throw new InvalidArgumentException(sprintf('Class %s interface error', $dto::class));
    }
}