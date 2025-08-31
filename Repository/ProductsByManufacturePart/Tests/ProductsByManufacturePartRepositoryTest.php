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

namespace BaksDev\Manufacture\Part\Repository\ProductsByManufacturePart\Tests;

use BaksDev\Manufacture\Part\Repository\ProductsByManufacturePart\ProductsByManufacturePartInterface;
use BaksDev\Manufacture\Part\Repository\ProductsByManufacturePart\ProductsByManufacturePartResult;
use BaksDev\Manufacture\Part\Type\Product\ManufacturePartProductUid;
use BaksDev\Products\Product\Type\Event\ProductEventUid;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Id\ProductOfferUid;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Id\ProductVariationUid;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Attribute\When;

#[When(env: 'test')]
#[Group('manufacture-part')]
class ProductsByManufacturePartRepositoryTest extends KernelTestCase
{
    public function testUseCase(): void
    {
        /** @var ProductsByManufacturePartInterface $ProductsByManufacturePartRepository */
        $ProductsByManufacturePartRepository = self::getContainer()->get(ProductsByManufacturePartInterface::class);

        $ProductsByManufacturePart = $ProductsByManufacturePartRepository
            ->forPart('3d268ae0-3fdb-7183-8ba7-cddfce23ae9a')
            ->findAll();

        if(false === $ProductsByManufacturePart || false === $ProductsByManufacturePart->valid())
        {
            self::assertFalse(false);
            return;
        }

        /** @var ProductsByManufacturePartResult $ProductsByManufacturePartResult */
        foreach($ProductsByManufacturePart as $ProductsByManufacturePartResult)
        {
            self::assertInstanceOf(ManufacturePartProductUid::class, $ProductsByManufacturePartResult->getPartProductId());
            self::assertInstanceOf(ProductUid::class, $ProductsByManufacturePartResult->getProductId());
            self::assertInstanceOf(ProductEventUid::class, $ProductsByManufacturePartResult->getProductEvent());
            self::assertIsInt($ProductsByManufacturePartResult->getTotal());

            if($ProductsByManufacturePartResult->getProductOfferId())
            {
                self::assertInstanceOf(ProductOfferUid::class, $ProductsByManufacturePartResult->getProductOfferId());
                self::assertInstanceOf(ProductOfferConst::class, $ProductsByManufacturePartResult->getProductOfferConst());
                self::assertIsString($ProductsByManufacturePartResult->getProductOfferValue());

                $ProductsByManufacturePartResult->getProductOfferPostfix() ?
                    self::assertIsString($ProductsByManufacturePartResult->getProductOfferPostfix()) :
                    self::assertNull($ProductsByManufacturePartResult->getProductOfferPostfix());

                $ProductsByManufacturePartResult->getProductOfferReference() ?
                    self::assertIsString($ProductsByManufacturePartResult->getProductOfferReference()) :
                    self::assertNull($ProductsByManufacturePartResult->getProductOfferReference());

            }
            else
            {
                self::assertFalse($ProductsByManufacturePartResult->getProductOfferId());
                self::assertFalse($ProductsByManufacturePartResult->getProductOfferConst());
                self::assertNull($ProductsByManufacturePartResult->getProductOfferValue());
                self::assertNull($ProductsByManufacturePartResult->getProductOfferPostfix());
                self::assertNull($ProductsByManufacturePartResult->getProductOfferReference());
            }

            /**
             * Variation
             */

            if($ProductsByManufacturePartResult->getProductVariationId())
            {
                self::assertInstanceOf(ProductVariationUid::class, $ProductsByManufacturePartResult->getProductVariationId());
                self::assertInstanceOf(ProductVariationConst::class, $ProductsByManufacturePartResult->getProductVariationConst());
                self::assertIsString($ProductsByManufacturePartResult->getProductVariationValue());

                $ProductsByManufacturePartResult->getProductVariationPostfix() ?
                    self::assertIsString($ProductsByManufacturePartResult->getProductVariationPostfix()) :
                    self::assertNull($ProductsByManufacturePartResult->getProductVariationPostfix());

                $ProductsByManufacturePartResult->getProductVariationReference() ?
                    self::assertIsString($ProductsByManufacturePartResult->getProductVariationReference()) :
                    self::assertNull($ProductsByManufacturePartResult->getProductVariationReference());
            }
            else
            {
                self::assertFalse($ProductsByManufacturePartResult->getProductVariationId());
                self::assertFalse($ProductsByManufacturePartResult->getProductVariationConst());
                self::assertNull($ProductsByManufacturePartResult->getProductVariationValue());
                self::assertNull($ProductsByManufacturePartResult->getProductVariationPostfix());
                self::assertNull($ProductsByManufacturePartResult->getProductVariationReference());
            }

            /**
             * Modification
             */

            if($ProductsByManufacturePartResult->getProductModificationId())
            {
                self::assertInstanceOf(ProductOfferUid::class, $ProductsByManufacturePartResult->getProductModificationId());
                self::assertInstanceOf(ProductOfferConst::class, $ProductsByManufacturePartResult->getProductModificationConst());
                self::assertIsString($ProductsByManufacturePartResult->getProductModificationValue());

                $ProductsByManufacturePartResult->getProductModificationPostfix() ?
                    self::assertIsString($ProductsByManufacturePartResult->getProductModificationPostfix()) :
                    self::assertNull($ProductsByManufacturePartResult->getProductModificationPostfix());

                $ProductsByManufacturePartResult->getProductModificationReference() ?
                    self::assertIsString($ProductsByManufacturePartResult->getProductModificationReference()) :
                    self::assertNull($ProductsByManufacturePartResult->getProductModificationReference());
            }
            else
            {
                self::assertFalse($ProductsByManufacturePartResult->getProductModificationId());
                self::assertFalse($ProductsByManufacturePartResult->getProductModificationConst());
                self::assertNull($ProductsByManufacturePartResult->getProductModificationValue());
                self::assertNull($ProductsByManufacturePartResult->getProductModificationPostfix());
                self::assertNull($ProductsByManufacturePartResult->getProductModificationReference());
            }

            break;
        }
    }
}