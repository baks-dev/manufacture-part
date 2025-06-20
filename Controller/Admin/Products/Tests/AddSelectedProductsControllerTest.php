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

namespace BaksDev\Manufacture\Part\Controller\Admin\Products\Tests;

use BaksDev\Products\Product\Type\Event\ProductEventUid;
use BaksDev\Products\Product\Type\Offers\Id\ProductOfferUid;
use BaksDev\Products\Product\Type\Offers\Variation\Id\ProductVariationUid;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\Id\ProductModificationUid;
use BaksDev\Users\User\Tests\TestUserAccount;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\Attribute\When;

/**
 * @group manufacture-part
 */
#[When(env: 'test')]
class AddSelectedProductsControllerTest extends WebTestCase
{
    private static ?string $url = null;
    private static ?array $post_data = null;

    private const string ROLE = 'ROLE_MANUFACTURE_PART_ADD';

    public static function setUpBeforeClass(): void
    {
        self::$url = '/admin/manufacture/part/selected-product/add';

        self::$post_data = [
            'manufacture_selection_part_products_form' => [
                'product_form_data' => [
                    [
                        "product" => ProductEventUid::TEST,
                        'offer' => ProductOfferUid::TEST,
                        'variation' => ProductVariationUid::TEST,
                        'modification' => ProductModificationUid::TEST,
                    ]
                ]
            ]
        ];

    }

    /** Доступ по роли ROLE_MANUFACTURE_PART_ADD */
    public function testRoleSuccessful(): void
    {
        self::ensureKernelShutdown();

        $client = static::createClient();

        $usr = TestUserAccount::getModer(self::ROLE);

        foreach(TestUserAccount::getDevice() as $device)
        {
            $client->setServerParameter('HTTP_USER_AGENT', $device);
            $client->loginUser($usr, 'user');
            $client->request('POST', self::$url, self::$post_data);

            self::assertResponseIsSuccessful();
        }

        self::assertTrue(true);
    }

    /** Доступ по роли ROLE_USER */
    public function testRoleUserFiled(): void
    {
        self::ensureKernelShutdown();

        $client = static::createClient();

        $usr = TestUserAccount::getUsr();

        foreach(TestUserAccount::getDevice() as $device)
        {
            $client->setServerParameter('HTTP_USER_AGENT', $device);
            $client->loginUser($usr, 'user');
            $client->request('POST', self::$url, self::$post_data);

            // У пользователя недостаточно прав для доступа к ресурсу
            self::assertResponseStatusCodeSame(403);
        }

        self::assertTrue(true);
    }

    /** Доступ без роли */
    public function testGuestFiled(): void
    {
        self::ensureKernelShutdown();

        $client = static::createClient();

        foreach(TestUserAccount::getDevice() as $device)
        {
            $client->setServerParameter('HTTP_USER_AGENT', $device);
            $client->request('POST', self::$url, self::$post_data);

            // Для доступа к ресурсу требуется аутентификация
            self::assertResponseStatusCodeSame(401);
        }

        self::assertTrue(true);
    }
}