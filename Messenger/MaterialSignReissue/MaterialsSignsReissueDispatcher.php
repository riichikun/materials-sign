<?php
/*
 * Copyright 2026.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Materials\Sign\Messenger\MaterialSignReissue;

use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Materials\Catalog\Repository\CurrentMaterialIdentifier\CurrentIdentifierMaterialByValueInterface;
use BaksDev\Materials\Sign\Entity\Event\MaterialSignEvent;
use BaksDev\Materials\Sign\Messenger\MaterialSignStatus\MaterialSignCancel\MaterialSignCancelMessage;
use BaksDev\Materials\Sign\Messenger\MaterialSignStatus\MaterialSignProcess\MaterialSignProcessMessage;
use BaksDev\Materials\Sign\Repository\MaterialSignProcessByOrder\MaterialSignProcessByOrderInterface;
use BaksDev\Materials\Sign\Type\Id\MaterialSignUid;
use BaksDev\Orders\Order\Entity\Event\OrderEvent;
use BaksDev\Products\Product\Repository\CurrentProductIdentifier\CurrentProductIdentifierByEventInterface;
use BaksDev\Products\Product\Repository\ProductMaterials\ProductMaterialsInterface;
use BaksDev\Products\Product\Type\Material\MaterialUid;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(priority: 0)]
final readonly class MaterialsSignsReissueDispatcher
{
    public function __construct(
        #[Target('materialsSignLogger')] private LoggerInterface $Logger,
        private MaterialSignProcessByOrderInterface $MaterialSignProcessByOrderRepository,
        private EntityManagerInterface $EntityManager,
        private MessageDispatchInterface $MessageDispatch,
        private ProductMaterialsInterface $ProductMaterialsRepository,
        private CurrentProductIdentifierByEventInterface $CurrentProductIdentifierByEventRepository,
        private CurrentIdentifierMaterialByValueInterface $CurrentIdentifierMaterialByValueRepository,
    ) {}


    /** Перевыпуск честных знаков на сырьё */
    public function __invoke(MaterialsSignsReissueMessage $message): void
    {
        $orderEventUid = $message->getOrderEvent();

        $orderEvent = $this->EntityManager->getRepository(OrderEvent::class)->find($orderEventUid);

        if(false === $orderEvent instanceof OrderEvent)
        {
            $this->Logger->critical(sprintf('Событие заказа %s не было найдено', $orderEventUid));
            return;
        }


        /**
         * Получаем все честные знаки для данного заказа
         */

        $signs = $this->MaterialSignProcessByOrderRepository
            ->forOrder($orderEvent->getMain())
            ->findAllByOrder();

        /** @var MaterialSignEvent $materialSignEvent*/
        foreach($signs as $materialSignEvent)
        {
            /** Отменяем честный знак */
            $this->MessageDispatch->dispatch(
                message: new MaterialSignCancelMessage(
                    $message->getProfile(),
                    $materialSignEvent->getId(),
                ),
                transport: 'materials-sign',
            );
        }


        // Идентификатор группы честных знаков
        $materialSignUid = new MaterialSignUid();

        foreach($orderEvent->getProduct() as $key => $orderProduct)
        {
            /** Делим партии для печати по 100 шт. */
            if(($key % 100) === 0)
            {
                $materialSignUid = new MaterialSignUid();
            }


            /** Получаем идентификаторы продукции */
            $currentProductIdentifier = $this->CurrentProductIdentifierByEventRepository
                ->forEvent($orderProduct->getProduct())
                ->forOffer($orderProduct->getOffer())
                ->forVariation($orderProduct->getVariation())
                ->forModification($orderProduct->getModification())
                ->find();


            /** Получаем список материалов продукции */

            $productMaterials = $this->ProductMaterialsRepository
                ->forEvent($orderProduct->getProduct())
                ->findAll();

            if(false === ($productMaterials || $productMaterials->valid()))
            {
                continue;
            }

            /** @var MaterialUid $productMaterial */
            foreach($productMaterials as $productMaterial)
            {
                /** Получаем материал согласно торговому предложению (value) */
                $currentMaterialDTO = $this->CurrentIdentifierMaterialByValueRepository
                    ->forMaterial($productMaterial)
                    ->forOfferValue($currentProductIdentifier->getOfferValue())
                    ->forVariationValue($currentProductIdentifier->getVariationValue())
                    ->forModificationValue($currentProductIdentifier->getModificationValue())
                    ->find();


                $total = $orderProduct->getTotal();

                for($i = 1; $i <= $total; $i++)
                {
                    $materialSignProcessMessage = new MaterialSignProcessMessage(
                        $orderEvent->getMain(),
                        $materialSignUid,
                        $message->getUser(),
                        $message->getProfile(),
                        $productMaterial,
                        $currentMaterialDTO->getOfferConst(),
                        $currentMaterialDTO->getVariationConst(),
                        $currentMaterialDTO->getModificationConst(),
                    );

                    $this->MessageDispatch->dispatch(message: $materialSignProcessMessage, transport: 'materials-sign');
                }
            }
        }
    }
}