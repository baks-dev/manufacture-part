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

namespace BaksDev\Manufacture\Part\Messenger\ManufactureProduct;


use BaksDev\Manufacture\Part\Entity\ManufactureProduct\ManufactureProductInvariable;
use BaksDev\Manufacture\Part\Type\Id\ManufacturePartUid;
use BaksDev\Manufacture\Part\UseCase\ManufactureProduct\Delete\DeleteManufactureProductInvariableDTO;
use BaksDev\Manufacture\Part\UseCase\ManufactureProduct\Delete\DeleteManufactureProductInvariableHandler;
use BaksDev\Manufacture\Part\UseCase\ManufactureProduct\New\NewManufactureProductInvariableDTO;
use BaksDev\Manufacture\Part\UseCase\ManufactureProduct\New\NewManufactureProductInvariableHandler;
use BaksDev\Products\Product\Type\Invariable\ProductInvariableUid;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(priority: 0)]
final readonly class ManufactureProductDispatcher
{
    public function __construct(
        #[Target('manufacturePartLogger')] private LoggerInterface $logger,
        private NewManufactureProductInvariableHandler $NewManufactureProductInvariableHandler,
        private DeleteManufactureProductInvariableHandler $DeleteManufactureProductInvariableHandler,
    ) {}

    public function __invoke(ManufactureProductMessage $message): void
    {
        /**
         * Добавляем идентификатор партии к продукции
         */

        if(
            true === ($message->getInvariable() instanceof ProductInvariableUid)
            && true === ($message->getManufacture() instanceof ManufacturePartUid)
        )
        {
            $NewManufactureProductInvariableDTO = new NewManufactureProductInvariableDTO(
                $message->getInvariable(),
                $message->getManufacture(),
                $message->getType(),
            );

            $result = $this->NewManufactureProductInvariableHandler->handle($NewManufactureProductInvariableDTO);

            if(true === ($result instanceof ManufactureProductInvariable))
            {
                $this->logger->info(
                    sprintf('Добавили идентификатор партии %s c продукцией %s в блокировку', $message->getManufacture(), $message->getInvariable()),
                    [self::class.':'.__LINE__],
                );
            }
            else
            {
                $this->logger->error(
                    sprintf('%s: Ошибка при добавлении идентификатора партии %s c продукцией %s', $result, $message->getManufacture(), $message->getInvariable()),
                    [self::class.':'.__LINE__, var_export($message, true)],
                );
            }

            return;
        }


        /**
         * Удаляем идентификатор продукции из партии
         */

        if(
            true === ($message->getInvariable() instanceof ProductInvariableUid)
            && false === ($message->getManufacture() instanceof ManufacturePartUid)
        )
        {
            $DeleteManufactureProductInvariableDTO = new DeleteManufactureProductInvariableDTO()
                ->deleteProductByInvariable($message->getInvariable(), $message->getType());

            $result = $this->DeleteManufactureProductInvariableHandler->handle($DeleteManufactureProductInvariableDTO);

            if(true === $result)
            {
                $this->logger->info(
                    sprintf('%s: Удалили идентификатор продукта из блокировки всех партий', $message->getInvariable()),
                    [self::class.':'.__LINE__],
                );
            }
            else
            {
                $this->logger->error(
                    sprintf('%s: Ошибка при удалении идентификатора продукта %s из блокировки всех партий', $result, $message->getInvariable()),
                    [self::class.':'.__LINE__, var_export($message, true)],
                );
            }


            return;
        }


        /** Удаляем идентификатор партии */

        if(
            false === ($message->getInvariable() instanceof ProductInvariableUid)
            && true === ($message->getManufacture() instanceof ManufacturePartUid)
        )
        {
            $DeleteManufactureProductInvariableDTO = new DeleteManufactureProductInvariableDTO()
                ->deleteProductsByManufacture($message->getManufacture());

            $result = $this->DeleteManufactureProductInvariableHandler->handle($DeleteManufactureProductInvariableDTO);

            if(true === $result)
            {
                $this->logger->info(
                    sprintf('%s: Удалили идентификатор партии с продукцией из блокировки', $message->getManufacture()),
                    [self::class.':'.__LINE__],
                );
            }
            else
            {
                $this->logger->error(
                    sprintf('%s: Ошибка при удалении идентификатора партии %s с продукцией из блокировки', $result, $message->getManufacture()),
                    [self::class.':'.__LINE__, var_export($message, true)],
                );
            }

            return;
        }

    }
}
