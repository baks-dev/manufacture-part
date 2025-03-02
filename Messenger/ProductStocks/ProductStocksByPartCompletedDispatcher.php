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

namespace BaksDev\Manufacture\Part\Messenger\ProductStocks;


use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Manufacture\Part\Messenger\ManufacturePartMessage;
use BaksDev\Manufacture\Part\Repository\ManufacturePartCurrentEvent\ManufacturePartCurrentEventInterface;
use BaksDev\Manufacture\Part\Repository\ProductsByManufacturePart\ProductsByManufacturePartInterface;
use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatus\ManufacturePartStatusCompleted;
use BaksDev\Manufacture\Part\UseCase\Admin\NewEdit\ManufacturePartDTO;
use BaksDev\Manufacture\Part\UseCase\ProductStocks\ManufactureProductStockDTO;
use BaksDev\Manufacture\Part\UseCase\ProductStocks\ManufactureProductStockHandler;
use BaksDev\Manufacture\Part\UseCase\ProductStocks\Products\ProductStockDTO;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Stocks\Entity\Stock\ProductStock;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;


/**
 * Обновляет складские остатки продукции после завершающего этапа производства
 */
#[AsMessageHandler(priority: 70)]
final readonly class ProductStocksByPartCompletedDispatcher
{
    public function __construct(
        #[Target('manufacturePartLogger')] private LoggerInterface $logger,
        private ProductsByManufacturePartInterface $ProductsByManufacturePart,
        private ManufacturePartCurrentEventInterface $ManufacturePartCurrentEvent,
        private ManufactureProductStockHandler $ManufactureProductStockHandler,
        private DeduplicatorInterface $deduplicator,
    ) {}


    public function __invoke(ManufacturePartMessage $message): bool
    {
        $DeduplicatorExecuted = $this
            ->deduplicator
            ->namespace('wildberries-package')
            ->deduplication([(string) $message->getId(), self::class]);

        if($DeduplicatorExecuted->isExecuted())
        {
            return true;
        }

        $ManufacturePartEvent = $this->ManufacturePartCurrentEvent
            ->fromPart($message->getId())
            ->find();

        if(false === $ManufacturePartEvent)
        {
            $this->logger->critical(
                'manufacture-part: ManufacturePartEvent не определено',
                [$message, self::class.':'.__LINE__]
            );

            return false;
        }

        if(false === $ManufacturePartEvent->equalsManufacturePartStatus(ManufacturePartStatusCompleted::class))
        {
            return true;
        }

        /** Получаем всю продукцию в производственной партии */

        $ProductsManufacture = $this->ProductsByManufacturePart
            ->forPart($message->getId())
            ->findAll();

        if(empty($ProductsManufacture))
        {
            return false;
        }

        /**
         * Отправляем продукцию на склад
         */

        $ManufacturePartDTO = new ManufacturePartDTO();
        $ManufacturePartEvent->getDto($ManufacturePartDTO);


        $this->logger->warning(
            'Обновляем остатки склада после производства',
            [$ManufacturePartEvent, self::class.':'.__LINE__]
        );

        foreach($ProductsManufacture as $product)
        {
            $DeduplicatorPack = $this->deduplicator
                ->deduplication([(string) $message->getId(), $product, self::class]);

            if($DeduplicatorPack->isExecuted())
            {
                continue;
            }

            $this->logger->info(
                'Обновляем продукцию после производства',
                [$product, self::class.':'.__LINE__]
            );

            $ProductUid = new ProductUid($product['product_id']);
            $ProductOfferConst = !empty($product['product_offer_const']) ? new ProductOfferConst($product['product_offer_const']) : null;
            $ProductVariationConst = !empty($product['product_variation_const']) ? new ProductVariationConst($product['product_variation_const']) : null;
            $ProductModificationConst = !empty($product['product_modification_const']) ? new ProductModificationConst($product['product_modification_const']) : null;

            $ProductStockDTO = new ProductStockDTO()
                ->setProduct($ProductUid)
                ->setOffer($ProductOfferConst)
                ->setVariation($ProductVariationConst)
                ->setModification($ProductModificationConst)
                ->setTotal($product['product_total']);

            $ManufactureProductStockDTO = new ManufactureProductStockDTO()
                ->addProduct($ProductStockDTO);

            $ManufactureProductStocksInvariableDTO = $ManufactureProductStockDTO->getInvariable();
            $ManufactureProductStocksInvariableDTO
                ->setUsr($ManufacturePartDTO->getInvariable()->getUsr())
                ->setProfile($ManufacturePartDTO->getInvariable()->getProfile());

            $ProductStock = $this->ManufactureProductStockHandler->handle($ManufactureProductStockDTO);

            if(false === ($ProductStock instanceof ProductStock))
            {
                $this->logger->critical(
                    'manufacture-part: Ошибка при обновлении складских остатков после производства',
                    [$message, $product, self::class.':'.__LINE__]
                );
            }

            $DeduplicatorPack->save();
        }

        $DeduplicatorExecuted->save();

        return true;
    }
}