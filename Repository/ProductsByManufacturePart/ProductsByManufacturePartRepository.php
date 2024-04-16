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

namespace BaksDev\Manufacture\Part\Repository\ProductsByManufacturePart;


use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Core\Form\Search\SearchDTO;
use BaksDev\Core\Services\Paginator\PaginatorInterface;
use BaksDev\Manufacture\Part\Entity\Event\ManufacturePartEvent;
use BaksDev\Manufacture\Part\Entity\ManufacturePart;
use BaksDev\Manufacture\Part\Entity\Products\ManufacturePartProduct;
use BaksDev\Manufacture\Part\Forms\PartProductFilter\PartProductFilterInterface;
use BaksDev\Manufacture\Part\Type\Id\ManufacturePartUid;
use BaksDev\Products\Category\Entity\CategoryProduct;
use BaksDev\Products\Category\Entity\Offers\CategoryProductOffers;
use BaksDev\Products\Category\Entity\Offers\Variation\Modification\CategoryProductModification;
use BaksDev\Products\Category\Entity\Offers\Variation\CategoryProductVariation;
use BaksDev\Products\Category\Entity\Trans\CategoryProductTrans;
use BaksDev\Products\Product\Entity\Category\ProductCategory;
use BaksDev\Products\Product\Entity\Event\ProductEvent;
use BaksDev\Products\Product\Entity\Info\ProductInfo;
use BaksDev\Products\Product\Entity\Offers\Image\ProductOfferImage;
use BaksDev\Products\Product\Entity\Offers\ProductOffer;
use BaksDev\Products\Product\Entity\Offers\Variation\Image\ProductVariationImage;
use BaksDev\Products\Product\Entity\Offers\Variation\Modification\Image\ProductModificationImage;
use BaksDev\Products\Product\Entity\Offers\Variation\Modification\ProductModification;
use BaksDev\Products\Product\Entity\Offers\Variation\ProductVariation;
use BaksDev\Products\Product\Entity\Photo\ProductPhoto;
use BaksDev\Products\Product\Entity\Trans\ProductTrans;
use BaksDev\Users\Profile\Group\Entity\Users\ProfileGroupUsers;
use BaksDev\Users\Profile\UserProfile\Entity\Personal\UserProfilePersonal;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;


final class ProductsByManufacturePartRepository implements ProductsByManufacturePartInterface
{
    private PaginatorInterface $paginator;
    private DBALQueryBuilder $DBALQueryBuilder;

    public function __construct(
        DBALQueryBuilder $DBALQueryBuilder,
        PaginatorInterface $paginator,
    )
    {
        $this->paginator = $paginator;
        $this->DBALQueryBuilder = $DBALQueryBuilder;
    }

    /** Метод возвращает пагинатор ManufacturePart */
    public function fetchAllProductsByManufacturePartAssociative(
        SearchDTO $search,
        PartProductFilterInterface $filter,
        ManufacturePartUid $part,
        UserProfileUid $profile,
        ?UserProfileUid $authority,
        $other
    ): PaginatorInterface
    {
        $dbal = $this->DBALQueryBuilder
            ->createQueryBuilder(self::class)
            ->bindLocal();

        $dbal
            ->select('part.id')
            ->addSelect('part.event')
            ->from(ManufacturePart::TABLE, 'part')
            ->where('part.id = :part')
            ->setParameter('part', $part, ManufacturePartUid::TYPE);


        /** Партии доверенных профилей */
        if($authority)
        {
            $dbal->leftJoin(
                'part',
                ProfileGroupUsers::TABLE,
                'profile_group_users',
                'profile_group_users.authority = :authority'
            )
                ->setParameter('authority', $authority, UserProfileUid::TYPE);

            $dbal->join(
                'part',
                ManufacturePartEvent::TABLE,
                'part_event',
                'part_event.id = part.event AND (part_event.profile = profile_group_users.profile OR part_event.profile = :profile)'
            );
        }
        else
        {
            $dbal->join(
                'part',
                ManufacturePartEvent::TABLE,
                'part_event',
                'part_event.id = part.event AND part_event.profile = :profile'
            );
        }

        $dbal
            ->setParameter('profile', $profile, UserProfileUid::TYPE);


        $dbal
            ->addSelect('part_product.id AS product_id')
            ->addSelect('part_product.total AS product_total')
            ->leftJoin(
                'part',
                ManufacturePartProduct::TABLE,
                'part_product',
                'part_product.event = part.event'
            );


        $dbal->join(
            'part_product',
            ProductEvent::TABLE,
            'product_event',
            'product_event.id = part_product.product'
        );

        $dbal
            ->addSelect('product_info.url')
            ->leftJoin(
                'product_event',
                ProductInfo::TABLE,
                'product_info',
                'product_info.product = product_event.main'
            );


        /** Ответственное лицо (Профиль пользователя) */
        $dbal->leftJoin(
            'product_info',
            UserProfile::TABLE,
            'users_profile',
            'users_profile.id = product_info.profile'
        );

        $dbal
            ->addSelect('users_profile_personal.username AS users_profile_username')
            ->leftJoin(
                'users_profile',
                UserProfilePersonal::TABLE,
                'users_profile_personal',
                'users_profile_personal.event = users_profile.event'
            );


        $dbal
            ->addSelect('product_trans.name AS product_name')
            ->leftJoin(
                'product_event',
                ProductTrans::TABLE,
                'product_trans',
                'product_trans.event = product_event.id AND product_trans.local = :local'
            );

        /**
         * Торговое предложение
         */

        $dbal
            ->addSelect('product_offer.id as product_offer_id')
            ->addSelect('product_offer.value as product_offer_value')
            ->addSelect('product_offer.postfix as product_offer_postfix')
            ->leftJoin(
                'product_event',
                ProductOffer::TABLE,
                'product_offer',
                'product_offer.id = part_product.offer OR product_offer.id IS NULL'
            );

        if($filter->getOffer())
        {
            $dbal->andWhere('product_offer.value = :offer');
            $dbal->setParameter('offer', $filter->getOffer());
        }


        /* Тип торгового предложения */
        $dbal
            ->addSelect('category_offer.reference as product_offer_reference')
            ->leftJoin(
                'product_offer',
                CategoryProductOffers::TABLE,
                'category_offer',
                'category_offer.id = product_offer.category_offer'
            );


        /**
         * Множественные варианты торгового предложения
         */

        $dbal
            ->addSelect('product_variation.id as product_variation_id')
            ->addSelect('product_variation.value as product_variation_value')
            ->addSelect('product_variation.postfix as product_variation_postfix')
            ->leftJoin(
                'product_offer',
                ProductVariation::TABLE,
                'product_variation',
                'product_variation.id = part_product.variation OR product_variation.id IS NULL'
            );


        if($filter->getVariation())
        {
            $dbal->andWhere('product_variation.value = :variation');
            $dbal->setParameter('variation', $filter->getVariation());
        }


        /* Тип множественного варианта торгового предложения */
        $dbal
            ->addSelect('category_variation.reference as product_variation_reference')
            ->leftJoin(
                'product_variation',
                CategoryProductVariation::TABLE,
                'category_variation',
                'category_variation.id = product_variation.category_variation'
            );


        /**
         * Модификация множественного варианта
         */

        $dbal
            ->addSelect('product_modification.value as product_modification_id')
            ->addSelect('product_modification.value as product_modification_value')
            ->addSelect('product_modification.postfix as product_modification_postfix')
            ->leftJoin(
                'part_product',
                ProductModification::TABLE,
                'product_modification',
                'product_modification.id = part_product.modification OR product_modification.id IS NULL'
            );

        if($filter->getModification())
        {
            $dbal->andWhere('product_modification.value = :modification');
            $dbal->setParameter('modification', $filter->getModification());
        }


        /** Получаем тип модификации множественного варианта */
        $dbal
            ->addSelect('category_modification.reference as product_modification_reference')
            ->leftJoin(
                'product_modification',
                CategoryProductModification::TABLE,
                'category_modification',
                'category_modification.id = product_modification.category_modification'
            );


        /** Артикул продукта */

        $dbal->addSelect("
					CASE
					   WHEN product_modification.article IS NOT NULL THEN product_modification.article
					   WHEN product_variation.article IS NOT NULL THEN product_variation.article
					   WHEN product_offer.article IS NOT NULL THEN product_offer.article
					   WHEN product_info.article IS NOT NULL THEN product_info.article
					   ELSE NULL
					END AS product_article
				"
        );


        /** Фото продукта */

        $dbal->leftJoin(
            'product_event',
            ProductPhoto::TABLE,
            'product_photo',
            'product_photo.event = product_event.id AND product_photo.root = true'
        );

        $dbal->leftJoin(
            'product_modification',
            ProductModificationImage::TABLE,
            'product_modification_image',
            'product_modification_image.modification = product_modification.id AND product_modification_image.root = true'
        );

        $dbal->leftJoin(
            'product_variation',
            ProductVariationImage::TABLE,
            'product_variation_image',
            'product_variation_image.variation = product_variation.id AND product_variation_image.root = true'
        );

        $dbal->leftJoin(
            'product_offer',
            ProductOfferImage::TABLE,
            'product_offer_images',
            'product_offer_images.offer = product_offer.id AND product_offer_images.root = true'
        );

        $dbal->addSelect("
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

        /** Флаг загрузки файла CDN */
        $dbal->addSelect("
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
		");

        /** Флаг загрузки файла CDN */
        $dbal->addSelect("
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
		");


        /* Категория */
        $dbal->join(
            'product_event',
            ProductCategory::class,
            'product_event_category',
            'product_event_category.event = product_event.id AND product_event_category.root = true'
        );


        $dbal->join(
            'product_event_category',
            CategoryProduct::class,
            'category',
            'category.id = product_event_category.category'
        );

        $dbal
            ->addSelect('category_trans.name AS category_name')
            ->leftJoin(
                'category',
                CategoryProductTrans::class,
                'category_trans',
                'category_trans.event = category.event AND category_trans.local = :local'
            );


        if($search->getQuery())
        {

            $dbal
                ->createSearchQueryBuilder($search)
                ->addSearchEqualUid('product.id')
                ->addSearchEqualUid('product.event')
                ->addSearchEqualUid('product_variation.id')
                ->addSearchEqualUid('product_modification.id')
                ->addSearchLike('product_trans.name')
                //->addSearchLike('product_trans.preview')
                ->addSearchLike('product_info.article')
                ->addSearchLike('product_offer.article')
                ->addSearchLike('product_modification.article')
                ->addSearchLike('product_modification.article')
                ->addSearchLike('product_variation.article');

        }

        $dbal->orderBy('part_product.id', 'DESC');

        return $this->paginator
            ->fetchAllAssociative($dbal->enableCache('manufacture-part', 3600));
    }


    /** Метод возвращает список продукции в производственной партии */
    public function getAllProductsByManufacturePart(ManufacturePartUid $part): ?array
    {
        $dbal = $this->DBALQueryBuilder->createQueryBuilder(self::class);
        $dbal->bindLocal();

        //        $dbal->select('part.id');
        //        $dbal->addSelect('part.event');

        $dbal
            ->from(ManufacturePart::TABLE, 'part')
            ->where('part.id = :part')
            ->setParameter('part', $part, ManufacturePartUid::TYPE);

        $dbal->leftJoin(
            'part',
            ManufacturePartEvent::TABLE,
            'part_event',
            'part_event.id = part.event'
        );


        $dbal
            ->addSelect('part_product.total AS product_total')
            ->leftJoin(
                'part',
                ManufacturePartProduct::TABLE,
                'part_product',
                'part_product.event = part.event'
            );

        $dbal
            ->addSelect('product_event.id AS product_event')
            ->join(
                'part_product',
                ProductEvent::TABLE,
                'product_event',
                'product_event.id = part_product.product'
            );

        $dbal->leftJoin(
            'product_event',
            ProductInfo::TABLE,
            'product_info',
            'product_info.product = product_event.main'
        );

        $dbal
            ->addSelect('product_trans.name AS product_name')
            ->leftJoin(
                'product_event',
                ProductTrans::TABLE,
                'product_trans',
                'product_trans.event = product_event.id AND product_trans.local = :local'
            );

        /**
         * Торговое предложение
         */

        $dbal
            ->addSelect('product_offer.id as product_offer_id')
            ->addSelect('product_offer.value as product_offer_value')
            ->addSelect('product_offer.postfix as product_offer_postfix')
            ->leftJoin(
                'product_event',
                ProductOffer::TABLE,
                'product_offer',
                'product_offer.id = part_product.offer OR product_offer.id IS NULL'
            );

        /* Тип торгового предложения */
        $dbal
            ->addSelect('category_offer.reference as product_offer_reference')
            ->leftJoin(
                'product_offer',
                CategoryProductOffers::TABLE,
                'category_offer',
                'category_offer.id = product_offer.category_offer'
            );


        /**
         * Множественные варианты торгового предложения
         */

        $dbal
            ->addSelect('product_variation.id as product_variation_id')
            ->addSelect('product_variation.value as product_variation_value')
            ->addSelect('product_variation.postfix as product_variation_postfix')
            ->leftJoin(
                'product_offer',
                ProductVariation::TABLE,
                'product_variation',
                'product_variation.id = part_product.variation OR product_variation.id IS NULL'
            );

        /* Тип множественного варианта торгового предложения */
        $dbal
            ->addSelect('category_variation.reference as product_variation_reference')
            ->leftJoin(
                'product_variation',
                CategoryProductVariation::TABLE,
                'category_variation',
                'category_variation.id = product_variation.category_variation'
            );


        /**
         * Модификация множественного варианта
         */

        $dbal
            ->addSelect('product_modification.value as product_modification_id')
            ->addSelect('product_modification.value as product_modification_value')
            ->addSelect('product_modification.postfix as product_modification_postfix')
            ->leftJoin(
                'part_product',
                ProductModification::TABLE,
                'product_modification',
                'product_modification.id = part_product.modification OR product_modification.id IS NULL'
            );

        /** Получаем тип модификации множественного варианта */
        $dbal
            ->addSelect('category_modification.reference as product_modification_reference')
            ->leftJoin(
                'product_modification',
                CategoryProductModification::TABLE,
                'category_modification',
                'category_modification.id = product_modification.category_modification'
            );


        /** Артикул продукта */
        $dbal->addSelect("
					CASE
					   WHEN product_modification.article IS NOT NULL THEN product_modification.article
					   WHEN product_variation.article IS NOT NULL THEN product_variation.article
					   WHEN product_offer.article IS NOT NULL THEN product_offer.article
					   WHEN product_info.article IS NOT NULL THEN product_info.article
					   ELSE NULL
					END AS product_article
				"
        );


        $dbal->orderBy('part_product.id', 'DESC');

        return $dbal->enableCache('manufacture-part', 3600)->fetchAllAssociative();
    }

}
