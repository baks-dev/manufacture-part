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
use BaksDev\Products\Category\Entity\Offers\ProductCategoryOffers;
use BaksDev\Products\Category\Entity\Offers\Variation\Modification\ProductCategoryModification;
use BaksDev\Products\Category\Entity\Offers\Variation\ProductCategoryVariation;
use BaksDev\Products\Category\Entity\Trans\ProductCategoryTrans;
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


final class ProductsByManufacturePart implements ProductsByManufacturePartInterface
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
        $qb = $this->DBALQueryBuilder->createQueryBuilder(self::class);
        $qb->bindLocal();

        $qb->select('part.id');
        $qb->addSelect('part.event');
        $qb->from(ManufacturePart::TABLE, 'part');
        $qb->where('part.id = :part');
        $qb->setParameter('part', $part, ManufacturePartUid::TYPE);

        $qb->leftJoin(
            'part',
            ManufacturePartEvent::TABLE,
            'part_event',
            'part_event.id = part.event'
        );


        /** Партии других пользователей */
        if($authority)
        {
            /** Профили доверенных пользователей */
            $qb->leftJoin(
                'part',
                ProfileGroupUsers::TABLE,
                'profile_group_users',
                'profile_group_users.authority = :authority '.($other ? '' : ' AND profile_group_users.profile = :profile')
            );

            $qb
                ->andWhere('part_event.profile = profile_group_users.profile')
                ->setParameter('authority', $authority, UserProfileUid::TYPE)
                ->setParameter('profile', $profile, UserProfileUid::TYPE)
            ;
        }
        else
        {
            $qb
                ->andWhere('part_event.profile = :profile')
                ->setParameter('profile', $profile, UserProfileUid::TYPE);
        }
        

        $qb->addSelect('part_product.id AS product_id');
        $qb->addSelect('part_product.total AS product_total');
        $qb->leftJoin(
            'part',
            ManufacturePartProduct::TABLE,
            'part_product',
            'part_product.event = part.event'
        );


        $qb->join(
            'part_product',
            ProductEvent::TABLE,
            'product_event',
            'product_event.id = part_product.product'
        );

        $qb->addSelect('product_info.url');

        $qb->leftJoin(
            'product_event',
            ProductInfo::TABLE,
            'product_info',
            'product_info.product = product_event.main'
        );



        /** Ответственное лицо (Профиль пользователя) */
        $qb->leftJoin(
            'product_info',
            UserProfile::TABLE,
            'users_profile',
            'users_profile.id = product_info.profile'
        );

        $qb->addSelect('users_profile_personal.username AS users_profile_username');
        $qb->leftJoin(
            'users_profile',
            UserProfilePersonal::TABLE,
            'users_profile_personal',
            'users_profile_personal.event = users_profile.event'
        );






        $qb->addSelect('product_trans.name AS product_name');
        //$qb->addSelect('product_trans.preview AS product_preview');
        $qb->leftJoin(
            'product_event',
            ProductTrans::TABLE,
            'product_trans',
            'product_trans.event = product_event.id AND product_trans.local = :local'
        );

        /**
         * Торговое предложение
         */

        $qb->addSelect('product_offer.id as product_offer_id');
        $qb->addSelect('product_offer.value as product_offer_value');
        $qb->addSelect('product_offer.postfix as product_offer_postfix');

        $qb->leftJoin(
            'product_event',
            ProductOffer::TABLE,
            'product_offer',
            'product_offer.id = part_product.offer OR product_offer.id IS NULL'
        );

        if($filter->getOffer())
        {
            $qb->andWhere('product_offer.value = :offer');
            $qb->setParameter('offer', $filter->getOffer());
        }


        /* Тип торгового предложения */
        $qb->addSelect('category_offer.reference as product_offer_reference');
        $qb->leftJoin(
            'product_offer',
            ProductCategoryOffers::TABLE,
            'category_offer',
            'category_offer.id = product_offer.category_offer'
        );


        /**
         * Множественные варианты торгового предложения
         */

        $qb->addSelect('product_variation.id as product_variation_id');
        $qb->addSelect('product_variation.value as product_variation_value');
        $qb->addSelect('product_variation.postfix as product_variation_postfix');

        $qb->leftJoin(
            'product_offer',
            ProductVariation::TABLE,
            'product_variation',
            'product_variation.id = part_product.variation OR product_variation.id IS NULL'
        );


        if($filter->getVariation())
        {
            $qb->andWhere('product_variation.value = :variation');
            $qb->setParameter('variation', $filter->getVariation());
        }


        /* Тип множественного варианта торгового предложения */
        $qb->addSelect('category_variation.reference as product_variation_reference');
        $qb->leftJoin(
            'product_variation',
            ProductCategoryVariation::TABLE,
            'category_variation',
            'category_variation.id = product_variation.category_variation'
        );


        /**
         * Модификация множественного варианта
         */

        $qb->addSelect('product_modification.value as product_modification_id');
        $qb->addSelect('product_modification.value as product_modification_value');
        $qb->addSelect('product_modification.postfix as product_modification_postfix');

        $qb->leftJoin(
            'part_product',
            ProductModification::TABLE,
            'product_modification',
            'product_modification.id = part_product.modification OR product_modification.id IS NULL'
        );

        if($filter->getModification())
        {
            $qb->andWhere('product_modification.value = :modification');
            $qb->setParameter('modification', $filter->getModification());
        }


        /** Получаем тип модификации множественного варианта */
        $qb->addSelect('category_modification.reference as product_modification_reference');
        $qb->leftJoin(
            'product_modification',
            ProductCategoryModification::TABLE,
            'category_modification',
            'category_modification.id = product_modification.category_modification'
        );


        /** Артикул продукта */

        $qb->addSelect("
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

        $qb->leftJoin(
            'product_event',
            ProductPhoto::TABLE,
            'product_photo',
            'product_photo.event = product_event.id AND product_photo.root = true'
        );

        $qb->leftJoin(
            'product_modification',
            ProductModificationImage::TABLE,
            'product_modification_image',
            'product_modification_image.modification = product_modification.id AND product_modification_image.root = true'
        );

        $qb->leftJoin(
            'product_variation',
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

        $qb->addSelect("
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
        $qb->addSelect("
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
        $qb->addSelect("
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
        $qb->join(
            'product_event',
            ProductCategory::TABLE,
            'product_event_category',
            'product_event_category.event = product_event.id AND product_event_category.root = true'
        );

        //        if($filter->getCategory())
        //        {
        //            $qb->andWhere('product_event_category.category = :category');
        //            $qb->setParameter('category', $filter->getCategory(), ProductCategoryUid::TYPE);
        //        }

        $qb->join(
            'product_event_category',
            \BaksDev\Products\Category\Entity\ProductCategory::TABLE,
            'category',
            'category.id = product_event_category.category'
        );

        $qb->addSelect('category_trans.name AS category_name');

        $qb->leftJoin(
            'category',
            ProductCategoryTrans::TABLE,
            'category_trans',
            'category_trans.event = category.event AND category_trans.local = :local'
        );


        if($search->getQuery())
        {

            $qb
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

        $qb->orderBy('part_product.id', 'DESC');

        return $this->paginator
            ->fetchAllAssociative($qb->enableCache('manufacture-part', 3600));
    }



    /** Метод возвращает список продукции в производственной партии */
    public function getAllProductsByManufacturePart(ManufacturePartUid $part): ?array
    {
        $qb = $this->DBALQueryBuilder->createQueryBuilder(self::class);
        $qb->bindLocal();

//        $qb->select('part.id');
//        $qb->addSelect('part.event');

        $qb->from(ManufacturePart::TABLE, 'part');
        $qb->where('part.id = :part');
        $qb->setParameter('part', $part, ManufacturePartUid::TYPE);

        $qb->leftJoin(
            'part',
            ManufacturePartEvent::TABLE,
            'part_event',
            'part_event.id = part.event'
        );



        $qb->addSelect('part_product.total AS product_total');
        $qb->leftJoin(
            'part',
            ManufacturePartProduct::TABLE,
            'part_product',
            'part_product.event = part.event'
        );

        $qb
            ->addSelect('product_event.id AS product_event')
            ->join(
            'part_product',
            ProductEvent::TABLE,
            'product_event',
            'product_event.id = part_product.product'
        );

        $qb->leftJoin(
            'product_event',
            ProductInfo::TABLE,
            'product_info',
            'product_info.product = product_event.main'
        );

        $qb->addSelect('product_trans.name AS product_name');
        $qb->leftJoin(
            'product_event',
            ProductTrans::TABLE,
            'product_trans',
            'product_trans.event = product_event.id AND product_trans.local = :local'
        );

        /**
         * Торговое предложение
         */

        $qb->addSelect('product_offer.id as product_offer_id');
        $qb->addSelect('product_offer.value as product_offer_value');
        $qb->addSelect('product_offer.postfix as product_offer_postfix');

        $qb->leftJoin(
            'product_event',
            ProductOffer::TABLE,
            'product_offer',
            'product_offer.id = part_product.offer OR product_offer.id IS NULL'
        );

        /* Тип торгового предложения */
        $qb->addSelect('category_offer.reference as product_offer_reference');
        $qb->leftJoin(
            'product_offer',
            ProductCategoryOffers::TABLE,
            'category_offer',
            'category_offer.id = product_offer.category_offer'
        );


        /**
         * Множественные варианты торгового предложения
         */

        $qb->addSelect('product_variation.id as product_variation_id');
        $qb->addSelect('product_variation.value as product_variation_value');
        $qb->addSelect('product_variation.postfix as product_variation_postfix');

        $qb->leftJoin(
            'product_offer',
            ProductVariation::TABLE,
            'product_variation',
            'product_variation.id = part_product.variation OR product_variation.id IS NULL'
        );

        /* Тип множественного варианта торгового предложения */
        $qb->addSelect('category_variation.reference as product_variation_reference');
        $qb->leftJoin(
            'product_variation',
            ProductCategoryVariation::TABLE,
            'category_variation',
            'category_variation.id = product_variation.category_variation'
        );


        /**
         * Модификация множественного варианта
         */

        $qb->addSelect('product_modification.value as product_modification_id');
        $qb->addSelect('product_modification.value as product_modification_value');
        $qb->addSelect('product_modification.postfix as product_modification_postfix');

        $qb->leftJoin(
            'part_product',
            ProductModification::TABLE,
            'product_modification',
            'product_modification.id = part_product.modification OR product_modification.id IS NULL'
        );

        /** Получаем тип модификации множественного варианта */
        $qb->addSelect('category_modification.reference as product_modification_reference');
        $qb->leftJoin(
            'product_modification',
            ProductCategoryModification::TABLE,
            'category_modification',
            'category_modification.id = product_modification.category_modification'
        );


        /** Артикул продукта */
        $qb->addSelect("
					CASE
					   WHEN product_modification.article IS NOT NULL THEN product_modification.article
					   WHEN product_variation.article IS NOT NULL THEN product_variation.article
					   WHEN product_offer.article IS NOT NULL THEN product_offer.article
					   WHEN product_info.article IS NOT NULL THEN product_info.article
					   ELSE NULL
					END AS product_article
				"
        );


        $qb->orderBy('part_product.id', 'DESC');

        return $qb->enableCache('manufacture-part', 3600)->fetchAllAssociative();
    }

}
