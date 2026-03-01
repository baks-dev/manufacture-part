<?php
/*
 *  Copyright 2026.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Manufacture\Part\Repository\ProductsByManufacturePart;

use BaksDev\Manufacture\Part\Entity\ManufacturePart;
use BaksDev\Manufacture\Part\Type\Id\ManufacturePartUid;
use Generator;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(public: true)]
interface ProductsByManufacturePartInterface
{
    public function forPart(ManufacturePart|ManufacturePartUid|string $part): self;

    public function onlyProductOrder(): self;

    public function onlyEmptyOrder(): self;

    /**
     * Метод возвращает список продукции в производственной партии
     *
     * @twig:
     *
     * <code>
     *     {# @var ProductsByManufacturePartResult \BaksDev\Manufacture\Part\Repository\ProductsByManufacturePart\ProductsByManufacturePartResult #}
     *     {{ ProductsByManufacturePartResult.getTotal }}
     * </code>
     */
    public function findAll(): Generator|false;

    /**
     * Метод возвращает список продукции в производственной партии
     *
     * @return array{
     *      product_id: string,
     *      product_event: string,
     *      product_name: string,
     *      product_offer_id: string,
     *      product_offer_value: string,
     *      product_offer_postfix: string,
     *      product_offer_reference: string,
     *      product_variation_id: string,
     *      product_variation_value: string,
     *      product_variation_postfix: string,
     *      product_variation_reference: string,
     *       product_modification_id: string,
     *       product_modification_value: string,
     *       product_modification_postfix: string,
     *       product_modification_reference: string,
     *      product_total: int
     *  }
     *
     */
    public function findAllAssociative(): ?array;
}