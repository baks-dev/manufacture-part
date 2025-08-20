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
 *
 */

declare(strict_types=1);

namespace BaksDev\Manufacture\Part\Messenger\ProductStocks;


use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Manufacture\Part\Entity\Event\ManufacturePartEvent;
use BaksDev\Manufacture\Part\Messenger\ManufacturePartMessage;
use BaksDev\Manufacture\Part\Repository\ManufacturePartCurrentEvent\ManufacturePartCurrentEventInterface;
use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatus\ManufacturePartStatusCompleted;
use BaksDev\Manufacture\Part\UseCase\Admin\NewEdit\ManufacturePartDTO;
use BaksDev\Manufacture\Part\UseCase\Admin\NewEdit\Products\ManufacturePartProductsDTO;
use BaksDev\Products\Product\Messenger\Quantity\UpdateProductQuantityMessage;
use BaksDev\Products\Product\Repository\CurrentProductIdentifier\CurrentProductIdentifierInterface;
use BaksDev\Products\Product\Repository\CurrentProductIdentifier\CurrentProductIdentifierResult;
use BaksDev\Products\Stocks\Entity\Total\ProductStockTotal;
use BaksDev\Products\Stocks\Repository\ProductStocksTotalStorage\ProductStocksTotalStorageInterface;
use BaksDev\Products\Stocks\Repository\UpdateProductStock\AddProductStockInterface;
use Doctrine\ORM\EntityManagerInterface;
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
        private DeduplicatorInterface $deduplicator,
        private CurrentProductIdentifierInterface $CurrentProductIdentifier,
        private AddProductStockInterface $AddProductStock,
        private EntityManagerInterface $entityManager,
        private ProductStocksTotalStorageInterface $ProductStocksTotalStorage,
        private MessageDispatchInterface $MessageDispatch,
    ) {}

    public function __invoke(ManufacturePartMessage $message): bool
    {
        $DeduplicatorExecuted = $this
            ->deduplicator
            ->namespace('manufacture-part')
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
                [var_export($message, true), self::class.':'.__LINE__],
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


        /** Создаем приход на склад */

        $ManufacturePartInvariableDTO = $ManufacturePartDTO->getInvariable();

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
                $this->logger->critical(
                    'manufacture-part: идентификатор продукции не найден для обновления остатка после производства',
                    [
                        'ProductEventUid' => (string) $ManufacturePartProductsDTO->getProduct(),
                        'ProductOfferUid' => (string) $ManufacturePartProductsDTO->getOffer(),
                        'ProductVariationUid' => (string) $ManufacturePartProductsDTO->getVariation(),
                        'ProductModificationUid' => (string) $ManufacturePartProductsDTO->getModification(),
                        self::class.':'.__LINE__,
                    ],
                );

                continue;
            }

            /** Получаем место для хранения указанной продукции данного профиля */
            $ProductStockTotal = $this->ProductStocksTotalStorage
                ->profile($ManufacturePartInvariableDTO->getProfile())
                ->product($CurrentProductIdentifierResult->getProduct())
                ->offer($CurrentProductIdentifierResult->getOfferConst())
                ->variation($CurrentProductIdentifierResult->getVariationConst())
                ->modification($CurrentProductIdentifierResult->getModificationConst())
                ->storage('pl') // производственная линия
                ->find();

            /** Если отсутствует место складирования - создаем на указанный профиль пользователя */
            if(false === ($ProductStockTotal instanceof ProductStockTotal))
            {

                $ProductStockTotal = new ProductStockTotal(
                    $ManufacturePartInvariableDTO->getUsr(),
                    $ManufacturePartInvariableDTO->getProfile(),
                    $CurrentProductIdentifierResult->getProduct(),
                    $CurrentProductIdentifierResult->getOfferConst(),
                    $CurrentProductIdentifierResult->getVariationConst(),
                    $CurrentProductIdentifierResult->getModificationConst(),
                    'pl',
                );

                $this->entityManager->persist($ProductStockTotal);
                $this->entityManager->flush();

                $this->logger->info(
                    'Место складирования профиля не найдено! Создали новое место для указанной продукции',
                    [
                        self::class.':'.__LINE__,
                        'profile' => (string) $ManufacturePartInvariableDTO->getProfile(),
                    ],
                );
            }

            $this->logger->info(
                sprintf('Добавляем приход продукции прозводственной партии %s', $ManufacturePartInvariableDTO->getNumber()),
                [self::class.':'.__LINE__],
            );


            /**
             * Добавляем приход продукции на склад на указанный профиль (склад)
             */

            $rows = $this->AddProductStock
                ->total($ManufacturePartProductsDTO->getTotal())
                ->reserve(false) // не обновляем резерв
                ->updateById($ProductStockTotal);

            if(empty($rows))
            {
                $this->logger->critical(
                    sprintf('manufacture-part: Ошибка при обновлении складских остатков после производства: #%s', $ManufacturePartInvariableDTO->getNumber()),
                    [
                        'ProductStockTotalUid' => (string) $ProductStockTotal->getId(),
                        self::class.':'.__LINE__,
                    ],
                );

                continue;
            }

            /**
             * Обновляем остаток в карточке на количество произведенной продукции
             */


            $UpdateProductQuantityMessage = new UpdateProductQuantityMessage(
                event: $ManufacturePartProductsDTO->getProduct(),
                reserve: $ManufacturePartProductsDTO->getTotal(),
                quantity: $ManufacturePartProductsDTO->getTotal(),
                offer: $ManufacturePartProductsDTO->getOffer(),
                variation: $ManufacturePartProductsDTO->getVariation(),
                modification: $ManufacturePartProductsDTO->getModification(),
            );

            $this->MessageDispatch->dispatch(
                message: $UpdateProductQuantityMessage,
                transport: $ManufacturePartInvariableDTO->getProfile().'-low',
            );


            $this->logger->info(
                'Добавили приход продукции после производства',
                [
                    'ProductStockTotalUid' => (string) $ProductStockTotal->getId(),
                    self::class.':'.__LINE__,
                ],
            );

        }

        $DeduplicatorExecuted->save();

        return true;
    }
}