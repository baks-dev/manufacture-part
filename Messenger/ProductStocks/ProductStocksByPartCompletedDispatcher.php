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
use BaksDev\Manufacture\Part\Entity\Event\ManufacturePartEvent;
use BaksDev\Manufacture\Part\Messenger\ManufacturePartMessage;
use BaksDev\Manufacture\Part\Repository\ManufacturePartEvent\ManufacturePartEventInterface;
use BaksDev\Manufacture\Part\Repository\ProductsByManufacturePart\ProductsByManufacturePartInterface;
use BaksDev\Manufacture\Part\Repository\ProductsByManufacturePart\ProductsByManufacturePartResult;
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
        private ManufacturePartEventInterface $ManufacturePartEventRepository,
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

        $ManufacturePartEvent = $this->ManufacturePartEventRepository
            ->forEvent($message->getEvent())
            ->find();

        if(false === ($ManufacturePartEvent instanceof ManufacturePartEvent))
        {
            $this->logger->critical(
                'manufacture-part: ManufacturePartEvent не определено',
                [$message, self::class.':'.__LINE__]
            );

            return false;
        }

        /**
         * Если статус производства не Completed «Укомплектована»
         */
        if(false === $ManufacturePartEvent->equalsManufacturePartStatus(ManufacturePartStatusCompleted::class))
        {
            return true;
        }

        /** Получаем всю продукцию в производственной партии */

        $ProductsManufacture = $this->ProductsByManufacturePart
            ->forPart($message->getId())
            ->findAll();

        if(false === $ProductsManufacture || false === $ProductsManufacture->valid())
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

        /** @var ProductsByManufacturePartResult $product */

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

            $ProductStockDTO = new ProductStockDTO()
                ->setProduct($product->getProductId())
                ->setOfferConst($product->getProductOfferConst())
                ->setVariationConst($product->getProductVariationConst())
                ->setModificationConst($product->getProductModificationConst())
                ->setTotal($product->getTotal());

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
                    sprintf('manufacture-part: Ошибка %s при обновлении складских остатков после производства', $ProductStock),
                    [$message, $product, self::class.':'.__LINE__]
                );
            }

            $DeduplicatorPack->save();
        }

        $DeduplicatorExecuted->save();

        return true;
    }
}