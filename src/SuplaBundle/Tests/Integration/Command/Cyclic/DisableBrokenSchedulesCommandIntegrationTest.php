<?php
/*
 Copyright (C) AC SOFTWARE SP. Z O.O.

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 as published by the Free Software Foundation; either version 2
 of the License, or (at your option) any later version.
 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.
 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 */

namespace SuplaBundle\Tests\Integration\Command;

use SuplaBundle\Entity\Schedule;
use SuplaBundle\Entity\User;
use SuplaBundle\Enums\ChannelFunction;
use SuplaBundle\Enums\ChannelType;
use SuplaBundle\Enums\ScheduleMode;
use SuplaBundle\Model\Schedule\ScheduleManager;
use SuplaBundle\Tests\Integration\IntegrationTestCase;
use SuplaBundle\Tests\Integration\Traits\SuplaApiHelper;

class DisableBrokenSchedulesCommandIntegrationTest extends IntegrationTestCase {
    use SuplaApiHelper;

    /** @var User */
    private $user;
    /** @var Schedule */
    private $schedule;

    protected function setUp() {
        $this->user = $this->createConfirmedUser();
        $location = $this->createLocation($this->user);
        $device = $this->createDevice($location, [[ChannelType::RELAY, ChannelFunction::LIGHTSWITCH]]);
        $this->schedule = $this->createSchedule($device->getChannels()[0], '*/5 * * * *', ['mode' => ScheduleMode::MINUTELY]);
        $this->container->get(ScheduleManager::class)->generateScheduledExecutions($this->schedule, '+1day');
    }

    public function testNotDisablingScheduleWithFutureExecutionsOnly() {
        $output = $this->executeCommand('supla:clean:disable-broken-schedules');
        $this->assertContains('Disabled 0 schedules', $output);
        $this->getEntityManager()->refresh($this->schedule);
        $this->assertTrue($this->schedule->getEnabled());
    }

    public function testDisablingScheduleIfALotOfFailedExecutions() {
        $this->getEntityManager()->getConnection()->executeQuery('UPDATE supla_scheduled_executions SET result=1');
        $output = $this->executeCommand('supla:clean:disable-broken-schedules');
        $this->assertContains('Disabled 1 schedules', $output);
        $this->getEntityManager()->refresh($this->schedule);
        $this->assertFalse($this->schedule->getEnabled());
    }

    public function testDoNotDisablingScheduleIfAtLeastOneSuccessful() {
        $this->getEntityManager()->getConnection()->executeQuery('UPDATE supla_scheduled_executions SET result=1');
        $this->getEntityManager()->getConnection()->executeQuery('UPDATE supla_scheduled_executions SET result=0 WHERE id=100');
        $output = $this->executeCommand('supla:clean:disable-broken-schedules');
        $this->assertContains('Disabled 0 schedules', $output);
    }

    public function testDisablingScheduleSuccessfulEntryLongTimeAgo() {
        $fiveWeeksAgo = date('Y-m-d H:i:s', strtotime('-5weeks'));
        $this->getEntityManager()->getConnection()->executeQuery('UPDATE supla_scheduled_executions SET result=1');
        $this->getEntityManager()->getConnection()->executeQuery(
            'UPDATE supla_scheduled_executions SET result=0, planned_timestamp="' . $fiveWeeksAgo . '" WHERE id=100'
        );
        $output = $this->executeCommand('supla:clean:disable-broken-schedules');
        $this->assertContains('Disabled 1 schedules', $output);
    }
}