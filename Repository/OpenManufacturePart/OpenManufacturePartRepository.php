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

namespace BaksDev\Manufacture\Part\Repository\OpenManufacturePart;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Delivery\Entity\Delivery;
use BaksDev\Delivery\Entity\Trans\DeliveryTrans;
use BaksDev\Manufacture\Part\Entity\Event\ManufacturePartEvent;
use BaksDev\Manufacture\Part\Entity\Invariable\ManufacturePartInvariable;
use BaksDev\Manufacture\Part\Entity\ManufacturePart;
use BaksDev\Manufacture\Part\Entity\Products\ManufacturePartProduct;
use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatus;
use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatus\ManufacturePartStatusOpen;
use BaksDev\Products\Category\Entity\CategoryProduct;
use BaksDev\Products\Category\Entity\Offers\CategoryProductOffers;
use BaksDev\Products\Category\Entity\Offers\Trans\CategoryProductOffersTrans;
use BaksDev\Products\Category\Entity\Offers\Variation\CategoryProductVariation;
use BaksDev\Products\Category\Entity\Offers\Variation\Modification\CategoryProductModification;
use BaksDev\Products\Category\Entity\Offers\Variation\Modification\Trans\CategoryProductModificationTrans;
use BaksDev\Products\Category\Entity\Offers\Variation\Trans\CategoryProductVariationTrans;
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
use BaksDev\Users\Profile\UserProfile\Repository\UserProfileTokenStorage\UserProfileTokenStorageInterface;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\UsersTable\Entity\Actions\Event\UsersTableActionsEvent;
use BaksDev\Users\UsersTable\Entity\Actions\Trans\UsersTableActionsTrans;
use InvalidArgumentException;

//use BaksDev\Manufacture\Part\Type\Marketplace\ManufacturePartMarketplace;

final class OpenManufacturePartRepository implements OpenManufacturePartInterface
{

    private UserProfileUid|false $profile = false;

    public function __construct(
        private readonly DBALQueryBuilder $DBALQueryBuilder,
        private readonly UserProfileTokenStorageInterface $UserProfileTokenStorage
    ) {}

    public function forFixed(UserProfile|UserProfileUid|string $profile): self
    {
        if(empty($profile))
        {
            throw new InvalidArgumentException('Invalid Argument UserProfile');
        }

        if(is_string($profile))
        {
            $profile = new UserProfileUid($profile);
        }

        if($profile instanceof UserProfile)
        {
            $profile = $profile->getId();
        }

        $this->profile = $profile;

        return $this;
    }

    public function find(): OpenManufacturePartResult|false
    {
        $dbal = $this->DBALQueryBuilder->createQueryBuilder(self::class)
            ->bindLocal();

        /** ManufacturePartInvariable */

        $dbal
            ->addSelect('invariable.number')
            ->addSelect('invariable.quantity')
            ->from(ManufacturePartInvariable::class, 'invariable');

        $dbal
            ->andWhere('invariable.profile = :profile')
            ->setParameter(
                key: 'profile',
                value: $this->profile ?: $this->UserProfileTokenStorage->getProfile(),
                type: UserProfileUid::TYPE
            );

        $dbal
            ->addSelect('part.id')
            ->addSelect('part.event')
            ->join(
                'invariable',
                ManufacturePart::class,
                'part',
                'part.id = invariable.main'
            );


        $dbal
            ->addSelect('part_event.complete AS complete_id')
            ->join(
                'part',
                ManufacturePartEvent::class,
                'part_event',
                'part_event.id = part.event AND part_event.fixed = :fixed AND part_event.status = :status'
            )
            ->setParameter(
                'status',
                ManufacturePartStatusOpen::class,
                ManufacturePartStatus::TYPE

            )
            ->setParameter(
                'fixed',
                $this->profile,
                UserProfileUid::TYPE
            );

        /** Способ доставки  */

        $dbal
            ->leftJoin(
                'part_event',
                Delivery::class,
                'delivery',
                'delivery.id = part_event.complete'
            );

        $dbal
            ->addSelect('delivery_trans.name AS complete_name')
            ->leftJoin(
                'part_event',
                DeliveryTrans::class,
                'delivery_trans',
                'delivery_trans.event = delivery.event AND delivery_trans.local = :local'
            );

        /** Ответственное лицо (Профиль пользователя) */

        $dbal
            //->addSelect('users_profile.event as users_profile_event')
            ->leftJoin(
                'part_event',
                UserProfile::class,
                'users_profile',
                'users_profile.id = part_event.fixed'
            );

        $dbal
            ->addSelect('users_profile_personal.username AS users_profile_username')
            ->leftJoin(
                'users_profile',
                UserProfilePersonal::class,
                'users_profile_personal',
                'users_profile_personal.event = users_profile.event'
            );


        /**
         * Последний добавленный продукт
         */

        $dbal
            ->addSelect('part_product.total AS product_total')
            ->leftOneJoin(
                'part_event',
                ManufacturePartProduct::class,
                'part_product',
                'part_product.event = part_event.id'
            );


        $dbal
            ->addSelect('product_event.main AS product_id')
            ->addSelect('product_event.id AS product_event')
            ->leftJoin(
                'part_product',
                ProductEvent::class,
                'product_event',
                'product_event.id = part_product.product'
            );

        $dbal
            ->addSelect('product_trans.name AS product_name')
            ->leftJoin(
                'product_event',
                ProductTrans::class,
                'product_trans',
                'product_trans.event = product_event.id AND product_trans.local = :local'
            );

        /* Торговое предложение */

        $dbal
            ->addSelect('product_offer.id as product_offer_uid')
            ->addSelect('product_offer.value as product_offer_value')
            ->addSelect('product_offer.postfix as product_offer_postfix')
            ->addSelect('product_offer.const as product_offer_const')
            ->leftJoin(
                'part_product',
                ProductOffer::class,
                'product_offer',
                'product_offer.id = part_product.offer OR product_offer.id IS NULL'
            );

        /* Получаем тип торгового предложения */
        $dbal
            ->addSelect('category_offer.reference AS product_offer_reference')
            ->leftJoin(
                'product_offer',
                CategoryProductOffers::class,
                'category_offer',
                'category_offer.id = product_offer.category_offer'
            );

        /* Получаем название торгового предложения */
        $dbal
            ->addSelect('category_offer_trans.name as product_offer_name')
            ->addSelect('category_offer_trans.postfix as product_offer_name_postfix')
            ->leftJoin(
                'category_offer',
                CategoryProductOffersTrans::class,
                'category_offer_trans',
                'category_offer_trans.offer = category_offer.id AND category_offer_trans.local = :local'
            );


        /* Множественные варианты торгового предложения */

        $dbal
            ->addSelect('product_variation.id as product_variation_uid')
            ->addSelect('product_variation.const as product_variation_const')
            ->addSelect('product_variation.value as product_variation_value')
            ->addSelect('product_variation.postfix as product_variation_postfix')
            ->leftJoin(
                'part_product',
                ProductVariation::class,
                'product_variation',
                'product_variation.id = part_product.variation OR product_variation.id IS NULL '
            );


        /* Получаем тип множественного варианта */
        $dbal
            ->addSelect('category_variation.reference as product_variation_reference')
            ->leftJoin(
                'product_variation',
                CategoryProductVariation::class,
                'category_variation',
                'category_variation.id = product_variation.category_variation'
            );

        /* Получаем название множественного варианта */
        $dbal
            ->addSelect('category_variation_trans.name as product_variation_name')
            ->addSelect('category_variation_trans.postfix as product_variation_name_postfix')
            ->leftJoin(
                'category_variation',
                CategoryProductVariationTrans::class,
                'category_variation_trans',
                'category_variation_trans.variation = category_variation.id AND category_variation_trans.local = :local'
            );


        /* Модификация множественного варианта торгового предложения */

        $dbal
            ->addSelect('product_modification.id as product_modification_uid')
            ->addSelect('product_modification.const as product_modification_const')
            ->addSelect('product_modification.value as product_modification_value')
            ->addSelect('product_modification.postfix as product_modification_postfix')
            ->leftJoin(
                'part_product',
                ProductModification::class,
                'product_modification',
                'product_modification.id = part_product.modification OR product_modification.id IS NULL '
            );


        /* Получаем тип модификации множественного варианта */
        $dbal
            ->addSelect('category_modification.reference as product_modification_reference')
            ->leftJoin(
                'product_modification',
                CategoryProductModification::class,
                'category_modification',
                'category_modification.id = product_modification.category_modification'
            );

        /* Получаем название типа модификации */
        $dbal
            ->addSelect('category_modification_trans.name as product_modification_name')
            ->addSelect('category_modification_trans.postfix as product_modification_name_postfix')
            ->leftJoin(
                'category_modification',
                CategoryProductModificationTrans::class,
                'category_modification_trans',
                'category_modification_trans.modification = category_modification.id AND category_modification_trans.local = :local'
            );


        /* Фото продукта */

        $dbal->leftJoin(
            'product_event',
            ProductPhoto::class,
            'product_photo',
            'product_photo.event = product_event.id AND product_photo.root = true'
        );

        $dbal->leftJoin(
            'product_offer',
            ProductModificationImage::class,
            'product_modification_image',
            'product_modification_image.modification = product_modification.id AND product_modification_image.root = true'
        );

        $dbal->leftJoin(
            'product_offer',
            ProductVariationImage::class,
            'product_variation_image',
            'product_variation_image.variation = product_variation.id AND product_variation_image.root = true'
        );


        $dbal->leftJoin(
            'product_offer',
            ProductOfferImage::class,
            'product_offer_images',
            'product_offer_images.offer = product_offer.id AND product_offer_images.root = true'
        );

        $dbal->addSelect(
            "
			CASE
				WHEN product_modification_image.name IS NOT NULL THEN
					CONCAT ( '/upload/".$dbal->table(ProductModificationImage::class)."' , '/', product_modification_image.name)
			   WHEN product_variation_image.name IS NOT NULL THEN
					CONCAT ( '/upload/".$dbal->table(ProductVariationImage::class)."' , '/', product_variation_image.name)
			   WHEN product_offer_images.name IS NOT NULL THEN
					CONCAT ( '/upload/".$dbal->table(ProductOfferImage::class)."' , '/', product_offer_images.name)
			   WHEN product_photo.name IS NOT NULL THEN
					CONCAT ( '/upload/".$dbal->table(ProductPhoto::class)."' , '/', product_photo.name)
			   ELSE NULL
			END AS product_image
		"
        );

        /* Флаг загрузки файла CDN */
        $dbal->addSelect('
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
        $dbal->addSelect('
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

        //        $dbal->leftJoin(
        //            'part_event',
        //            UsersTableActions::class,
        //            'actions',
        //            'actions.event = part_event.action '
        //        );

        $dbal
            ->addSelect('actions_event.id AS actions_id')
            ->leftJoin(
                'part_event',
                UsersTableActionsEvent::class,
                'actions_event',
                'actions_event.id = part_event.action'
            );

        $dbal
            ->addSelect('actions_trans.name AS actions_name')
            ->leftJoin(
                'part_event',
                UsersTableActionsTrans::class,
                'actions_trans',
                'actions_trans.event = actions_event.id AND actions_trans.local = :local'
            );

        $dbal
            ->addSelect('category.id AS category_id')
            ->leftJoin(
                'actions_event',
                CategoryProduct::class,
                'category',
                'category.id = actions_event.category'
            );

        $dbal
            ->addSelect('trans.name AS category_name')
            ->leftJoin(
                'category',
                CategoryProductTrans::class,
                'trans',
                'trans.event = category.event AND trans.local = :local'
            );

        return $dbal->fetchHydrate(OpenManufacturePartResult::class);
    }


    /**
     * @depricate
     * Метод возвращает список открытых партий пользователя
     */
    public function fetchOpenManufacturePartAssociative(): bool|array
    {


        $dbal = $this->DBALQueryBuilder->createQueryBuilder(self::class)->bindLocal();


        /** ManufacturePartInvariable */

        $dbal
            ->addSelect('invariable.number')
            ->addSelect('invariable.quantity')
            ->from(ManufacturePartInvariable::class, 'invariable');

        $dbal
            ->andWhere('invariable.profile = :profile')
            ->setParameter(
                key: 'profile',
                value: $this->UserProfileTokenStorage->getProfile(),
                type: UserProfileUid::TYPE
            );

        $dbal
            ->addSelect('part.id')
            ->addSelect('part.event')
            ->join(
                'invariable',
                ManufacturePart::class,
                'part',
                'part.id = invariable.main'
            );


        $dbal
            ->addSelect('part_event.id AS event')
            ->addSelect('part_event.complete AS complete_id')
            ->join(
                'part',
                ManufacturePartEvent::class,
                'part_event',
                'part_event.id = part.event AND part_event.fixed = :fixed AND part_event.status = :status'
            )
            ->setParameter(
                'status',
                ManufacturePartStatusOpen::class,
                ManufacturePartStatus::TYPE

            )
            ->setParameter(
                'fixed',
                $this->profile,
                UserProfileUid::TYPE
            );

        /** Способ доставки  */

        $dbal
            ->leftJoin(
                'part_event',
                Delivery::class,
                'delivery',
                'delivery.id = part_event.complete'
            );

        $dbal
            ->addSelect('delivery_trans.name AS complete_name')
            ->leftJoin(
                'part_event',
                DeliveryTrans::class,
                'delivery_trans',
                'delivery_trans.event = delivery.event AND delivery_trans.local = :local'
            );


        /** Ответственное лицо (Профиль пользователя) */

        $dbal
            ->addSelect('users_profile.event as users_profile_event')
            ->leftJoin(
                'part_event',
                UserProfile::class,
                'users_profile',
                'users_profile.id = part_event.fixed'
            );

        $dbal
            ->addSelect('users_profile_personal.username AS users_profile_username')
            ->leftJoin(
                'users_profile',
                UserProfilePersonal::class,
                'users_profile_personal',
                'users_profile_personal.event = users_profile.event'
            );


        /**
         * Последний добавленный продукт
         */

        $dbal
            ->addSelect('part_product.total AS product_total')
            ->leftOneJoin(
                'part_event',
                ManufacturePartProduct::class,
                'part_product',
                'part_product.event = part_event.id'
            );


        $dbal
            ->addSelect('product_event.id AS product_event')
            ->leftJoin(
                'part_product',
                ProductEvent::class,
                'product_event',
                'product_event.id = part_product.product'
            );

        $dbal
            ->addSelect('product_trans.name AS product_name')
            ->leftJoin(
                'product_event',
                ProductTrans::class,
                'product_trans',
                'product_trans.event = product_event.id AND product_trans.local = :local'
            );

        /* Торговое предложение */

        $dbal
            ->addSelect('product_offer.id as product_offer_uid')
            ->addSelect('product_offer.value as product_offer_value')
            ->addSelect('product_offer.postfix as product_offer_postfix')
            ->leftJoin(
                'part_product',
                ProductOffer::class,
                'product_offer',
                'product_offer.id = part_product.offer OR product_offer.id IS NULL'
            );

        /* Получаем тип торгового предложения */
        $dbal
            ->addSelect('category_offer.reference AS product_offer_reference')
            ->leftJoin(
                'product_offer',
                CategoryProductOffers::class,
                'category_offer',
                'category_offer.id = product_offer.category_offer'
            );

        /* Получаем название торгового предложения */
        $dbal
            ->addSelect('category_offer_trans.name as product_offer_name')
            ->addSelect('category_offer_trans.postfix as product_offer_name_postfix')
            ->leftJoin(
                'category_offer',
                CategoryProductOffersTrans::class,
                'category_offer_trans',
                'category_offer_trans.offer = category_offer.id AND category_offer_trans.local = :local'
            );


        /* Множественные варианты торгового предложения */

        $dbal
            ->addSelect('product_variation.id as product_variation_uid')
            ->addSelect('product_variation.value as product_variation_value')
            ->addSelect('product_variation.postfix as product_variation_postfix')
            ->leftJoin(
                'part_product',
                ProductVariation::class,
                'product_variation',
                'product_variation.id = part_product.variation OR product_variation.id IS NULL '
            );


        /* Получаем тип множественного варианта */
        $dbal
            ->addSelect('category_variation.reference as product_variation_reference')
            ->leftJoin(
                'product_variation',
                CategoryProductVariation::class,
                'category_variation',
                'category_variation.id = product_variation.category_variation'
            );

        /* Получаем название множественного варианта */
        $dbal
            ->addSelect('category_variation_trans.name as product_variation_name')
            ->addSelect('category_variation_trans.postfix as product_variation_name_postfix')
            ->leftJoin(
                'category_variation',
                CategoryProductVariationTrans::class,
                'category_variation_trans',
                'category_variation_trans.variation = category_variation.id AND category_variation_trans.local = :local'
            );


        /* Модификация множественного варианта торгового предложения */

        $dbal
            ->addSelect('product_modification.id as product_modification_uid')
            ->addSelect('product_modification.value as product_modification_value')
            ->addSelect('product_modification.postfix as product_modification_postfix')
            ->leftJoin(
                'part_product',
                ProductModification::class,
                'product_modification',
                'product_modification.id = part_product.modification OR product_modification.id IS NULL '
            );


        /* Получаем тип модификации множественного варианта */
        $dbal
            ->addSelect('category_modification.reference as product_modification_reference')
            ->leftJoin(
                'product_modification',
                CategoryProductModification::class,
                'category_modification',
                'category_modification.id = product_modification.category_modification'
            );

        /* Получаем название типа модификации */
        $dbal
            ->addSelect('category_modification_trans.name as product_modification_name')
            ->addSelect('category_modification_trans.postfix as product_modification_name_postfix')
            ->leftJoin(
                'category_modification',
                CategoryProductModificationTrans::class,
                'category_modification_trans',
                'category_modification_trans.modification = category_modification.id AND category_modification_trans.local = :local'
            );


        /* Фото продукта */

        $dbal->leftJoin(
            'product_event',
            ProductPhoto::class,
            'product_photo',
            'product_photo.event = product_event.id AND product_photo.root = true'
        );

        $dbal->leftJoin(
            'product_offer',
            ProductModificationImage::class,
            'product_modification_image',
            'product_modification_image.modification = product_modification.id AND product_modification_image.root = true'
        );

        $dbal->leftJoin(
            'product_offer',
            ProductVariationImage::class,
            'product_variation_image',
            'product_variation_image.variation = product_variation.id AND product_variation_image.root = true'
        );


        $dbal->leftJoin(
            'product_offer',
            ProductOfferImage::class,
            'product_offer_images',
            'product_offer_images.offer = product_offer.id AND product_offer_images.root = true'
        );

        $dbal->addSelect(
            "
			CASE
				WHEN product_modification_image.name IS NOT NULL THEN
					CONCAT ( '/upload/".$dbal->table(ProductModificationImage::class)."' , '/', product_modification_image.name)
			   WHEN product_variation_image.name IS NOT NULL THEN
					CONCAT ( '/upload/".$dbal->table(ProductVariationImage::class)."' , '/', product_variation_image.name)
			   WHEN product_offer_images.name IS NOT NULL THEN
					CONCAT ( '/upload/".$dbal->table(ProductOfferImage::class)."' , '/', product_offer_images.name)
			   WHEN product_photo.name IS NOT NULL THEN
					CONCAT ( '/upload/".$dbal->table(ProductPhoto::class)."' , '/', product_photo.name)
			   ELSE NULL
			END AS product_image
		"
        );

        /* Флаг загрузки файла CDN */
        $dbal->addSelect('
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
        $dbal->addSelect('
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

        //        $dbal->leftJoin(
        //            'part_event',
        //            UsersTableActions::class,
        //            'actions',
        //            'actions.event = part_event.action '
        //        );

        $dbal
            ->addSelect('actions_event.id AS actions_event')
            ->leftJoin(
                'part_event',
                UsersTableActionsEvent::class,
                'actions_event',
                'actions_event.id = part_event.action'
            );

        $dbal
            ->addSelect('actions_trans.name AS actions_name')
            ->leftJoin(
                'part_event',
                UsersTableActionsTrans::class,
                'actions_trans',
                'actions_trans.event = actions_event.id AND actions_trans.local = :local'
            );

        $dbal
            ->addSelect('category.id AS category_id')
            ->leftJoin(
                'actions_event',
                CategoryProduct::class,
                'category',
                'category.id = actions_event.category'
            );

        $dbal
            ->addSelect('trans.name AS category_name')
            ->leftJoin(
                'category',
                CategoryProductTrans::class,
                'trans',
                'trans.event = category.event AND trans.local = :local'
            );

        return $dbal
            ->enableCache('manufacture-part', 86400)
            //->fetchAllAssociative();
            ->fetchAssociative();
    }
}