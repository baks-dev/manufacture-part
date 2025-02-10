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

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Manufacture\Part\Entity\Event\ManufacturePartEvent;
use BaksDev\Manufacture\Part\Entity\ManufacturePart;
use BaksDev\Manufacture\Part\Entity\Products\ManufacturePartProduct;
use BaksDev\Manufacture\Part\Type\Id\ManufacturePartUid;
use BaksDev\Products\Category\Entity\Offers\CategoryProductOffers;
use BaksDev\Products\Category\Entity\Offers\Variation\CategoryProductVariation;
use BaksDev\Products\Category\Entity\Offers\Variation\Modification\CategoryProductModification;
use BaksDev\Products\Product\Entity\Event\ProductEvent;
use BaksDev\Products\Product\Entity\Info\ProductInfo;
use BaksDev\Products\Product\Entity\Offers\ProductOffer;
use BaksDev\Products\Product\Entity\Offers\Variation\Modification\ProductModification;
use BaksDev\Products\Product\Entity\Offers\Variation\ProductVariation;
use BaksDev\Products\Product\Entity\Trans\ProductTrans;
use InvalidArgumentException;

final class ProductsByManufacturePartRepository implements ProductsByManufacturePartInterface
{
    private ManufacturePartUid|false $part = false;

    private bool|null $order = null;

    public function __construct(private readonly DBALQueryBuilder $DBALQueryBuilder) {}

    public function forPart(ManufacturePart|ManufacturePartUid|string $part): self
    {
        if(is_string($part))
        {
            $part = new ManufacturePartUid($part);
        }

        if($part instanceof ManufacturePart)
        {
            $part = $part->getId();
        }

        $this->part = $part;

        return $this;
    }

    public function onlyProductOrder(): self
    {

        $this->order = true;
        return $this;
    }

    public function onlyEmptyOrder(): self
    {

        $this->order = false;
        return $this;
    }

    /**
     * Метод возвращает список продукции в производственной партии
     *
     * @return array{
     *     product_id: string,
     *     product_event: string,
     *     product_name: string,
     *     product_offer_id: string,
     *     product_offer_const: string,
     *     product_offer_value: string,
     *     product_offer_postfix: string,
     *     product_offer_reference: string,
     *     product_variation_id: string,
     *     product_variation_const: string,
     *     product_variation_value: string,
     *     product_variation_postfix: string,
     *     product_variation_reference: string,
     *      product_modification_id: string,
     *      product_modification_const: string,
     *      product_modification_value: string,
     *      product_modification_postfix: string,
     *      product_modification_reference: string,
     *     product_total: int
     * }
     */
    public function findAll(): ?array
    {
        if(false === $this->part)
        {
            throw new InvalidArgumentException('Invalid Argument Part');
        }

        $dbal = $this->DBALQueryBuilder
            ->createQueryBuilder(self::class)
            ->bindLocal();

        //        $dbal->select('part.id');
        //        $dbal->addSelect('part.event');

        $dbal
            ->from(ManufacturePart::class, 'part')
            ->where('part.id = :part')
            ->setParameter('part', $this->part, ManufacturePartUid::TYPE);

        $dbal->leftJoin(
            'part',
            ManufacturePartEvent::class,
            'part_event',
            'part_event.id = part.event'
        );

        $dbal
            ->addSelect('part_product.id AS part_product_id')
            ->addSelect('part_product.total AS product_total')
            ->leftJoin(
                'part',
                ManufacturePartProduct::class,
                'part_product',
                'part_product.event = part.event'
            );


        $dbal
            ->addSelect('product_event.id AS product_event')
            ->addSelect('product_event.main AS product_id')
            ->join(
                'part_product',
                ProductEvent::class,
                'product_event',
                'product_event.id = part_product.product'
            );

        $dbal->leftJoin(
            'product_event',
            ProductInfo::class,
            'product_info',
            'product_info.product = product_event.main'
        );

        $dbal
            ->addSelect('product_trans.name AS product_name')
            ->leftJoin(
                'product_event',
                ProductTrans::class,
                'product_trans',
                'product_trans.event = product_event.id AND product_trans.local = :local'
            );

        /**
         * Торговое предложение
         */

        $dbal
            ->addSelect('product_offer.id as product_offer_id')
            ->addSelect('product_offer.const as product_offer_const')
            ->addSelect('product_offer.value as product_offer_value')
            ->addSelect('product_offer.postfix as product_offer_postfix')
            ->leftJoin(
                'product_event',
                ProductOffer::class,
                'product_offer',
                'product_offer.id = part_product.offer OR product_offer.id IS NULL'
            );

        /* Тип торгового предложения */
        $dbal
            ->addSelect('category_offer.reference as product_offer_reference')
            ->leftJoin(
                'product_offer',
                CategoryProductOffers::class,
                'category_offer',
                'category_offer.id = product_offer.category_offer'
            );


        /**
         * Множественные варианты торгового предложения
         */

        $dbal
            ->addSelect('product_variation.id as product_variation_id')
            ->addSelect('product_variation.const as product_variation_const')
            ->addSelect('product_variation.value as product_variation_value')
            ->addSelect('product_variation.postfix as product_variation_postfix')
            ->leftJoin(
                'product_offer',
                ProductVariation::class,
                'product_variation',
                'product_variation.id = part_product.variation OR product_variation.id IS NULL'
            );

        /* Тип множественного варианта торгового предложения */
        $dbal
            ->addSelect('category_variation.reference as product_variation_reference')
            ->leftJoin(
                'product_variation',
                CategoryProductVariation::class,
                'category_variation',
                'category_variation.id = product_variation.category_variation'
            );


        /**
         * Модификация множественного варианта
         */

        $dbal
            ->addSelect('product_modification.id as product_modification_id')
            ->addSelect('product_modification.const as product_modification_const')
            ->addSelect('product_modification.value as product_modification_value')
            ->addSelect('product_modification.postfix as product_modification_postfix')
            ->leftJoin(
                'part_product',
                ProductModification::class,
                'product_modification',
                'product_modification.id = part_product.modification OR product_modification.id IS NULL'
            );

        /** Получаем тип модификации множественного варианта */
        $dbal
            ->addSelect('category_modification.reference as product_modification_reference')
            ->leftJoin(
                'product_modification',
                CategoryProductModification::class,
                'category_modification',
                'category_modification.id = product_modification.category_modification'
            );


        /** Артикул продукта */
        $dbal->addSelect('
            COALESCE(
                product_modification.article, 
                product_variation.article, 
                product_offer.article, 
                product_info.article
            ) AS product_article
		');


        $dbal->orderBy('part_product.id', 'DESC');

        return $dbal
            ->enableCache('manufacture-part', 3600)
            ->fetchAllAssociative();
    }

}
