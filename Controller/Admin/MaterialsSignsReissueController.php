<?php
/*
 *  Copyright 2026.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Materials\Sign\Controller\Admin;

use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Materials\Sign\Messenger\MaterialSignReissue\MaterialsSignsReissueMessage;
use BaksDev\Orders\Order\Entity\Event\OrderEvent;
use BaksDev\Orders\Order\Repository\ExistOrderEventByStatus\ExistOrderEventByStatusInterface;
use BaksDev\Orders\Order\Type\Status\OrderStatus\Collection\OrderStatusCompleted;
use BaksDev\Orders\Order\Type\Status\OrderStatus\Collection\OrderStatusPackage;
use BaksDev\Materials\Sign\Forms\MaterialsSignsReissue\MaterialsSignsReissueDTO;
use BaksDev\Materials\Sign\Forms\MaterialsSignsReissue\MaterialsSignsReissueForm;
use JsonException;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[RoleSecurity('ROLE_MATERIAL_SIGN_REISSUE')]
final class MaterialsSignsReissueController extends AbstractController
{
    /**
     * @throws JsonException
     */
    #[Route('/admin/material/signs/reissue/{id}', name: 'admin.reissue', methods: ['GET', 'POST'])]
    public function reissue(
        #[MapEntity] OrderEvent $orderEvent,
        Request $request,
        ExistOrderEventByStatusInterface $ExistOrderEventByStatusRepository,
        MessageDispatchInterface $MessageDispatch,
    ): Response
    {
        $materialsSignsReissueDTO = new MaterialsSignsReissueDTO()
            ->setOrder($orderEvent->getMain());

        $form = $this
            ->createForm(
                type: MaterialsSignsReissueForm::class,
                data: $materialsSignsReissueDTO,
                options: ['action' => $this->generateUrl(
                    'materials-sign:admin.reissue',
                    ['id' => $orderEvent->getId()],
                )],
            )
            ->handleRequest($request);

        if($form->isSubmitted() && $form->isValid() & $form->has('material_signs_reissue'))
        {
            /**
             * Проверяем, был ли или находится ли данный заказ в статусе "Упаковка"
             */

            $existsPackageStatus = $ExistOrderEventByStatusRepository
                ->forOrder($orderEvent->getMain())
                ->forStatus(OrderStatusPackage::STATUS)
                ->isExists();

            if(false === $existsPackageStatus)
            {
                $flash = $this->addFlash
                (
                    'page.reissue',
                    'danger.reissue.package',
                    'materials-sign.admin',
                );

                return $flash ?: $this->redirectToReferer();
            }


            /**
             * Проверяем, был ли данный заказ выполнен
             */

            $existsCompletedStatus = $ExistOrderEventByStatusRepository
                ->forOrder($orderEvent->getMain())
                ->forStatus(OrderStatusCompleted::STATUS)
                ->isExists();

            if(true === $existsCompletedStatus)
            {
                $this->addFlash
                (
                    'page.reissue',
                    'danger.reissue.completed',
                    'materials-sign.admin',
                );

                return new JsonResponse('Cannot reissue material signs on order completed', 400);
            }

            $materialsSignsReissueMessage = new MaterialsSignsReissueMessage(
                $orderEvent->getId(),
                $this->getUsr()?->getId(),
                $this->getProfileUid(),
            );


            /** Отправляем сообщение для перевыпуска честных знаков */
            $MessageDispatch->dispatch(message:$materialsSignsReissueMessage, transport: 'materials-sign');

            $this->addFlash
            (
                'page.reissue',
                'success.reissue',
                'materials-sign.admin',
            );

            return $this->redirectToReferer();
        }

        return $this->render(['form' => $form->createView()]);
    }
}