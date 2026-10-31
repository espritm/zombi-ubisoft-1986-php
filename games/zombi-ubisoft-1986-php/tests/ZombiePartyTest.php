<?php
namespace App\Tests;

use App\Model\World;
use PHPUnit\Framework\TestCase;

final class ZombiePartyTest extends TestCase
{
    private function world(): World
    {
        $w = new World(json_decode(file_get_contents(__DIR__.'/../assets/original/game.json'),true,512,JSON_THROW_ON_ERROR));
        $save = $w->export(); $save['enemySeed'] = 0;
        self::assertTrue($w->restore($save));
        $w->party[0]['state'] = 128; $w->party[0]['gauges'] = [50,40,80];
        return $w;
    }

    private function shoot(World $w, int $cell): void
    {
        $w->party[3]['held'] = 34; $w->chooseAction(3); $w->activateAction();
        self::assertSame('target',$w->mode); $w->choice=0; $w->confirm();
        self::assertSame('aim',$w->mode);
        $w->aim = ($cell-2+0.1)/56; $w->confirm();
    }

    public function testAutoCombatUsesPartyGaugesAndOrdinaryEnemiesTakePriority(): void
    {
        $w = $this->world(); self::assertSame(['person'=>0,'hp'=>50,'strength'=>40],$w->opponent());
        $w->advance(10.24); self::assertSame('combat',$w->mode);
        $w->advance(4); self::assertLessThan(70,$w->person()['gauges'][0]);
        $w->cancel(); $w->rooms[62]['prefix'][6] = 1;
        self::assertNull($w->opponent()['person']);
    }

    public function testOnlyZombiesInTheSameRoomAndVehicleCanAttack(): void
    {
        foreach ([0,16,64,255] as $state) {
            $w = $this->world(); $w->party[0]['state'] = $state;
            self::assertNull($w->opponent());
        }
        $w = $this->world(); $w->party[0]['position'] = array_search(41,$w->map,true);
        self::assertNull($w->opponent());
        $w->party[0]['position'] = $w->party[3]['position']; $w->party[0]['inside'] = 90;
        self::assertNull($w->opponent()); $w->party[3]['inside'] = 90;
        self::assertSame(0,$w->opponent()['person']);
        $w->party[1]['state'] = 128; $w->party[1]['inside'] = 90;
        self::assertSame(0,$w->opponent()['person']);
    }

    public function testMeleeKillsPermanentlyWithoutCreatingAnOrdinaryCorpse(): void
    {
        $w = $this->world(); $w->party[0]['gauges'][0] = 5; $before = $w->rooms[62];
        for ($i=0;$i<240 && $w->party[0]['state']===128;$i++) { $w->strike($i%2 ? -1 : 1); $w->advance(0.05); }
        self::assertSame(255,$w->party[0]['state']); self::assertSame(0,$w->party[0]['gauges'][0]);
        self::assertSame(0,$w->party[0]['used']); self::assertSame($before,$w->rooms[62]);
        self::assertSame('normal',$w->mode); self::assertNull($w->opponent());
        $w->advance(600); self::assertSame(255,$w->party[0]['state']);
    }

    public function testShotsUseTheNativeDamageAndOutsideWindowBranch(): void
    {
        $w = $this->world(); $before = $w->rooms[62]; $this->shoot($w,28);
        self::assertSame([38,28,80],$w->party[0]['gauges']); self::assertSame(128,$w->party[0]['state']);
        self::assertSame(1.92,$w->shotDelay);
        $w->advance(1.92); $w->aim = 0; $w->confirm();
        self::assertSame([0,28,80],$w->party[0]['gauges']); self::assertSame(255,$w->party[0]['state']);
        self::assertSame($before,$w->rooms[62]);
        $fatal = $this->world(); $fatal->party[0]['gauges'] = [50,6,80]; $this->shoot($fatal,28);
        self::assertSame([0,6,80],$fatal->party[0]['gauges']); self::assertSame(255,$fatal->party[0]['state']);
    }

    public function testWoundsAndPermanentDeathSurviveSaveAndOldVersionLoads(): void
    {
        $w = $this->world(); $this->shoot($w,28); $save = $w->export();
        $copy = $this->world(); self::assertTrue($copy->restore($save));
        self::assertSame([38,28,80],$copy->party[0]['gauges']); self::assertSame(0,$copy->opponent()['person']);
        $this->shoot($copy,2); $dead = $copy->export(); self::assertTrue($w->restore($dead));
        self::assertSame(255,$w->party[0]['state']); self::assertNull($w->opponent());
        $save['version'] = '0.9.0'; self::assertTrue($w->restore($save));
        self::assertSame([38,28,80],$w->party[0]['gauges']);
    }
}
