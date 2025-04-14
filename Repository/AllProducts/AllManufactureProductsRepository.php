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

namespace BaksDev\Manufacture\Part\Repository\AllProducts;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Core\Form\Search\SearchDTO;
use BaksDev\Core\Services\Paginator\PaginatorInterface;
use BaksDev\Delivery\Entity\Delivery;
use BaksDev\Delivery\Type\Id\DeliveryUid;
use BaksDev\Manufacture\Part\Entity\Event\ManufacturePartEvent;
use BaksDev\Manufacture\Part\Entity\Invariable\ManufacturePartInvariable;
use BaksDev\Manufacture\Part\Entity\ManufacturePart;
use BaksDev\Manufacture\Part\Entity\Products\ManufacturePartProduct;
use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatus\ManufacturePartStatusClosed;
use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatus\ManufacturePartStatusCompleted;
use BaksDev\Products\Category\Entity\CategoryProduct;
use BaksDev\Products\Category\Entity\Offers\CategoryProductOffers;
use BaksDev\Products\Category\Entity\Offers\Variation\CategoryProductVariation;
use BaksDev\Products\Category\Entity\Offers\Variation\Modification\CategoryProductModification;
use BaksDev\Products\Category\Entity\Trans\CategoryProductTrans;
use BaksDev\Products\Category\Type\Id\CategoryProductUid;
use BaksDev\Products\Product\Entity\Category\ProductCategory;
use BaksDev\Products\Product\Entity\Info\ProductInfo;
use BaksDev\Products\Product\Entity\Offers\Image\ProductOfferImage;
use BaksDev\Products\Product\Entity\Offers\ProductOffer;
use BaksDev\Products\Product\Entity\Offers\Variation\Image\ProductVariationImage;
use BaksDev\Products\Product\Entity\Offers\Variation\Modification\ProductModification;
use BaksDev\Products\Product\Entity\Offers\Variation\ProductVariation;
use BaksDev\Products\Product\Entity\Photo\ProductPhoto;
use BaksDev\Products\Product\Entity\Product;
use BaksDev\Products\Product\Entity\Property\ProductProperty;
use BaksDev\Products\Product\Entity\Trans\ProductTrans;
use BaksDev\Products\Product\Forms\ProductFilter\Admin\ProductFilterDTO;
use BaksDev\Products\Product\Forms\ProductFilter\Admin\Property\ProductFilterPropertyDTO;
use BaksDev\Users\Profile\UserProfile\Entity\Personal\UserProfilePersonal;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\Profile\UserProfile\Repository\UserProfileTokenStorage\UserProfileTokenStorageInterface;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;

final class AllManufactureProductsRepository implements AllManufactureProductsInterface
{
    private ?SearchDTO $search = null;

    private ?ProductFilterDTO $filter = null;

    private DeliveryUid|false $delivery = false;

    public function __construct(
        private readonly DBALQueryBuilder $DBALQueryBuilder,
        private readonly PaginatorInterface $paginator,
        private readonly UserProfileTokenStorageInterface $UserProfileTokenStorage
    ) {}

    public function search(SearchDTO $search): self
    {
        $this->search = $search;
        return $this;
    }

    public function filter(ProductFilterDTO $filter): self
    {
        $this->filter = $filter;
        return $this;
    }

    public function forDeliveryType(Delivery|DeliveryUid|string|false $delivery): self
    {
        if(empty($delivery))
        {
            $this->delivery = false;
            return $this;
        }

        if(is_string($delivery))
        {
            $delivery = new DeliveryUid($delivery);
        }

        if($delivery instanceof Delivery)
        {
            $delivery = $delivery->getId();
        }

        $this->delivery = $delivery;

        return $this;
    }


    /**
     * Метод возвращает все товары, которые не участвуют в производстве
     */
    public function findPaginator(): PaginatorInterface
    {

        $dbal = $this->DBALQueryBuilder
            ->createQueryBuilder(self::class)
            ->bindLocal();

        $dbal
            ->select('product.id')
            ->addSelect('product.event')
            ->from(Product::class, 'product');


        $dbal
            ->addSelect('product_trans.name AS product_name')
            ->leftJoin(
                'product',
                ProductTrans::class,
                'product_trans',
                'product_trans.event = product.event AND product_trans.local = :local'
            );


        /* ProductInfo */

        $dbal
            ->addSelect('product_info.url')
            ->join(
                'product',
                ProductInfo::class,
                'product_info',
                'product_info.product = product.id AND (product_info.profile IS NULL OR product_info.profile = :profile)'
            )
            ->setParameter(
                key: 'profile',
                value: $this->UserProfileTokenStorage->getProfile(),
                type: UserProfileUid::TYPE
            );


        /** Ответственное лицо (Профиль пользователя) */
        $dbal->leftJoin(
            'product_info',
            UserProfile::class,
            'users_profile',
            'users_profile.id = product_info.profile'
        );

        $dbal
            ->addSelect('users_profile_personal.username AS users_profile_username')
            ->leftJoin(
                'users_profile',
                UserProfilePersonal::class,
                'users_profile_personal',
                'users_profile_personal.event = users_profile.event'
            );


        /** Торговое предложение */

        $dbal
            ->addSelect('product_offer.id as product_offer_id')
            ->addSelect('product_offer.value as product_offer_value')
            ->addSelect('product_offer.postfix as product_offer_postfix')
            ->leftJoin(
                'product',
                ProductOffer::class,
                'product_offer',
                'product_offer.event = product.event'
            );

        if($this->filter?->getOffer())
        {
            $dbal->andWhere('product_offer.value = :offer');
            $dbal->setParameter('offer', $this->filter->getOffer());
        }


        /* Тип торгового предложения */
        $dbal
            ->addSelect('category_offer.reference as product_offer_reference')
            ->leftJoin(
                'product_offer',
                CategoryProductOffers::class,
                'category_offer',
                'category_offer.id = product_offer.category_offer'
            );


        /** Множественные варианты торгового предложения */

        $dbal
            ->addSelect('product_variation.id as product_variation_id')
            ->addSelect('product_variation.value as product_variation_value')
            ->addSelect('product_variation.postfix as product_variation_postfix')
            ->leftJoin(
                'product_offer',
                ProductVariation::class,
                'product_variation',
                'product_variation.offer = product_offer.id'
            );


        if($this->filter?->getVariation())
        {
            $dbal
                ->andWhere('product_variation.value = :variation')
                ->setParameter('variation', $this->filter->getVariation());
        }


        /* Тип множественного варианта торгового предложения */
        $dbal
            ->addSelect('category_offer_variation.reference as product_variation_reference')
            ->leftJoin(
                'product_variation',
                CategoryProductVariation::class,
                'category_offer_variation',
                'category_offer_variation.id = product_variation.category_variation'
            );


        /** Модификация множественного варианта */
        $dbal
            ->addSelect('product_modification.id as product_modification_id')
            ->addSelect('product_modification.value as product_modification_value')
            ->addSelect('product_modification.postfix as product_modification_postfix')
            ->leftJoin(
                'product_variation',
                ProductModification::class,
                'product_modification',
                'product_modification.variation = product_variation.id '
            );


        /** Получаем тип модификации множественного варианта */
        $dbal
            ->addSelect('category_offer_modification.reference as product_modification_reference')
            ->leftJoin(
                'product_modification',
                CategoryProductModification::class,
                'category_offer_modification',
                'category_offer_modification.id = product_modification.category_modification'
            );


        /** Артикул продукта */

        $dbal->addSelect('
            COALESCE(
                product_modification.article, 
                product_variation.article, 
                product_offer.article, 
                product_info.article
            ) AS product_article
		');


        /** Фото продукта */

        $dbal->leftJoin(
            'product',
            ProductPhoto::class,
            'product_photo',
            'product_photo.event = product.event AND product_photo.root = true'
        );

        $dbal->leftJoin(
            'product_offer',
            ProductVariationImage::class,
            'product_offer_variation_image',
            'product_offer_variation_image.variation = product_variation.id AND product_offer_variation_image.root = true'
        );

        $dbal->leftJoin(
            'product_offer',
            ProductOfferImage::class,
            'product_offer_images',
            'product_offer_images.offer = product_offer.id AND product_offer_images.root = true'
        );

        $dbal->addSelect("
			CASE
			   WHEN product_offer_variation_image.name IS NOT NULL THEN
					CONCAT ( '/upload/".$dbal->table(ProductVariationImage::class)."' , '/', product_offer_variation_image.name)
			   WHEN product_offer_images.name IS NOT NULL THEN
					CONCAT ( '/upload/".$dbal->table(ProductOfferImage::class)."' , '/', product_offer_images.name)
			   WHEN product_photo.name IS NOT NULL THEN
					CONCAT ( '/upload/".$dbal->table(ProductPhoto::class)."' , '/', product_photo.name)
			   ELSE NULL
			END AS product_image
		");

        /** Флаг загрузки файла CDN */
        $dbal->addSelect("
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
        $dbal->addSelect("
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
        $dbal->join(
            'product',
            ProductCategory::class,
            'product_event_category',
            'product_event_category.event = product.event AND product_event_category.root = true'
        );

        if($this->filter->getCategory())
        {
            $dbal
                ->andWhere('product_event_category.category = :category')
                ->setParameter(
                    key: 'category',
                    value: $this->filter->getCategory(),
                    type: CategoryProductUid::TYPE
                );
        }

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


        if($this->filter->getProperty())
        {
            $filterProperty = null;

            /** @var ProductFilterPropertyDTO $property */
            foreach($this->filter->getProperty() as $property)
            {
                if($property->getValue())
                {
                    $filterProperty = ['(product_property.field = :'.$property->getType().'_const AND product_property.value = :'.$property->getType().'_value )'];
                    $dbal->setParameter($property->getType().'_const', $property->getConst());
                    $dbal->setParameter($property->getType().'_value', $property->getValue());
                }
            }

            $dbal->join(
                'product',
                ProductProperty::class,
                'product_property',
                'product_property.event = product.event '.($filterProperty ? ' AND '.implode(' AND ', $filterProperty) : '')
            );
        }


        if($this->delivery)
        {
            /** Только товары, которых нет в производстве */

            $dbalExist = $this->DBALQueryBuilder->createQueryBuilder(self::class);

            $dbalExist
                ->select('exist_part_invariable.number AS number')
                ->from(ManufacturePartProduct::class, 'exist_product');

            $dbalExist->join(
                'exist_product',
                ManufacturePart::class,
                'exist_part',
                '
                exist_part.event = exist_product.event
            '
            );

            $dbalExist->leftJoin(
                'exist_part',
                ManufacturePartInvariable::class,
                'exist_part_invariable',
                'exist_part_invariable.main = exist_part.id'
            );

            $dbalExist->join(
                'exist_part',
                ManufacturePartEvent::class,
                'exist_product_event',
                '
                exist_product_event.id = exist_part.event AND 
                exist_product_event.complete = :complete
            '
            );

            $dbalExist->andWhere('exist_product_event.status != :status_closed');
            $dbalExist->andWhere('exist_product_event.status != :status_completed');

            /** Только продукция на указанный завершающий этап */
            $dbal->setParameter('complete', $this->delivery, DeliveryUid::TYPE);

            /** Только продукция в процессе производства */
            $dbal->setParameter('status_closed', ManufacturePartStatusClosed::STATUS);
            $dbal->setParameter('status_completed', ManufacturePartStatusCompleted::STATUS);


            $dbalExist->andWhere('exist_product.product = product.event');
            $dbalExist->andWhere('(exist_product.offer = product_offer.id)');
            $dbalExist->andWhere('(exist_product.variation = product_variation.id)');
            $dbalExist->andWhere('(exist_product.modification = product_modification.id)');
            $dbalExist->setMaxResults(1);


            $dbal->addSelect('(SELECT ('.$dbalExist->getSQL().')) AS exist_manufacture');

            $dbal->allGroupByExclude('exist_part');

        }
        else
        {
            $dbal->addSelect('FALSE AS exist_manufacture');
            $dbal->allGroupByExclude();
        }


        if($this->search?->getQuery())
        {
            $dbal
                ->createSearchQueryBuilder($this->search)
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


        $dbal
            ->orderBy('product.event', 'DESC');

        return $this->paginator->fetchAllAssociative($dbal);

    }

}
