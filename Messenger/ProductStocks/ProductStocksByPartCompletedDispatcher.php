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
use BaksDev\Manufacture\Part\Repository\ManufacturePartCurrentEvent\ManufacturePartCurrentEventInterface;
use BaksDev\Manufacture\Part\Repository\ProductsByManufacturePart\ProductsByManufacturePartInterface;
use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatus\ManufacturePartStatusCompleted;
use BaksDev\Manufacture\Part\UseCase\Admin\NewEdit\ManufacturePartDTO;
use BaksDev\Manufacture\Part\UseCase\Admin\NewEdit\Products\ManufacturePartProductsDTO;
use BaksDev\Manufacture\Part\UseCase\ProductStocks\ManufactureProductStockDTO;
use BaksDev\Manufacture\Part\UseCase\ProductStocks\ManufactureProductStockHandler;
use BaksDev\Manufacture\Part\UseCase\ProductStocks\Products\ProductStockDTO;
use BaksDev\Products\Product\Repository\CurrentProductIdentifier\CurrentProductIdentifierInterface;
use BaksDev\Products\Product\Repository\CurrentProductIdentifier\CurrentProductIdentifierResult;
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
        private ManufacturePartCurrentEventInterface $ManufacturePartCurrentEvent,
        private ProductsByManufacturePartInterface $ProductsByManufacturePart,
        private ManufactureProductStockHandler $ManufactureProductStockHandler,
        private DeduplicatorInterface $deduplicator,
        private CurrentProductIdentifierInterface $CurrentProductIdentifier
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

        if(false === ($ManufacturePartEvent instanceof ManufacturePartEvent))
        {
            $this->logger->critical(
                'manufacture-part: ManufacturePartEvent не определено',
                [var_export($message, true), self::class.':'.__LINE__]
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

        /**
         * Отправляем продукцию на склад
         */

        $ManufacturePartDTO = new ManufacturePartDTO();
        $ManufacturePartEvent->getDto($ManufacturePartDTO);

        /** @var ManufacturePartProductsDTO $ManufacturePartProductsDTO */
        foreach($ManufacturePartDTO->getProduct() as $ManufacturePartProductsDTO)
        {
            /** Поиск идентификаторов продукции по событию */
            $CurrentProductIdentifierResult = $this->CurrentProductIdentifier
                ->forEvent($ManufacturePartProductsDTO->getProduct())
                ->forOffer($ManufacturePartProductsDTO->getOffer())
                ->forVariation($ManufacturePartProductsDTO->getVariation())
                ->forModification($ManufacturePartProductsDTO->getModification())
                ->find();

            if(false === ($CurrentProductIdentifierResult instanceof CurrentProductIdentifierResult))
            {
                continue;
            }

            $ProductStockDTO = new ProductStockDTO()
                ->setProduct($CurrentProductIdentifierResult->getProduct())
                ->setOfferConst($CurrentProductIdentifierResult->getOfferConst())
                ->setVariationConst($CurrentProductIdentifierResult->getVariationConst())
                ->setModificationConst($CurrentProductIdentifierResult->getModificationConst())
                ->setTotal($ManufacturePartProductsDTO->getTotal());

            $ManufactureProductStockDTO = new ManufactureProductStockDTO()
                ->addProduct($ProductStockDTO);

            $ManufactureProductStockDTO
                ->getInvariable()
                ->setUsr($ManufacturePartDTO->getInvariable()->getUsr())
                ->setProfile($ManufacturePartDTO->getInvariable()->getProfile());

            $ProductStock = $this->ManufactureProductStockHandler->handle($ManufactureProductStockDTO);

            if(false === ($ProductStock instanceof ProductStock))
            {
                $this->logger->critical(
                    sprintf('manufacture-part: Ошибка %s при обновлении складских остатков после производства', $ProductStock),
                    [var_export($message, true), self::class.':'.__LINE__]
                );

                continue;
            }

            $this->logger->warning(
                'Обновили остатки склада после производства',
                [var_export($CurrentProductIdentifierResult, true), self::class.':'.__LINE__]
            );
        }


        $DeduplicatorExecuted->save();

        return true;
    }
}