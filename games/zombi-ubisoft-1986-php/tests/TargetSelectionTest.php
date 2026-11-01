<?php
namespace App\Tests;

use App\Model\World;
use PHPUnit\Framework\TestCase;

final class TargetSelectionTest extends TestCase
{
    private function world(): World
    {
        $w = new World(json_decode(file_get_contents(__DIR__.'/../assets/original/game.json'),true,512,JSON_THROW_ON_ERROR));
        $w->party[3]['held']=34; return $w;
    }

    private function select(World $w, int $index): void
    {
        $w->chooseAction(3); $w->activateAction(); self::assertSame('target',$w->mode);
        $w->choice=$index; $w->confirm();
    }

    public function testFirearmOpensTheNativeFourPersonSelector(): void
    {
        foreach ([34,35,36] as $weapon) {
            $w=$this->world(); $w->party[3]['held']=$weapon; $w->chooseAction(3); $w->activateAction();
            self::assertSame('target',$w->mode); self::assertSame(3,$w->choice);
            $w->changeChoice(1); self::assertSame(0,$w->choice); $w->changeChoice(-1); self::assertSame(3,$w->choice);
            self::assertStringContainsString('VIVANT',$w->targetLabel(3));
            $w->cancel(); self::assertSame('normal',$w->mode); self::assertSame(0,$w->party[3]['state']);
        }
        $w=$this->world(); $w->rooms[62]['prefix'][6]=1; $w->chooseAction(3); $w->activateAction();
        self::assertSame('aim',$w->mode); self::assertNull($w->opponent()['person']);
    }

    public function testLivingSleepingAndDeadTargetsAreEliminatedWithoutGaugeDamage(): void
    {
        foreach ([0,16,64] as $state) {
            $w=$this->world(); $w->party[0]['state']=$state; $before=$w->party[0]['gauges']; $room=$w->rooms[62];
            $w->party[0]['used']=43; $this->select($w,0);
            self::assertSame(255,$w->party[0]['state']); self::assertSame($before,$w->party[0]['gauges']);
            self::assertSame(0,$w->party[0]['used']); self::assertSame($room['prefix'],$w->rooms[62]['prefix']);
            self::assertSame([29,29],array_slice($w->rooms[62]['cells'],38,2));
            self::assertSame(3,$w->active); self::assertSame('normal',$w->mode);
        }
    }

    public function testSelfTargetEliminatesTheActiveSurvivorAndAllowsAnother(): void
    {
        $w=$this->world(); $before=$w->person()['gauges']; $this->select($w,3);
        self::assertSame(255,$w->party[3]['state']); self::assertSame($before,$w->person()['gauges']);
        self::assertSame('character',$w->mode); self::assertTrue($w->selectPerson(1));
        self::assertSame(1,$w->active);
        $save=$w->export(); $save['version']='0.10.0'; $copy=$this->world(); self::assertTrue($copy->restore($save));
        self::assertSame(255,$copy->party[3]['state']);
    }

    public function testAbsentOrPermanentlyDeadTargetsAreRejected(): void
    {
        foreach ([false,true] as $absent) {
            $w=$this->world();
            if ($absent) { $w->party[0]['position']=array_search(41,$w->map,true); }
            else { $w->party[0]['state']=255; }
            $before=$w->party[0]; $this->select($w,0);
            self::assertSame($before,$w->party[0]); self::assertSame('normal',$w->mode);
        }
    }

    public function testManualZombieTargetIsLockedAcrossNpcArrivalAndAimTimeout(): void
    {
        $w=$this->world(); $w->party[0]['state']=128; $w->party[1]['state']=128;
        $w->party[1]['gauges']=[50,40,80]; $w->party[1]['inside']=90;
        $this->select($w,1); self::assertSame('aim',$w->mode); self::assertSame(1,$w->opponent()['person']);
        $before=$w->party[0]; $w->rooms[62]['prefix'][6]=1;
        $w->aim=26.1/56; $w->confirm(); self::assertSame([38,28,80],$w->party[1]['gauges']);
        self::assertSame($before,$w->party[0]); self::assertSame(1,$w->current()['prefix'][6]);
        $w->advance(10.24); self::assertSame('combat',$w->mode); self::assertSame(1,$w->opponent()['person']);
        $save=$w->export(); self::assertTrue($w->restore($save)); self::assertNull($w->opponent()['person']);
        self::assertSame([38,28,80],$w->party[1]['gauges']);
    }
}
