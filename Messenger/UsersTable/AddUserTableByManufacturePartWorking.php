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

namespace BaksDev\Manufacture\Part\Messenger\UsersTable;

use BaksDev\Manufacture\Part\Entity\Event\ManufacturePartEvent;
use BaksDev\Manufacture\Part\Entity\ManufacturePart;
use BaksDev\Manufacture\Part\Messenger\ManufacturePartMessage;
use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatus\ManufacturePartStatusPackage;
use BaksDev\Manufacture\Part\UseCase\Admin\Action\ManufacturePartActionDTO;
use BaksDev\Manufacture\Part\UseCase\Admin\Working\WorkingManufacturePartDTO;
use BaksDev\Users\UsersTable\Entity\Table\UsersTable;
use BaksDev\Users\UsersTable\UseCase\Admin\Table\NewEdit\UsersTableDTO;
use BaksDev\Users\UsersTable\UseCase\Admin\Table\NewEdit\UsersTableHandler;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class AddUserTableByManufacturePartWorking
{
    private EntityManagerInterface $entityManager;
    private UsersTableHandler $usersTableHandler;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        UsersTableHandler $usersTableHandler,
        LoggerInterface $manufacturePartLogger,
    )
    {
        $this->entityManager = $entityManager;
        $this->usersTableHandler = $usersTableHandler;
        $this->logger = $manufacturePartLogger;
    }

    /**
     * Добавляем в табель сотрудника действие
     */
    public function __invoke(ManufacturePartMessage $message): void
    {
        if(!class_exists(UsersTable::class))
        {
            return;
        }

        $this->entityManager->clear();

        $ManufacturePart = $this->entityManager->getRepository(ManufacturePart::class)->find($message->getId());

        if(!$ManufacturePart)
        {
            return;
        }

        $ManufacturePartEvent = $this->entityManager
            ->getRepository(ManufacturePartEvent::class)
            ->find($message->getEvent());

        /** Только если статус партии - PACKAGE «На сборке (упаковке)» */
        if(!$ManufacturePartEvent || false === $ManufacturePartEvent->getStatus()->equals(ManufacturePartStatusPackage::class))
        {
            return;
        }

        /** Если отсутствует рабочий процесс - заявка только поступила на производство */
        if(!$ManufacturePartEvent->getWorking())
        {
            return;
        }

        /*
         * Получаем рабочее состояние события
         */
        $WorkingManufacturePartDTO = new ManufacturePartActionDTO($message->getEvent());
        $ManufacturePartEvent->getDto($WorkingManufacturePartDTO);
        $ManufacturePartWorkingDTO = $WorkingManufacturePartDTO->getWorking();

        /** Если не указан профиль пользователя - закрываем */
        if(!$ManufacturePartWorkingDTO->getProfile())
        {
            return;
        }

        /** Получаем общее количество в заявке */
        $this->logger->info('Добавляем действие сотрудника в табель', [self::class.':'.__LINE__]);

        /** Создаем и сохраняем табель сотруднику */
        $UsersTableDTO = new UsersTableDTO(authority: $ManufacturePartWorkingDTO->getProfile());
        $UsersTableDTO->setProfile($ManufacturePartWorkingDTO->getProfile());
        $UsersTableDTO->setWorking($ManufacturePartWorkingDTO->getWorking());
        $UsersTableDTO->setQuantity($ManufacturePart->getQuantity());

        $UsersTableHandler = $this->usersTableHandler->handle($UsersTableDTO);

        if(!$UsersTableHandler instanceof UsersTable)
        {
            throw new DomainException(sprintf('%s: Ошибка при сохранении табеля сотрудника', $UsersTableHandler));
        }

        $this->logger->info('Добавили табель сотрудника', [
            'profile' => $ManufacturePartWorkingDTO->getProfile(),
            'working' => $ManufacturePartWorkingDTO->getWorking(),
            'quantity' => $ManufacturePart->getQuantity()
        ]);

    }
}