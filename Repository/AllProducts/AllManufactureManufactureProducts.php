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

namespace BaksDev\Manufacture\Part\Repository\AllProducts;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Core\Form\Search\SearchDTO;
use BaksDev\Core\Services\Paginator\PaginatorInterface;
use BaksDev\Manufacture\Part\Entity\Event\ManufacturePartEvent;
use BaksDev\Manufacture\Part\Entity\ManufacturePart;
use BaksDev\Manufacture\Part\Entity\Products\ManufacturePartProduct;
use BaksDev\Manufacture\Part\Type\Complete\ManufacturePartComplete;
use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatus\ManufacturePartStatusClosed;
use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatus\ManufacturePartStatusCompleted;
use BaksDev\Products\Category\Entity as CategoryEntity;
use BaksDev\Products\Category\Type\Id\ProductCategoryUid;
use BaksDev\Products\Product\Entity;
use BaksDev\Products\Product\Forms\ProductFilter\ProductFilterInterface;
use BaksDev\Users\Profile\UserProfile\Entity\Personal\UserProfilePersonal;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;

final class AllManufactureManufactureProducts implements AllManufactureProductsInterface
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

    /**
     * Метод возвращает все товары, которые не участвуют в производстве
     */
    public function getAllManufactureProducts(
        SearchDTO $search,
        ?UserProfileUid $profile,
        ProductFilterInterface $filter,
        ?ManufacturePartComplete $complete = null
    ): PaginatorInterface
    {

        $qb = $this->DBALQueryBuilder->createQueryBuilder(self::class);
        $qb->bindLocal();

        $qb->select('product.id');
        $qb->addSelect('product.event');

        $qb->from(Entity\Product::TABLE, 'product');

        $qb->join('product', Entity\Event\ProductEvent::TABLE, 'product_event', 'product_event.id = product.event');

        $qb->addSelect('product_trans.name AS product_name');
        //$qb->addSelect('product_trans.preview AS product_preview');
        $qb->leftJoin(
            'product_event',
            Entity\Trans\ProductTrans::TABLE,
            'product_trans',
            'product_trans.event = product_event.id AND product_trans.local = :local'
        );


        if($profile)
        {
            $qb->andWhere('product_info.profile = :profile');
            $qb->setParameter('profile', $profile, UserProfileUid::TYPE);
        }

        /* ProductInfo */

        $qb->addSelect('product_info.url');

        $qb->leftJoin(
            'product_event',
            Entity\Info\ProductInfo::TABLE,
            'product_info',
            'product_info.product = product.id'
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


        /** Торговое предложение */

        $qb->addSelect('product_offer.id as product_offer_id');
        $qb->addSelect('product_offer.value as product_offer_value');
        $qb->addSelect('product_offer.postfix as product_offer_postfix');

        $qb->leftJoin(
            'product_event',
            Entity\Offers\ProductOffer::TABLE,
            'product_offer',
            'product_offer.event = product_event.id'
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
            CategoryEntity\Offers\ProductCategoryOffers::TABLE,
            'category_offer',
            'category_offer.id = product_offer.category_offer'
        );


        /** Множественные варианты торгового предложения */

        $qb->addSelect('product_variation.id as product_variation_id');
        $qb->addSelect('product_variation.value as product_variation_value');
        $qb->addSelect('product_variation.postfix as product_variation_postfix');

        $qb->leftJoin(
            'product_offer',
            Entity\Offers\Variation\ProductVariation::TABLE,
            'product_variation',
            'product_variation.offer = product_offer.id'
        );


        if($filter->getVariation())
        {
            $qb->andWhere('product_variation.value = :variation');
            $qb->setParameter('variation', $filter->getVariation());
        }


        /* Тип множественного варианта торгового предложения */
        $qb->addSelect('category_offer_variation.reference as product_variation_reference');
        $qb->leftJoin(
            'product_variation',
            CategoryEntity\Offers\Variation\ProductCategoryVariation::TABLE,
            'category_offer_variation',
            'category_offer_variation.id = product_variation.category_variation'
        );


        /** Модификация множественного варианта */
        $qb->addSelect('product_modification.id as product_modification_id');
        $qb->addSelect('product_modification.value as product_modification_value');
        $qb->addSelect('product_modification.postfix as product_modification_postfix');

        $qb->leftJoin(
            'product_variation',
            Entity\Offers\Variation\Modification\ProductModification::TABLE,
            'product_modification',
            'product_modification.variation = product_variation.id '
        );


        /** Получаем тип модификации множественного варианта */
        $qb->addSelect('category_offer_modification.reference as product_modification_reference');
        $qb->leftJoin(
            'product_modification',
            CategoryEntity\Offers\Variation\Modification\ProductCategoryModification::TABLE,
            'category_offer_modification',
            'category_offer_modification.id = product_modification.category_modification'
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
            Entity\Photo\ProductPhoto::TABLE,
            'product_photo',
            'product_photo.event = product_event.id AND product_photo.root = true'
        );

        $qb->leftJoin(
            'product_offer',
            Entity\Offers\Variation\Image\ProductVariationImage::TABLE,
            'product_offer_variation_image',
            'product_offer_variation_image.variation = product_variation.id AND product_offer_variation_image.root = true'
        );

        $qb->leftJoin(
            'product_offer',
            Entity\Offers\Image\ProductOfferImage::TABLE,
            'product_offer_images',
            'product_offer_images.offer = product_offer.id AND product_offer_images.root = true'
        );

        $qb->addSelect("
			CASE
			   WHEN product_offer_variation_image.name IS NOT NULL THEN
					CONCAT ( '/upload/".Entity\Offers\Variation\Image\ProductVariationImage::TABLE."' , '/', product_offer_variation_image.name)
			   WHEN product_offer_images.name IS NOT NULL THEN
					CONCAT ( '/upload/".Entity\Offers\Image\ProductOfferImage::TABLE."' , '/', product_offer_images.name)
			   WHEN product_photo.name IS NOT NULL THEN
					CONCAT ( '/upload/".Entity\Photo\ProductPhoto::TABLE."' , '/', product_photo.name)
			   ELSE NULL
			END AS product_image
		"
        );

        /** Флаг загрузки файла CDN */
        $qb->addSelect("
			CASE
			   WHEN product_offer_variation_image.name IS NOT NULL THEN
					product_offer_variation_image.ext
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
			   WHEN product_offer_variation_image.name IS NOT NULL THEN
					product_offer_variation_image.cdn
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
            Entity\Category\ProductCategory::TABLE,
            'product_event_category',
            'product_event_category.event = product_event.id AND product_event_category.root = true'
        );

        if($filter->getCategory())
        {
            $qb->andWhere('product_event_category.category = :category');
            $qb->setParameter('category', $filter->getCategory(), ProductCategoryUid::TYPE);
        }

        $qb->join(
            'product_event_category',
            CategoryEntity\ProductCategory::TABLE,
            'category',
            'category.id = product_event_category.category'
        );

        $qb->addSelect('category_trans.name AS category_name');

        $qb->leftJoin(
            'category',
            CategoryEntity\Trans\ProductCategoryTrans::TABLE,
            'category_trans',
            'category_trans.event = category.event AND category_trans.local = :local'
        );


        if($complete) {

            /** Только товары, которых нет в производстве */

            $qbExist = $this->DBALQueryBuilder->createQueryBuilder(self::class);
            $qbExist->select('exist_part.number');

            $qbExist->from(ManufacturePartProduct::TABLE, 'exist_product');

            $qbExist->join('exist_product',
                ManufacturePart::TABLE,
                'exist_part',
                '
                exist_part.event = exist_product.event
            '
            );

            $qbExist->join('exist_part',
                ManufacturePartEvent::TABLE,
                'exist_product_event',
                '
                exist_product_event.id = exist_part.event AND exist_product_event.complete = :complete
            '
            );

            $qbExist->andWhere('exist_product_event.status != :status_closed');
            $qbExist->andWhere('exist_product_event.status != :status_completed');

            /** Только продукция на указанный завершающий этап */
            $qb->setParameter('complete', $complete, ManufacturePartComplete::TYPE);

            /** Только продукция в процессе производства */
            $qb->setParameter('status_closed', ManufacturePartStatusClosed::STATUS);
            $qb->setParameter('status_completed', ManufacturePartStatusCompleted::STATUS);


            $qbExist->andWhere('exist_product.product = product.event');
            $qbExist->andWhere('(exist_product.offer = product_offer.id)');
            $qbExist->andWhere('(exist_product.variation = product_variation.id)');
            $qbExist->andWhere('(exist_product.modification = product_modification.id)');
            $qbExist->setMaxResults(1);


            $qb->addSelect('(SELECT (' . $qbExist->getSQL() . ')) AS exist_manufacture');
        } else {
            $qb->addSelect('FALSE AS exist_manufacture');
        }






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

        $qb->orderBy('product.event', 'DESC');

        return $this->paginator->fetchAllAssociative($qb);

    }

}