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

namespace BaksDev\Manufacture\Part\Messenger;

use BaksDev\Manufacture\Part\Entity\ManufacturePart;
use BaksDev\Manufacture\Part\Repository\ManufacturePartSumProducts\ManufacturePartSumProductsInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(priority: 99)]
final class ManufacturePartProductsSum
{

    private EntityManagerInterface $entityManager;
    private ManufacturePartSumProductsInterface $manufacturePartSumProducts;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        ManufacturePartSumProductsInterface $manufacturePartSumProducts,
        LoggerInterface $manufacturePartLogger,
    )
    {
        $this->entityManager = $entityManager;
        $this->manufacturePartSumProducts = $manufacturePartSumProducts;
        $this->logger = $manufacturePartLogger;
    }

    /**
     * Метод делает пересчет всей продукции в заявке на производство
     */
    public function __invoke(ManufacturePartMessage $message): void
    {
        $this->entityManager->clear();

        $ManufacturePart = $this->entityManager->getRepository(ManufacturePart::class)->find($message->getId());

        if(!$ManufacturePart)
        {
            return;
        }

        $quantity = $this->manufacturePartSumProducts->sumManufacturePartProductsByEvent($ManufacturePart->getEvent());
        $ManufacturePart->setQuantity($quantity);
        $this->entityManager->flush();

        $this->logger->info('Выполнили пересчет всей продукции в производственной партии', [self::class.':'.__LINE__]);

    }
}
