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

namespace BaksDev\Manufacture\Part\Controller\Admin;


use BaksDev\Barcode\Writer\BarcodeFormat;
use BaksDev\Barcode\Writer\BarcodeType;
use BaksDev\Barcode\Writer\BarcodeWrite;
use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Core\Type\UidType\ParamConverter;
use BaksDev\Manufacture\Part\Entity\Event\ManufacturePartEvent;
use BaksDev\Manufacture\Part\Entity\ManufacturePart;
use BaksDev\Manufacture\Part\Repository\ActiveWorkingManufacturePart\ActiveWorkingManufacturePartInterface;
use BaksDev\Manufacture\Part\Repository\ManufacturePartCurrentEvent\ManufacturePartCurrentEventInterface;
use BaksDev\Manufacture\Part\Type\Id\ManufacturePartUid;
use BaksDev\Manufacture\Part\UseCase\Admin\Action\ManufacturePartActionDTO;
use BaksDev\Manufacture\Part\UseCase\Admin\Action\ManufacturePartActionForm;
use BaksDev\Manufacture\Part\UseCase\Admin\Action\ManufacturePartActionHandler;
use BaksDev\Users\UsersTable\Type\Actions\Working\UsersTableActionsWorkingUid;
use chillerlan\QRCode\QRCode;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
//#[RoleSecurity('ROLE_MANUFACTURE_PART_SCAN')]
final class ScannerController extends AbstractController
{
    private BarcodeWrite $BarcodeWrite;

    /**
     * Меняет производственное состояние и присваивает исполнителя
     */
    #[Route('/admin/manufacture/part/scan/{id}', name: 'admin.scan', methods: ['GET', 'POST'])]
    public function action(
        Request $request,
        #[ParamConverter(ManufacturePartUid::class)] $id,
        ManufacturePartActionHandler $ManufacturePartActionHandler,
        ActiveWorkingManufacturePartInterface $activeWorkingManufacturePart,
        ManufacturePartCurrentEventInterface $ManufacturePartCurrentEvent,
    ): Response
    {
        $ManufacturePartEvent = $ManufacturePartCurrentEvent
            ->fromPart($id)
            ->find();

        if(false === ($ManufacturePartEvent instanceof ManufacturePartEvent))
        {
            throw new InvalidArgumentException('Invalid Argument ManufacturePartEvent');
        }


        /**
         * Получаем этап производства партии, который необходимо выполнить
         */
        $working = $activeWorkingManufacturePart->findNextWorkingByManufacturePart($ManufacturePartEvent->getMain());

        /**
         * Если все этапы выполнены - получаем все выполненные этапы
         */
        if(false === ($working instanceof UsersTableActionsWorkingUid))
        {
            $this->addFlash(
                'admin.page.action',
                'Производственной партии требующей выполнения не найдено',
                'manufacture-part.admin',
            );

            return $this->redirectToReferer();
        }

        $ManufacturePartActionDTO = new ManufacturePartActionDTO();
        $ManufacturePartEvent->getDto($ManufacturePartActionDTO);

        $ManufacturePartActionDTO
            ->getWorking()
            ->setWorking($working)
            ->setProfile($this->getCurrentProfileUid());

        // Форма
        $form = $this
            ->createForm(
                type: ManufacturePartActionForm::class,
                data: $ManufacturePartActionDTO,
                options: ['action' => $this->generateUrl('manufacture-part:admin.action', ['id' => $ManufacturePartEvent->getMain()]),],
            )
            ->handleRequest($request);

        if($form->isSubmitted() && $form->isValid() && $form->has('manufacture_part_action'))
        {
            $handle = $ManufacturePartActionHandler
                ->handle($ManufacturePartActionDTO);

            if($handle instanceof ManufacturePart)
            {
                $this->refreshTokenForm($form);

                $this->addFlash(
                    'admin.page.action',
                    'admin.success.action',
                    'manufacture-part.admin',
                );

                return $this->redirectToReferer();
            }

            $this->addFlash(
                'admin.page.action',
                'admin.danger.action',
                'manufacture-part.admin',
                $handle);

            return $this->redirectToReferer();
        }


        $this->addFlash(
            'admin.page.action',
            'Необходимо выполнить сканирование QR-кода',
            'manufacture-part.admin',
        );

        return $this->redirectToReferer();
    }
}
