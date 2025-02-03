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

namespace BaksDev\Manufacture\Part\UseCase\ProductStocks\Tests;

use BaksDev\Manufacture\Part\UseCase\ProductStocks\ManufactureProductStockHandler;
use BaksDev\Manufacture\Part\UseCase\ProductStocks\Products\ProductStockDTO;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Stock\ProductStock;
use BaksDev\Products\Stocks\Entity\Total\ProductStockTotal;
use BaksDev\Products\Stocks\Type\Id\ProductStockUid;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusPurchase;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Attribute\When;

/**
 * @group products-stocks
 * @group products-stocks-purchase
 */
#[When(env: 'test')]
final class PurchaseProductStockTest extends KernelTestCase
{

    public static function setUpBeforeClass(): void
    {
        $ProductStockStatus = new ProductStockStatusPurchase();

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $main = $em->getRepository(ProductStock::class)
            ->findBy(['id' => ProductStockUid::TEST]);

        foreach($main as $remove)
        {
            $em->remove($remove);
        }

        $event = $em->getRepository(ProductStockEvent::class)
            ->findBy(['main' => ProductStockUid::TEST]);

        foreach($event as $remove)
        {
            $em->remove($remove);
        }

        $total = $em->getRepository(ProductStockTotal::class)
            ->findBy(['profile' => UserProfileUid::TEST]);

        foreach($total as $remove)
        {
            $em->remove($remove);
        }

        $em->flush();

        $em->clear();

    }


    /**
     * Тест нового закупочного листа
     */
    public function testUseCase(): void
    {
        $PurchaseProductStockDTO = new ProductStockDTO();
        $PurchaseProductStockDTO->setProfile(clone new UserProfileUid());

        $PurchaseProductStockDTO->setComment('Comment');
        self::assertEquals('Comment', $PurchaseProductStockDTO->getComment());

        $PurchaseProductStockDTO->setNumber('Number');
        self::assertEquals('Number', $PurchaseProductStockDTO->getNumber());

        $ProductStockDTO = new ProductStockDTO();

        $ProductUid = new ProductUid();
        $ProductStockDTO->setProduct($ProductUid);
        self::assertSame($ProductUid, $ProductStockDTO->getProduct());

        $ProductOfferConst = new ProductOfferConst();
        $ProductStockDTO->setOffer($ProductOfferConst);
        self::assertSame($ProductOfferConst, $ProductStockDTO->getOffer());

        $ProductVariationConst = new ProductVariationConst();
        $ProductStockDTO->setVariation($ProductVariationConst);
        self::assertSame($ProductVariationConst, $ProductStockDTO->getVariation());

        $ProductModificationConst = new ProductModificationConst();
        $ProductStockDTO->setModification($ProductModificationConst);
        self::assertSame($ProductModificationConst, $ProductStockDTO->getModification());

        $ProductStockDTO->setTotal(100);
        self::assertEquals(100, $ProductStockDTO->getTotal());

        $PurchaseProductStockDTO->addProduct($ProductStockDTO);
        self::assertCount(1, $PurchaseProductStockDTO->getProduct());


        $ProductStockDTO = new ProductStockDTO();
        $ProductStockDTO->setProduct(clone $ProductUid);
        $ProductStockDTO->setOffer(clone $ProductOfferConst);
        $ProductStockDTO->setVariation(clone $ProductVariationConst);
        $ProductStockDTO->setModification(clone $ProductModificationConst);
        $ProductStockDTO->setTotal(200);

        $PurchaseProductStockDTO->addProduct($ProductStockDTO);
        self::assertCount(2, $PurchaseProductStockDTO->getProduct());

        $PurchaseProductStockDTO->removeProduct($ProductStockDTO);
        self::assertCount(1, $PurchaseProductStockDTO->getProduct());

        /** @var ManufactureProductStockHandler $PurchaseProductStockHandler */
        $PurchaseProductStockHandler = self::getContainer()->get(ManufactureProductStockHandler::class);
        $handle = $PurchaseProductStockHandler->handle($PurchaseProductStockDTO);

        self::assertTrue(($handle instanceof ProductStock), $handle.': Ошибка ProductStock');


    }
}
