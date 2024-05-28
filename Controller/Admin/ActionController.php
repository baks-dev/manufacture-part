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

namespace BaksDev\Manufacture\Part\Controller\Admin;


use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Manufacture\Part\Entity\ManufacturePart;
use BaksDev\Manufacture\Part\Repository\ActiveWorkingManufacturePart\ActiveWorkingManufacturePartInterface;
use BaksDev\Manufacture\Part\Repository\AllWorkingByManufacturePart\AllWorkingByManufacturePartInterface;
use BaksDev\Manufacture\Part\UseCase\Admin\Action\ManufacturePartActionDTO;
use BaksDev\Manufacture\Part\UseCase\Admin\Action\ManufacturePartActionForm;
use BaksDev\Manufacture\Part\UseCase\Admin\Action\ManufacturePartActionHandler;
use chillerlan\QRCode\QRCode;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
#[RoleSecurity('ROLE_MANUFACTURE_PART_ACTION')]
final class ActionController extends AbstractController
{
    /**
     * Меняет производственное состояние и присваивает исполнителя
     */
    #[Route('/admin/manufacture/part/action/{id}', name: 'admin.action', methods: ['GET', 'POST'])]
    public function action(
        Request $request,
        #[MapEntity] ManufacturePart $ManufacturePart,
        ManufacturePartActionHandler $ManufacturePartActionHandler,
        ActiveWorkingManufacturePartInterface $activeWorkingManufacturePart,
        AllWorkingByManufacturePartInterface $allWorkingByManufacturePart,
    ): Response
    {
        /**
         * Получаем все этапы данной категории производства
         */
        $all = $allWorkingByManufacturePart->fetchAllWorkingByManufacturePartAssociative($ManufacturePart->getId());

        /**
         * Получаем этап производства партии, который необходимо выполнить
         */
        $working = $activeWorkingManufacturePart->findNextWorkingByManufacturePart($ManufacturePart->getId());


        $data = sprintf('%s', $ManufacturePart->getId());

        /**
         * Если все этапы выполнены - получаем все выполненные этапы
         */
        if(!$working)
        {
            $all = $activeWorkingManufacturePart->fetchCompleteWorkingByManufacturePartAssociative($ManufacturePart->getId());

            return $this->render([
                'part' => $ManufacturePart,
                'current' => $working,
                'all' => $all,
                'qrcode' => (new QRCode())->render($data),
            ], file: 'completed.html.twig');
        }


        $ManufacturePartActionDTO = new ManufacturePartActionDTO($ManufacturePart->getEvent());
        $ManufacturePartWorkingDTO = $ManufacturePartActionDTO->getWorking();
        $ManufacturePartWorkingDTO->setWorking($working);

        // Форма
        $form = $this->createForm(ManufacturePartActionForm::class, $ManufacturePartActionDTO, [
            'action' => $this->generateUrl('manufacture-part:admin.action', ['id' => $ManufacturePart->getId()]),
        ]);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid() && $form->has('manufacture_part_action'))
        {
            $this->refreshTokenForm($form);

            $handle = $ManufacturePartActionHandler->handle($ManufacturePartActionDTO);

            if($handle instanceof ManufacturePart)
            {
                $this->addFlash(
                    'admin.page.action',
                    'admin.success.action',
                    'admin.manufacture.part'
                );

                return $this->redirectToRoute('manufacture-part:admin.manufacture');
            }

            $this->addFlash(
                'admin.page.action',
                'admin.danger.action',
                'admin.manufacture.part',
                $handle);

            return $this->redirectToReferer();
        }



        return $this->render([
            'form' => $form->createView(),
            'part' => $ManufacturePart,
            'current' => $working,
            'all' => $all,
            'qrcode' => (new QRCode())->render($data),
        ]);
    }
}
