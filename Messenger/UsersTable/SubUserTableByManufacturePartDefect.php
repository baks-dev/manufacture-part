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
use BaksDev\Manufacture\Part\Messenger\ManufacturePartMessage;
use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatus\ManufacturePartStatusDefect;
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
final class SubUserTableByManufacturePartDefect
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
     * Штрафуем сотрудника по причине дефекта
     */
    public function __invoke(ManufacturePartMessage $message): void
    {

        if(!class_exists(UsersTable::class))
        {
            return;
        }

        /* Получаем событие заявки на производство  */
        $this->entityManager->clear();
        $ManufacturePartEvent = $this->entityManager
            ->getRepository(ManufacturePartEvent::class)
            ->find($message->getEvent());


        /** Только если дефект на производстве */
        if($ManufacturePartEvent && $ManufacturePartEvent->getStatus()->equals(ManufacturePartStatusDefect::class))
        {
            /** Если отсутствует рабочий процесс - Дефект производственного сырья */
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

            $this->logger->info('Добавляем штраф сотруднику', [
                __FILE__.':'.__LINE__,
                'class' => self::class,
                'message' => sprintf("new %s(new %s('%s'),new %s('%s'));",
                    $message::class,
                    $message->getId()::class,
                    $message->getId(),
                    $message->getEvent()::class,
                    $message->getEvent()
                )
            ]);

            /** Получаем общее количество в заявке */
            //$ManufacturePart = $this->entityManager->getRepository(ManufacturePart::class)->find($message->getId());


            /** Приводим к отрицательному числу */
            $fine = -$message->getTotal();
            
            /** Создаем и сохраняем табель сотруднику */
            $UsersTableDTO = new UsersTableDTO(authority: $ManufacturePartWorkingDTO->getProfile());
            $UsersTableDTO->setProfile($ManufacturePartWorkingDTO->getProfile());
            $UsersTableDTO->setWorking($ManufacturePartWorkingDTO->getWorking());
            $UsersTableDTO->setQuantity($fine);

            $UsersTableHandler = $this->usersTableHandler->handle($UsersTableDTO);

            if(!$UsersTableHandler instanceof UsersTable)
            {
                throw new DomainException(sprintf('%s: Ошибка при сохранении табеля сотрудника', $UsersTableHandler) );
            }

            $this->logger->info('Добавили штраф', ['profile' => $ManufacturePartWorkingDTO->getProfile()]);

        }
    }
}