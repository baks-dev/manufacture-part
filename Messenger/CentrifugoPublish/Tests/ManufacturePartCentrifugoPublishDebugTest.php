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

namespace BaksDev\Manufacture\Part\Messenger\CentrifugoPublish\Tests;

use BaksDev\Manufacture\Part\Messenger\CentrifugoPublish\ManufacturePartCentrifugoPublishHandler;
use BaksDev\Manufacture\Part\Messenger\CentrifugoPublish\ManufacturePartCentrifugoPublishMessage;
use BaksDev\Manufacture\Part\Type\Event\ManufacturePartEventUid;
use BaksDev\Products\Product\Type\Event\ProductEventUid;
use BaksDev\Products\Product\Type\Offers\Id\ProductOfferUid;
use BaksDev\Products\Product\Type\Offers\Variation\Id\ProductVariationUid;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\Id\ProductModificationUid;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Attribute\When;

#[When(env: 'test')]
#[Group('manufacture-part')]
class ManufacturePartCentrifugoPublishDebugTest extends KernelTestCase
{
    public function testFullConstructor(): void
    {
        $identifier = '8f0004f5-42d4-760a-9212-c94474d4f190';
        $UserProfileUid = new UserProfileUid(UserProfileUid::TEST);
        $ManufacturePartEventUid = new ManufacturePartEventUid(ManufacturePartEventUid::TEST);
        $ProductEventUid = new ProductEventUid(ProductEventUid::TEST);
        $ProductOfferUid = new ProductOfferUid(ProductOfferUid::TEST);
        $ProductVariationUid = new ProductVariationUid(ProductVariationUid::TEST);
        $ProductModificationUid = new ProductModificationUid(ProductModificationUid::TEST);
        $total = 100;

        $ManufacturePartCentrifugoPublishMessage = new ManufacturePartCentrifugoPublishMessage(
            $identifier,
            $UserProfileUid,
            $ManufacturePartEventUid,
            $ProductEventUid,
            $ProductOfferUid,
            $ProductVariationUid,
            $ProductModificationUid,
            $total,
        );

        // Проверяем все параметры
        $this->assertEquals($identifier, $ManufacturePartCentrifugoPublishMessage->getIdentifier());

        $this->assertEquals($UserProfileUid, $ManufacturePartCentrifugoPublishMessage->getProfile());
        $this->assertTrue($ManufacturePartCentrifugoPublishMessage->getProfile()->equals(UserProfileUid::TEST));

        $this->assertEquals($ManufacturePartEventUid, $ManufacturePartCentrifugoPublishMessage->getManufacturePartEvent());
        $this->assertTrue($ManufacturePartCentrifugoPublishMessage->getManufacturePartEvent()->equals(ManufacturePartEventUid::TEST));

        $this->assertEquals($ProductEventUid, $ManufacturePartCentrifugoPublishMessage->getEvent());
        $this->assertTrue($ManufacturePartCentrifugoPublishMessage->getEvent()->equals(ProductEventUid::TEST));

        $this->assertEquals($ProductOfferUid, $ManufacturePartCentrifugoPublishMessage->getOffer());
        $this->assertTrue($ManufacturePartCentrifugoPublishMessage->getOffer()->equals(ProductOfferUid::TEST));

        $this->assertEquals($ProductVariationUid, $ManufacturePartCentrifugoPublishMessage->getVariation());
        $this->assertTrue($ManufacturePartCentrifugoPublishMessage->getVariation()->equals(ProductVariationUid::TEST));

        $this->assertEquals($ProductModificationUid, $ManufacturePartCentrifugoPublishMessage->getModification());
        $this->assertTrue($ManufacturePartCentrifugoPublishMessage->getModification()->equals(ProductModificationUid::TEST));

        $this->assertEquals($total, $ManufacturePartCentrifugoPublishMessage->getTotal());

        /** @var ManufacturePartCentrifugoPublishHandler $ManufacturePartCentrifugoPublishHandler */
        $ManufacturePartCentrifugoPublishHandler = self::getContainer()->get(ManufacturePartCentrifugoPublishHandler::class);
        $ManufacturePartCentrifugoPublishHandler($ManufacturePartCentrifugoPublishMessage);
    }

    public function testMinimalConstructor(): void
    {
        $identifier = 'aba5a019-1ff6-7899-8973-d114b45363a6';
        $UserProfileUid = new UserProfileUid(UserProfileUid::TEST);

        $ManufacturePartCentrifugoPublishMessage = new ManufacturePartCentrifugoPublishMessage($identifier, $UserProfileUid);

        // Проверяем обязательные параметры
        $this->assertEquals($identifier, $ManufacturePartCentrifugoPublishMessage->getIdentifier());
        $this->assertTrue($ManufacturePartCentrifugoPublishMessage->getProfile()->equals(UserProfileUid::TEST));
        $this->assertEquals($UserProfileUid, $ManufacturePartCentrifugoPublishMessage->getProfile());

        // Проверяем отсутствующие опциональные параметры
        $this->assertFalse($ManufacturePartCentrifugoPublishMessage->getManufacturePartEvent());
        $this->assertFalse($ManufacturePartCentrifugoPublishMessage->getEvent());
        $this->assertFalse($ManufacturePartCentrifugoPublishMessage->getOffer());
        $this->assertFalse($ManufacturePartCentrifugoPublishMessage->getVariation());
        $this->assertFalse($ManufacturePartCentrifugoPublishMessage->getModification());
        $this->assertFalse($ManufacturePartCentrifugoPublishMessage->getTotal());


        /** @var ManufacturePartCentrifugoPublishHandler $ManufacturePartCentrifugoPublishHandler */
        $ManufacturePartCentrifugoPublishHandler = self::getContainer()->get(ManufacturePartCentrifugoPublishHandler::class);
        $ManufacturePartCentrifugoPublishHandler($ManufacturePartCentrifugoPublishMessage);

    }


    public function testPartialConstructor(): void
    {
        $identifier = '16e4c1f5-acd5-7508-9bda-c533486601eb';
        $UserProfileUid = new UserProfileUid(UserProfileUid::TEST);
        $ProductEventUid = new ProductEventUid(ProductEventUid::TEST);
        $total = 50;

        $ManufacturePartCentrifugoPublishMessage = new ManufacturePartCentrifugoPublishMessage(
            $identifier,
            $UserProfileUid,
            null,
            $ProductEventUid,
            null,
            null,
            null,
            $total,
        );

        // Проверяем переданные параметры
        $this->assertEquals($identifier, $ManufacturePartCentrifugoPublishMessage->getIdentifier());

        $this->assertEquals($UserProfileUid, $ManufacturePartCentrifugoPublishMessage->getProfile());
        $this->assertTrue($ManufacturePartCentrifugoPublishMessage->getProfile()->equals(UserProfileUid::TEST));


        $this->assertEquals($ProductEventUid, $ManufacturePartCentrifugoPublishMessage->getEvent());
        $this->assertTrue($ManufacturePartCentrifugoPublishMessage->getEvent()->equals(ProductEventUid::TEST));

        $this->assertEquals($total, $ManufacturePartCentrifugoPublishMessage->getTotal());

        // Проверяем отсутствующие параметры
        $this->assertFalse($ManufacturePartCentrifugoPublishMessage->getManufacturePartEvent());
        $this->assertFalse($ManufacturePartCentrifugoPublishMessage->getOffer());
        $this->assertFalse($ManufacturePartCentrifugoPublishMessage->getVariation());
        $this->assertFalse($ManufacturePartCentrifugoPublishMessage->getModification());

        /** @var ManufacturePartCentrifugoPublishHandler $ManufacturePartCentrifugoPublishHandler */
        $ManufacturePartCentrifugoPublishHandler = self::getContainer()->get(ManufacturePartCentrifugoPublishHandler::class);
        $ManufacturePartCentrifugoPublishHandler($ManufacturePartCentrifugoPublishMessage);
    }
}