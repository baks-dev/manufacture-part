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

namespace BaksDev\Manufacture\Part\Repository\OpenManufacturePart\Tests;

use BaksDev\Manufacture\Part\Repository\OpenManufacturePart\OpenManufacturePartInterface;
use BaksDev\Manufacture\Part\Repository\OpenManufacturePart\OpenManufacturePartResult;
use BaksDev\Manufacture\Part\Type\Id\ManufacturePartUid;
use BaksDev\Products\Product\Type\Event\ProductEventUid;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Id\ProductOfferUid;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Id\ProductVariationUid;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\Id\ProductModificationUid;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Attribute\When;

#[When(env: 'test')]
#[Group('manufacture-part')]
class OpenManufacturePartRepositoryTest extends KernelTestCase
{

    public function testUseCase(): void
    {

        /** @var OpenManufacturePartInterface $OpenManufacturePartRepository */
        $OpenManufacturePartRepository = self::getContainer()->get(OpenManufacturePartInterface::class);

        /** @var OpenManufacturePartResult $OpenManufacturePartResult */
        $OpenManufacturePartResult = $OpenManufacturePartRepository
            ->forFixed('019577a9-71a3-714b-a99c-0386833d802f')
            ->find();


        if(false === ($OpenManufacturePartResult instanceof OpenManufacturePartResult))
        {
            self::assertFalse(false);
            return;
        }

        self::assertInstanceOf(ManufacturePartUid::class, $OpenManufacturePartResult->getManufacturePartId());

        if($OpenManufacturePartResult->getProductId())
        {
            self::assertInstanceOf(ProductUid::class, $OpenManufacturePartResult->getProductId());
            self::assertInstanceOf(ProductEventUid::class, $OpenManufacturePartResult->getProductEvent());
        }
        else
        {
            self::assertNull($OpenManufacturePartResult->getProductId());
            self::assertNull($OpenManufacturePartResult->getProductEvent());
        }

        self::assertIsInt($OpenManufacturePartResult->getQuantity());

        if($OpenManufacturePartResult->getProductOfferId())
        {
            self::assertInstanceOf(ProductOfferUid::class, $OpenManufacturePartResult->getProductOfferId());
            self::assertInstanceOf(ProductOfferConst::class, $OpenManufacturePartResult->getProductOfferConst());
            self::assertIsString($OpenManufacturePartResult->getProductOfferValue());

            $OpenManufacturePartResult->getProductOfferPostfix() ?
                self::assertIsString($OpenManufacturePartResult->getProductOfferPostfix()) :
                self::assertNull($OpenManufacturePartResult->getProductOfferPostfix());

            $OpenManufacturePartResult->getProductOfferReference() ?
                self::assertIsString($OpenManufacturePartResult->getProductOfferReference()) :
                self::assertNull($OpenManufacturePartResult->getProductOfferReference());

        }
        else
        {
            self::assertFalse($OpenManufacturePartResult->getProductOfferId());
            self::assertFalse($OpenManufacturePartResult->getProductOfferConst());
            self::assertNull($OpenManufacturePartResult->getProductOfferValue());
            self::assertNull($OpenManufacturePartResult->getProductOfferPostfix());
            self::assertNull($OpenManufacturePartResult->getProductOfferReference());
        }

        /**
         * Variation
         */

        if($OpenManufacturePartResult->getProductVariationId())
        {
            self::assertInstanceOf(ProductVariationUid::class, $OpenManufacturePartResult->getProductVariationId());
            self::assertInstanceOf(ProductVariationConst::class, $OpenManufacturePartResult->getProductVariationConst());
            self::assertIsString($OpenManufacturePartResult->getProductVariationValue());

            $OpenManufacturePartResult->getProductVariationPostfix() ?
                self::assertIsString($OpenManufacturePartResult->getProductVariationPostfix()) :
                self::assertNull($OpenManufacturePartResult->getProductVariationPostfix());

            $OpenManufacturePartResult->getProductVariationReference() ?
                self::assertIsString($OpenManufacturePartResult->getProductVariationReference()) :
                self::assertNull($OpenManufacturePartResult->getProductVariationReference());
        }
        else
        {
            self::assertFalse($OpenManufacturePartResult->getProductVariationId());
            self::assertFalse($OpenManufacturePartResult->getProductVariationConst());
            self::assertNull($OpenManufacturePartResult->getProductVariationValue());
            self::assertNull($OpenManufacturePartResult->getProductVariationPostfix());
            self::assertNull($OpenManufacturePartResult->getProductVariationReference());
        }

        /**
         * Modification
         */

        if($OpenManufacturePartResult->getProductModificationId())
        {
            self::assertInstanceOf(ProductModificationUid::class, $OpenManufacturePartResult->getProductModificationId());
            self::assertInstanceOf(ProductModificationConst::class, $OpenManufacturePartResult->getProductModificationConst());
            self::assertIsString($OpenManufacturePartResult->getProductModificationValue());

            $OpenManufacturePartResult->getProductModificationPostfix() ?
                self::assertIsString($OpenManufacturePartResult->getProductModificationPostfix()) :
                self::assertNull($OpenManufacturePartResult->getProductModificationPostfix());

            $OpenManufacturePartResult->getProductModificationReference() ?
                self::assertIsString($OpenManufacturePartResult->getProductModificationReference()) :
                self::assertNull($OpenManufacturePartResult->getProductModificationReference());
        }
        else
        {
            self::assertFalse($OpenManufacturePartResult->getProductModificationId());
            self::assertFalse($OpenManufacturePartResult->getProductModificationConst());
            self::assertNull($OpenManufacturePartResult->getProductModificationValue());
            self::assertNull($OpenManufacturePartResult->getProductModificationPostfix());
            self::assertNull($OpenManufacturePartResult->getProductModificationReference());
        }


        /**
         * ProductImage
         */


        $OpenManufacturePartResult->getProductImage() ?
            self::assertIsString($OpenManufacturePartResult->getProductImage()) :
            self::assertNull($OpenManufacturePartResult->getProductImage());

        $OpenManufacturePartResult->getProductImageExt() ?
            self::assertIsString($OpenManufacturePartResult->getProductImageExt()) :
            self::assertNull($OpenManufacturePartResult->getProductImageExt());

        self::assertIsBool($OpenManufacturePartResult->getProductImageCdn());

    }
}