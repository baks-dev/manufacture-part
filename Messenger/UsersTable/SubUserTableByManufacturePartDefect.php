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

namespace BaksDev\Manufacture\Part\Messenger\UsersTable;

use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Manufacture\Part\Entity\Event\ManufacturePartEvent;
use BaksDev\Manufacture\Part\Entity\Working\ManufacturePartWorking;
use BaksDev\Manufacture\Part\Messenger\ManufacturePartMessage;
use BaksDev\Manufacture\Part\Repository\ManufacturePartCurrentEvent\ManufacturePartCurrentEventInterface;
use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatus\ManufacturePartStatusDefect;
use BaksDev\Manufacture\Part\UseCase\Admin\Action\ManufacturePartActionDTO;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\UsersTable\Entity\Table\UsersTable;
use BaksDev\Users\UsersTable\UseCase\Admin\Table\NewEdit\UsersTableDTO;
use BaksDev\Users\UsersTable\UseCase\Admin\Table\NewEdit\UsersTableHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Штрафуем сотрудника по причине дефекта
 */
#[AsMessageHandler]
final readonly class SubUserTableByManufacturePartDefect
{
    public function __construct(
        #[Target('manufacturePartLogger')] private LoggerInterface $logger,
        private ManufacturePartCurrentEventInterface $ManufacturePartCurrentEvent,
        private UsersTableHandler $usersTableHandler,
        private DeduplicatorInterface $deduplicator
    ) {}


    public function __invoke(ManufacturePartMessage $message): bool
    {
        if(!class_exists(UsersTable::class))
        {
            return false;
        }

        $DeduplicatorExecuted = $this->deduplicator
            ->namespace('manufacture-part')
            ->deduplication([$message, self::class]);

        if($DeduplicatorExecuted->isExecuted())
        {
            return true;
        }

        $ManufacturePartEvent = $this->ManufacturePartCurrentEvent->fromPart($message->getId())->find();

        if(false === ($ManufacturePartEvent instanceof ManufacturePartEvent))
        {
            return false;
        }

        /** Только если статус DEFECT «Дефект при производстве» */
        if(false === $ManufacturePartEvent->getStatus()->equals(ManufacturePartStatusDefect::class))
        {
            return false;
        }


        /** Если отсутствует рабочий процесс - Дефект производственного сырья */
        if(false === ($ManufacturePartEvent->getWorking() instanceof ManufacturePartWorking))
        {
            return false;
        }

        /*
         * Получаем рабочее состояние события
         */
        $WorkingManufacturePartDTO = new ManufacturePartActionDTO($message->getEvent());
        $ManufacturePartEvent->getDto($WorkingManufacturePartDTO);
        $ManufacturePartWorkingDTO = $WorkingManufacturePartDTO->getWorking();


        /** Если не указан профиль пользователя - закрываем */
        if(false === ($ManufacturePartWorkingDTO->getProfile() instanceof UserProfileUid))
        {
            return false;
        }

        $this->logger->info(
            'Добавляем штраф сотруднику',
            [$message, self::class.':'.__LINE__]
        );

        /** Приводим к отрицательному числу */
        $fine = -$message->getTotal();

        /** Создаем и сохраняем табель сотруднику */
        $UsersTableDTO = new UsersTableDTO(authority: $ManufacturePartWorkingDTO->getProfile())
            ->setProfile($ManufacturePartWorkingDTO->getProfile())
            ->setWorking($ManufacturePartWorkingDTO->getWorking())
            ->setQuantity($fine);

        $UsersTableHandler = $this->usersTableHandler->handle($UsersTableDTO);

        if(false === ($UsersTableHandler instanceof UsersTable))
        {
            $this->logger->critical(
                sprintf('manufacture-part: Ошибка %s при сохранении табеля сотрудника', $UsersTableHandler),
                [$message, self::class.':'.__LINE__]
            );

            return false;
        }

        $this->logger->info(
            sprintf('Добавили штраф профилю %s', $ManufacturePartWorkingDTO->getProfile()),
            [$message, self::class.':'.__LINE__]
        );

        $DeduplicatorExecuted->save();

        return true;

    }
}