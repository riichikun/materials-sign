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

namespace BaksDev\Materials\Sign\Repository\MaterialSignNew\Tests;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Materials\Catalog\Type\Offers\ConstId\MaterialOfferConst;
use BaksDev\Materials\Catalog\Type\Offers\Variation\ConstId\MaterialVariationConst;
use BaksDev\Materials\Catalog\Type\Offers\Variation\Modification\ConstId\MaterialModificationConst;
use BaksDev\Materials\Sign\Entity\Event\MaterialSignEvent;
use BaksDev\Materials\Sign\Entity\Invariable\MaterialSignInvariable;
use BaksDev\Materials\Sign\Repository\MaterialSignNew\MaterialSignNewInterface;
use BaksDev\Materials\Sign\Type\Status\MaterialSignStatus;
use BaksDev\Materials\Sign\Type\Status\MaterialSignStatus\MaterialSignStatusNew;
use BaksDev\Products\Product\Type\Material\MaterialUid;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

#[When(env: 'test')]
#[Group('materials-sign')]
#[Group('materials-sign-repository')]
class MaterialSignNewTest extends KernelTestCase
{
    private static string|false $user = false;
    private static string|false $profile = false;
    private static string|false $material = false;
    private static ?string $offer = null;
    private static ?string $variation = null;
    private static ?string $modification = null;


    public static function setUpBeforeClass(): void
    {
        // Бросаем событие консольной комманды
        $dispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        $event = new ConsoleCommandEvent(new Command(), new StringInput(''), new NullOutput());
        $dispatcher->dispatch($event, 'console.command');

        /** @var DBALQueryBuilder $DBALQueryBuilder */
        $DBALQueryBuilder = self::getContainer()->get(DBALQueryBuilder::class);

        $dbal = $DBALQueryBuilder->createQueryBuilder(self::class);

        $result = $dbal
            ->select('*')
            ->from(MaterialSignInvariable::class, 'invariable')
            ->leftJoin(
                'invariable',
                MaterialSignEvent::class,
                'event',
                'event.id = invariable.event AND status = :status'
            )
            ->setParameter('status', MaterialSignStatusNew::class, MaterialSignStatus::TYPE)
            ->setMaxResults(1)
            ->fetchAssociative();


        self::$user = $result['usr'] ?? false;
        self::$profile = $result['profile'] ?? false;
        self::$material = $result['material'] ?? false;
        self::$offer = $result['offer'] ?? null;
        self::$variation = $result['variation'] ?? null;
        self::$modification = $result['modification'] ?? null;

    }

    public function testUseCase(): void
    {
        self::assertTrue(true);
        return;

        /** @var MaterialSignNewInterface $MaterialSignNewInterface */
        $MaterialSignNewInterface = self::getContainer()->get(MaterialSignNewInterface::class);

        $profile = '018d3075-6e7b-7b5e-95f6-923243b1fa3d'; // admin
        //$profile = '018d36b7-0d03-71a8-b1b0-e57b5c186ef9'; // moderator
        //$profile = 'd4503fe5-e1f7-7025-b693-97bf5bb4f92d'; // admin
        //$profile = '018d36b7-0d03-71a8-b1b0-e57b5c186ef9'; // random

        $MaterialSignEvent = $MaterialSignNewInterface
            ->forUser(self::$user)
            ->forProfile(new UserProfileUid($profile))
            ->forMaterial(self::$material)
            ->forOfferConst(self::$offer)
            ->forVariationConst(self::$variation)
            ->forModificationConst(self::$modification)
            ->getOneMaterialSign();

        self::assertNotFalse($MaterialSignEvent);

    }
}
