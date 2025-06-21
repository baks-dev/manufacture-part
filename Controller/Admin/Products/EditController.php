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

namespace BaksDev\Manufacture\Part\Controller\Admin\Products;


use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Manufacture\Part\Entity\Products\ManufacturePartProduct;
use BaksDev\Manufacture\Part\UseCase\Admin\Products\Edit\ManufacturePartProductEditDTO;
use BaksDev\Manufacture\Part\UseCase\Admin\Products\Edit\ManufacturePartProductEditForm;
use BaksDev\Manufacture\Part\UseCase\Admin\Products\Edit\ManufacturePartProductEditHandler;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[RoleSecurity('ROLE_MANUFACTURE_PART_EDIT')]
final class EditController extends AbstractController
{
    #[Route('/admin/manufacture/part/product/edit/{id}', name: 'admin.products.edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        #[MapEntity] ManufacturePartProduct $ManufacturePartProduct,
        ManufacturePartProductEditHandler $ManufacturePartProductEditHandler,
    ): Response
    {
        $ManufacturePartProductDTO = new ManufacturePartProductEditDTO();
        $ManufacturePartProduct->getDto($ManufacturePartProductDTO);

        // Форма
        $form = $this->createForm(ManufacturePartProductEditForm::class, $ManufacturePartProductDTO, [
            'action' => $this->generateUrl('manufacture-part:admin.products.edit', ['id' => $ManufacturePartProductDTO->getId()]),
        ]);
        $form->handleRequest($request);


        if($form->isSubmitted() && $form->isValid() && $form->has('manufacture_part_product_edit'))
        {
            $this->refreshTokenForm($form);

            $handle = $ManufacturePartProductEditHandler->handle(
                $ManufacturePartProductDTO,
                $this->isGranted('ROLE_ADMIN') ? null : $this->getCurrentProfileUid()
            );

            $this->addFlash(
                'admin.page.product_edit',
                $handle instanceof ManufacturePartProduct ? 'admin.success.product_edit' : 'admin.danger.product_edit',
                'manufacture-part.admin',
                $handle
            );

            return $this->redirectToReferer();
        }

        return $this->render(['form' => $form->createView()]);
    }
}
