<?php
/*
 *  Copyright 2023.  Baks.dev <admin@baks.dev>
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

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Manufacture\Part\Entity\Event\ManufacturePartEvent;
use BaksDev\Manufacture\Part\Entity\ManufacturePart;
use BaksDev\Manufacture\Part\Entity\Products\ManufacturePartProduct;
use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatus\ManufacturePartStatusOpen;
use BaksDev\Products\Category\Entity\Offers\CategoryProductOffers;
use BaksDev\Products\Category\Entity\Offers\Trans\CategoryProductOffersTrans;
use BaksDev\Products\Category\Entity\Offers\Variation\Modification\CategoryProductModification;
use BaksDev\Products\Category\Entity\Offers\Variation\Modification\Trans\CategoryProductModificationTrans;
use BaksDev\Products\Category\Entity\Offers\Variation\CategoryProductVariation;
use BaksDev\Products\Category\Entity\Offers\Variation\Trans\CategoryProductVariationTrans;
use BaksDev\Products\Category\Entity\CategoryProduct;
use BaksDev\Products\Category\Entity\Trans\CategoryProductTrans;
use BaksDev\Products\Product\Entity\Event\ProductEvent;
use BaksDev\Products\Product\Entity\Offers\Image\ProductOfferImage;
use BaksDev\Products\Product\Entity\Offers\ProductOffer;
use BaksDev\Products\Product\Entity\Offers\Variation\Image\ProductVariationImage;
use BaksDev\Products\Product\Entity\Offers\Variation\Modification\Image\ProductModificationImage;
use BaksDev\Products\Product\Entity\Offers\Variation\Modification\ProductModification;
use BaksDev\Products\Product\Entity\Offers\Variation\ProductVariation;
use BaksDev\Products\Product\Entity\Photo\ProductPhoto;
use BaksDev\Products\Product\Entity\Trans\ProductTrans;
use BaksDev\Users\Profile\UserProfile\Entity\Personal\UserProfilePersonal;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\UsersTable\Entity\Actions\Event\UsersTableActionsEvent;
use BaksDev\Users\UsersTable\Entity\Actions\Trans\UsersTableActionsTrans;

//use BaksDev\Manufacture\Part\Type\Marketplace\ManufacturePartMarketplace;

final class OpenManufacturePartRepository implements OpenManufacturePartInterface
{
    private DBALQueryBuilder $DBALQueryBuilder;

    public function __construct(
        DBALQueryBuilder $DBALQueryBuilder,
    )
    {
        $this->DBALQueryBuilder = $DBALQueryBuilder;
    }

    /**
     * Метод возвращает список открытых партий пользователя
     */
    public function fetchOpenManufacturePartAssociative(
        UserProfileUid $profile,
        //UserProfileUid $current
    ): bool|array
    {
        $qb = $this->DBALQueryBuilder->createQueryBuilder(self::class)->bindLocal();

        $qb->select('part.id');
        $qb->addSelect('part.number');
        $qb->addSelect('part.quantity');
        $qb->from(ManufacturePart::TABLE, 'part');


        $qb->addSelect('part_event.id AS event');
        $qb->addSelect('part_event.complete');
        $qb->join(
            'part', ManufacturePartEvent::TABLE,
            'part_event',
            'part_event.id = part.event AND part_event.status = :status'
        );

        $qb->setParameter('status',
            ManufacturePartStatusOpen::STATUS);


        $qb
            ->andWhere('part_event.profile = :profile')
            ->setParameter('profile', $profile, UserProfileUid::TYPE);



        //        if($marketplace)
        //        {
        //
        //            $qb->andWhere('part_event.marketplace = :marketplace');
        //            $qb->setParameter('marketplace', $marketplace, ManufacturePartMarketplace::TYPE);
        //        }


        /** Ответственное лицо (Профиль пользователя) */

        $qb->addSelect('users_profile.event as users_profile_event');
        $qb->leftJoin(
            'part_event',
            UserProfile::TABLE,
            'users_profile',
            'users_profile.id = part_event.profile'
        );

        $qb->addSelect('users_profile_personal.username AS users_profile_username');
        $qb->leftJoin(
            'users_profile',
            UserProfilePersonal::TABLE,
            'users_profile_personal',
            'users_profile_personal.event = users_profile.event'
        );


        /**
         * Последний добавленный продукт
         */

        $qb->addSelect('part_product.total AS product_total');

        $qb->leftOneJoin(
            'part_event',
            ManufacturePartProduct::TABLE,
            'part_product',
            'part_product.event = part_event.id'
        );


        $qb->addSelect('product_event.id AS product_event');
        $qb->leftJoin(
            'part_product',
            ProductEvent::TABLE,
            'product_event',
            'product_event.id = part_product.product'
        );

        $qb->addSelect('product_trans.name AS product_name');
        $qb->leftJoin(
            'product_event',
            ProductTrans::TABLE,
            'product_trans',
            'product_trans.event = product_event.id AND product_trans.local = :local'
        );

        /* Торговое предложение */

        $qb->addSelect('product_offer.id as product_offer_uid');
        $qb->addSelect('product_offer.value as product_offer_value');
        $qb->addSelect('product_offer.postfix as product_offer_postfix');


        $qb->leftJoin(
            'part_product',
            ProductOffer::TABLE,
            'product_offer',
            'product_offer.id = part_product.offer OR product_offer.id IS NULL'
        );

        /* Получаем тип торгового предложения */
        $qb->addSelect('category_offer.reference AS product_offer_reference');
        $qb->leftJoin(
            'product_offer',
            CategoryProductOffers::TABLE,
            'category_offer',
            'category_offer.id = product_offer.category_offer'
        );

        /* Получаем название торгового предложения */
        $qb->addSelect('category_offer_trans.name as product_offer_name');
        $qb->addSelect('category_offer_trans.postfix as product_offer_name_postfix');
        $qb->leftJoin(
            'category_offer',
            CategoryProductOffersTrans::TABLE,
            'category_offer_trans',
            'category_offer_trans.offer = category_offer.id AND category_offer_trans.local = :local'
        );


        /* Множественные варианты торгового предложения */

        $qb->addSelect('product_variation.id as product_variation_uid');
        $qb->addSelect('product_variation.value as product_variation_value');
        $qb->addSelect('product_variation.postfix as product_variation_postfix');

        $qb->leftJoin(
            'part_product',
            ProductVariation::TABLE,
            'product_variation',
            'product_variation.id = part_product.variation OR product_variation.id IS NULL '
        );


        /* Получаем тип множественного варианта */
        $qb->addSelect('category_variation.reference as product_variation_reference');
        $qb->leftJoin(
            'product_variation',
            CategoryProductVariation::TABLE,
            'category_variation',
            'category_variation.id = product_variation.category_variation'
        );

        /* Получаем название множественного варианта */
        $qb->addSelect('category_variation_trans.name as product_variation_name');

        $qb->addSelect('category_variation_trans.postfix as product_variation_name_postfix');
        $qb->leftJoin(
            'category_variation',
            CategoryProductVariationTrans::TABLE,
            'category_variation_trans',
            'category_variation_trans.variation = category_variation.id AND category_variation_trans.local = :local'
        );



        /* Модификация множественного варианта торгового предложения */

        $qb->addSelect('product_modification.id as product_modification_uid');
        $qb->addSelect('product_modification.value as product_modification_value');
        $qb->addSelect('product_modification.postfix as product_modification_postfix');

        $qb->leftJoin(
            'part_product',
            ProductModification::TABLE,
            'product_modification',
            'product_modification.id = part_product.modification OR product_modification.id IS NULL '
        );


        /* Получаем тип модификации множественного варианта */
        $qb->addSelect('category_modification.reference as product_modification_reference');
        $qb->leftJoin(
            'product_modification',
            CategoryProductModification::TABLE,
            'category_modification',
            'category_modification.id = product_modification.category_modification'
        );

        /* Получаем название типа модификации */
        $qb->addSelect('category_modification_trans.name as product_modification_name');
        $qb->addSelect('category_modification_trans.postfix as product_modification_name_postfix');
        $qb->leftJoin(
            'category_modification',
            CategoryProductModificationTrans::TABLE,
            'category_modification_trans',
            'category_modification_trans.modification = category_modification.id AND category_modification_trans.local = :local'
        );


        /* Фото продукта */

        $qb->leftJoin(
            'product_event',
            ProductPhoto::TABLE,
            'product_photo',
            'product_photo.event = product_event.id AND product_photo.root = true'
        );

        $qb->leftJoin(
            'product_offer',
            ProductModificationImage::TABLE,
            'product_modification_image',
            'product_modification_image.modification = product_modification.id AND product_modification_image.root = true'
        );

        $qb->leftJoin(
            'product_offer',
            ProductVariationImage::TABLE,
            'product_variation_image',
            'product_variation_image.variation = product_variation.id AND product_variation_image.root = true'
        );


        $qb->leftJoin(
            'product_offer',
            ProductOfferImage::TABLE,
            'product_offer_images',
            'product_offer_images.offer = product_offer.id AND product_offer_images.root = true'
        );

        $qb->addSelect(
            "
			CASE
				WHEN product_modification_image.name IS NOT NULL THEN
					CONCAT ( '/upload/".ProductModificationImage::TABLE."' , '/', product_modification_image.name)
			   WHEN product_variation_image.name IS NOT NULL THEN
					CONCAT ( '/upload/".ProductVariationImage::TABLE."' , '/', product_variation_image.name)
			   WHEN product_offer_images.name IS NOT NULL THEN
					CONCAT ( '/upload/".ProductOfferImage::TABLE."' , '/', product_offer_images.name)
			   WHEN product_photo.name IS NOT NULL THEN
					CONCAT ( '/upload/".ProductPhoto::TABLE."' , '/', product_photo.name)
			   ELSE NULL
			END AS product_image
		"
        );

        /* Флаг загрузки файла CDN */
        $qb->addSelect('
			CASE
			    WHEN product_modification_image.name IS NOT NULL THEN
					product_modification_image.ext
			   WHEN product_variation_image.name IS NOT NULL THEN
					product_variation_image.ext
			   WHEN product_offer_images.name IS NOT NULL THEN
					product_offer_images.ext
			   WHEN product_photo.name IS NOT NULL THEN
					product_photo.ext
			   ELSE NULL
			END AS product_image_ext
		');

        /* Флаг загрузки файла CDN */
        $qb->addSelect('
			CASE
			   WHEN product_modification_image.name IS NOT NULL THEN
					product_modification_image.cdn			   
			    WHEN product_variation_image.name IS NOT NULL THEN
					product_variation_image.cdn
			   WHEN product_offer_images.name IS NOT NULL THEN
					product_offer_images.cdn
			   WHEN product_photo.name IS NOT NULL THEN
					product_photo.cdn
			   ELSE NULL
			END AS product_image_cdn
		');



        /** Категория производства */

//        $qb->leftJoin(
//            'part_event',
//            UsersTableActions::TABLE,
//            'actions',
//            'actions.event = part_event.action '
//        );

        $qb->addSelect('actions_event.id AS actions_event');
        $qb->leftJoin(
            'part_event',
            UsersTableActionsEvent::TABLE,
            'actions_event',
            'actions_event.id = part_event.action'
        );

        $qb->addSelect('actions_trans.name AS actions_name');
        $qb->leftJoin(
            'part_event',
            UsersTableActionsTrans::TABLE,
            'actions_trans',
            'actions_trans.event = actions_event.id AND actions_trans.local = :local'
        );

        $qb->addSelect('category.id AS category_id');
        $qb->leftJoin(
            'actions_event',
            CategoryProduct::TABLE,
            'category',
            'category.id = actions_event.category'
        );

        $qb->addSelect('trans.name AS category_name');
        $qb->leftJoin(
            'category',
            CategoryProductTrans::TABLE,
            'trans',
            'trans.event = category.event AND trans.local = :local'
        );

        return $qb
            ->enableCache('manufacture-part', 86400)
            //->fetchAllAssociative();
            ->fetchAssociative();
    }
}