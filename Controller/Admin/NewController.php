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


use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Manufacture\Part\Entity\ManufacturePart;
use BaksDev\Manufacture\Part\Repository\ExistManufacturePart\ExistOpenManufacturePartInterface;
use BaksDev\Manufacture\Part\UseCase\Admin\NewEdit\ManufacturePartDTO;
use BaksDev\Manufacture\Part\UseCase\Admin\NewEdit\ManufacturePartForm;
use BaksDev\Manufacture\Part\UseCase\Admin\NewEdit\ManufacturePartHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[RoleSecurity('ROLE_MANUFACTURE_PART_NEW')]
final class NewController extends AbstractController
{
    #[Route('/admin/manufacture/part/new', name: 'admin.newedit.new', methods: ['GET', 'POST'])]
    public function news(
        Request $request,
        ManufacturePartHandler $ManufacturePartHandler,
        ExistOpenManufacturePartInterface $existManufacturePartByAction
    ): Response
    {
        $ManufacturePartDTO = new ManufacturePartDTO()
            ->setFixed($this->getCurrentProfileUid());

        $ManufacturePartDTO
            ->getInvariable()
            ->setUsr($this->getUsr()?->getId())
            ->setProfile($this->getProfileUid());

        // Форма
        $form = $this
            ->createForm(
                type: ManufacturePartForm::class,
                data: $ManufacturePartDTO,
                options: ['action' => $this->generateUrl('manufacture-part:admin.newedit.new')]
            )
            ->handleRequest($request);

        if($form->isSubmitted() && $form->isValid() && $form->has('manufacture_part'))
        {
            $this->refreshTokenForm($form);

            /**
             * Проверяем, имеется ли открытая партия
             */
            if($existManufacturePartByAction->forProfile($this->getCurrentProfileUid())->isExistByProfile())
            {
                $this->addFlash
                (
                    'admin.page.new',
                    'admin.danger.exist',
                    'admin.manufacture.part'
                );

                return $this->redirectToReferer();
            }

            $handle = $ManufacturePartHandler->handle($ManufacturePartDTO);

            $this->addFlash
            (
                'admin.page.new',
                $handle instanceof ManufacturePart ? 'admin.success.new' : 'admin.danger.new',
                'admin.manufacture.part',
                $handle
            );

            return $this->redirectToReferer();
        }

        return $this->render(['form' => $form->createView()]);
    }
}