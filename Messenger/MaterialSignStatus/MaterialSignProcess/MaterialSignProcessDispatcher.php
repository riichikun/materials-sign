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
 *
 */

declare(strict_types=1);

namespace BaksDev\Materials\Sign\Messenger\MaterialSignStatus\MaterialSignProcess;

use BaksDev\Core\Cache\AppCacheInterface;
use BaksDev\Core\Messenger\MessageDelay;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Orders\Order\Entity\Event\OrderEvent;
use BaksDev\Orders\Order\Repository\CurrentOrderEvent\CurrentOrderEventInterface;
use BaksDev\Materials\Sign\Entity\Event\MaterialSignEvent;
use BaksDev\Materials\Sign\Entity\MaterialSign;
use BaksDev\Materials\Sign\Repository\MaterialSignNew\MaterialSignNewInterface;
use BaksDev\Materials\Sign\UseCase\Admin\Status\MaterialSignProcessDTO;
use BaksDev\Materials\Sign\UseCase\Admin\Status\MaterialSignStatusHandler;
use BaksDev\Ozon\Orders\BaksDevOzonOrdersBundle;
use BaksDev\Ozon\Orders\Type\DeliveryType\TypeDeliveryDbsOzon;
use BaksDev\Ozon\Orders\Type\DeliveryType\TypeDeliveryFbsOzon;
use BaksDev\Ozon\Orders\Type\PaymentType\TypePaymentDbsOzon;
use BaksDev\Ozon\Orders\Type\PaymentType\TypePaymentFbsOzon;
use BaksDev\Users\Profile\TypeProfile\Type\Id\Choice\TypeProfileIndividual;
use BaksDev\Users\Profile\TypeProfile\Type\Id\Choice\TypeProfileOrganization;
use BaksDev\Users\Profile\TypeProfile\Type\Id\Choice\TypeProfileUser;
use BaksDev\Users\Profile\TypeProfile\Type\Id\Choice\TypeProfileWorker;
use BaksDev\Users\Profile\UserProfile\Repository\CurrentUserProfileEvent\CurrentUserProfileEventInterface;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Wildberries\Orders\BaksDevWildberriesOrdersBundle;
use BaksDev\Wildberries\Orders\Type\DeliveryType\TypeDeliveryDbsWildberries;
use BaksDev\Wildberries\Orders\Type\DeliveryType\TypeDeliveryFboWildberries;
use BaksDev\Wildberries\Orders\Type\DeliveryType\TypeDeliveryFbsWildberries;
use BaksDev\Wildberries\Orders\Type\PaymentType\TypePaymentDbsWildberries;
use BaksDev\Wildberries\Orders\Type\PaymentType\TypePaymentFboWildberries;
use BaksDev\Wildberries\Orders\Type\PaymentType\TypePaymentFbsWildberries;
use BaksDev\Yandex\Market\Orders\BaksDevYandexMarketOrdersBundle;
use BaksDev\Yandex\Market\Orders\Type\DeliveryType\TypeDeliveryDbsYaMarket;
use BaksDev\Yandex\Market\Orders\Type\DeliveryType\TypeDeliveryFbsYaMarket;
use BaksDev\Yandex\Market\Orders\Type\PaymentType\TypePaymentDbsYaMarket;
use BaksDev\Yandex\Market\Orders\Type\PaymentType\TypePaymentFbsYandex;
use DateInterval;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/** Ставит в резерв честный знак по заказу */
#[AsMessageHandler(priority: 0)]
final readonly class MaterialSignProcessDispatcher
{
    public function __construct(
        #[Target('materialsSignLogger')] private LoggerInterface $logger,
        private MaterialSignStatusHandler $MaterialSignStatusHandler,
        private MaterialSignNewInterface $MaterialSignNewRepository,
        private CurrentOrderEventInterface $CurrentOrderEventRepository,
        private CurrentUserProfileEventInterface $currentUserProfileEvent,
        private AppCacheInterface $Cache,
        private MessageDispatchInterface $MessageDispatch,
    ) {}

    public function __invoke(MaterialSignProcessMessage $message): void
    {
        /**
         * Получаем информацию о заказе
         */
        $currentOrderEvent = $this->CurrentOrderEventRepository
            ->forOrder($message->getOrder())
            ->find();

        if(false === ($currentOrderEvent instanceof OrderEvent))
        {
            $this->logger->warning(
                'Событие по идентификатору заказа не найдено',
                [var_export($message, true), self::class.':'.__LINE__],
            );

            return;
        }

        /**
         * Если тип заказа Wildberries, Озон, Яндекс, Авито
         * Присваиваем владельца честного знака в качестве продавца
         */

        if($this->isMarketplace($currentOrderEvent))
        {
            /**
             * При реализации через маркетплейсы SELLER всегда должен быть NULL
             * если указан SELLER - реализация только через корзину и собственную доставку
             *
             * @see MaterialSignInvariable
             *
             * Поиск любого доступного честного знака,
             * должен определится честный знак у которого свойство SELLER === NULL
             * для этого передаем тестовый идентификатор профиля
             *
             * @see MaterialSignNewRepository:244
             *
             */

            $materialSignEvent = $this->MaterialSignNewRepository
                ->forUser($message->getUser())
                ->forProfile(new UserProfileUid(UserProfileUid::TEST)) // передаем тестовый идентификатор для поиска по NULL
                ->forMaterial($message->getMaterial())
                ->forOfferConst($message->getOffer())
                ->forVariationConst($message->getVariation())
                ->forModificationConst($message->getModification())
                ->getOneMaterialSign();
        }
        else
        {
            $materialSignEvent = $this->MaterialSignNewRepository
                ->forUser($message->getUser())
                ->forProfile($message->getProfile())
                ->forMaterial($message->getMaterial())
                ->forOfferConst($message->getOffer())
                ->forVariationConst($message->getVariation())
                ->forModificationConst($message->getModification())
                ->getOneMaterialSign();
        }

        if(false === ($materialSignEvent instanceof MaterialSignEvent))
        {
            $this->logger->warning(
                'Честный знак на продукцию не найден',
                [var_export($message, true), self::class.':'.__LINE__],
            );

            return;
        }

        /**
         * Мьютекс на идентификатор честного знака
         */

        $cache = $this->Cache->init('materials-sign');
        $item = $cache->getItem((string) $materialSignEvent);

        /** Если идентификатор найден - пробуем через время */
        if(true === $item->isHit())
        {
            $this->MessageDispatch->dispatch(
                message: $message,
                stamps: [new MessageDelay('5 seconds')],
                transport: 'materials-sign',
            );

            return;
        }

        $item->expiresAfter(DateInterval::createFromDateString('1 minutes'));
        $item->set(true);
        $cache->save($item);

        /**
         * Резервируем «Честный знак»
         */


        $materialSignProcessDTO = new MaterialSignProcessDTO($message->getProfile(), $message->getOrder());
        $materialSignInvariableDTO = $materialSignProcessDTO->getInvariable();


        /** Если тип заказа Wildberries, Озон, Яндекс, Озон - Присваиваем владельца в качестве продавца */

        if($this->isMarketplace($currentOrderEvent))
        {
            $materialSignInvariableDTO
                ->setSeller($materialSignEvent->getProfile());
        }

        /**
         * Определяем тип профиля клиента
         */

        $userProfileEventUid = $currentOrderEvent->getClientProfile();
        $userProfileEvent = $this->currentUserProfileEvent->findByEvent($userProfileEventUid);
        $typeProfileUid = $userProfileEvent->getType();


        /**
         * Если тип клиента Сотрудник - присваиваем NULL (Не передаем и не списываем честный знак)
         */
        if(true === $typeProfileUid->equals(TypeProfileWorker::class))
        {
            $materialSignInvariableDTO->setNullSeller();
        }

        /**
         * Если тип клиента «Физ. лицо» - присваиваем идентификатор склада в качестве продавца
         */
        if(
            false === $this->isMarketplace($currentOrderEvent)
            && true === $typeProfileUid->equals(TypeProfileUser::class)
        )
        {
            $materialSignInvariableDTO
                ->setSeller($currentOrderEvent->getOrderProfile());
        }

        /**
         * Если тип профиля клиента «Организация» либо «Индивидуальный предприниматель»
         * присваиваем в качестве продавца профиль клиента (для передачи)
         */
        if(
            true === $typeProfileUid->equals(TypeProfileOrganization::class) ||
            true === $typeProfileUid->equals(TypeProfileIndividual::class)
        )
        {
            $materialSignInvariableDTO
                ->setSeller($userProfileEvent->getMain());
        }

        $materialSignEvent->getDto($materialSignProcessDTO);

        /** Присваиваем партию упаковки */
        $materialSignInvariableDTO->setPart($message->getPart());

        $handle = $this->MaterialSignStatusHandler->handle($materialSignProcessDTO);

        if(false === ($handle instanceof MaterialSign))
        {
            $this->logger->critical(
                sprintf('%s: Ошибка при обновлении статуса честного знака', $handle),
                [var_export($message, true), self::class.':'.__LINE__],
            );

            throw new InvalidArgumentException('Ошибка при обновлении статуса честного знака');
        }

        $this->logger->info(
            'Отметили Честный знак Process «В резерве»',
            [var_export($message, true), self::class.':'.__LINE__],
        );
    }

    public function isMarketplace(OrderEvent $currentOrderEvent): bool
    {
        /** Если тип заказа Wildberries, Озон, Яндекс, Озон - Присваиваем владельца в качестве продавца */

        if(class_exists(BaksDevYandexMarketOrdersBundle::class))
        {
            if(
                // Способ доставки Yandex
                (
                    $currentOrderEvent->isDeliveryTypeEquals(TypeDeliveryFbsYaMarket::TYPE) // FBS
                    || $currentOrderEvent->isDeliveryTypeEquals(TypeDeliveryDbsYaMarket::TYPE) // DBS
                )

                ||

                // Способ оплаты Yandex
                (
                    $currentOrderEvent->isPaymentTypeEquals(TypePaymentFbsYandex::TYPE) // FBS
                    || $currentOrderEvent->isPaymentTypeEquals(TypePaymentDbsYaMarket::TYPE) // DBS
                )

            )
            {
                return true;
            }
        }


        if(class_exists(BaksDevOzonOrdersBundle::class))
        {
            if(
                // Способ доставки Ozon
                (
                    $currentOrderEvent->isDeliveryTypeEquals(TypeDeliveryDbsOzon::TYPE) // DBS
                    || $currentOrderEvent->isDeliveryTypeEquals(TypeDeliveryFbsOzon::TYPE) // FBS
                )

                ||

                // Способ оплаты Ozon
                (
                    $currentOrderEvent->isPaymentTypeEquals(TypePaymentDbsOzon::TYPE) // DBS
                    || $currentOrderEvent->isPaymentTypeEquals(TypePaymentFbsOzon::TYPE) // FBS
                )


            )
            {
                return true;
            }
        }

        if(class_exists(BaksDevWildberriesOrdersBundle::class))
        {
            if(
                // Способ доставки Wildberries
                (
                    $currentOrderEvent->isDeliveryTypeEquals(TypeDeliveryDbsWildberries::TYPE) // DBS
                    || $currentOrderEvent->isDeliveryTypeEquals(TypeDeliveryFbsWildberries::TYPE) // FBS
                    || $currentOrderEvent->isDeliveryTypeEquals(TypeDeliveryFboWildberries::TYPE) // FBO
                )

                ||

                // Способ оплаты Wildberries
                (
                    $currentOrderEvent->isPaymentTypeEquals(TypePaymentDbsWildberries::TYPE) // DBS
                    || $currentOrderEvent->isPaymentTypeEquals(TypePaymentFbsWildberries::TYPE) // FBS
                    || $currentOrderEvent->isPaymentTypeEquals(TypePaymentFboWildberries::TYPE) // FBO
                )

            )
            {
                return true;
            }
        }

        return false;
    }
}
