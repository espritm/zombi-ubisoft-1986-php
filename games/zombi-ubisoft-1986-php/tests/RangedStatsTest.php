<?php
namespace App\Tests;

use App\Model\World;
use PHPUnit\Framework\TestCase;

/** Controlled RNG/phase fixtures correspond to the native probe preconditions. */
final class RangedStatsTest extends TestCase
{
    private function world(): World
    {
        return new World(json_decode(file_get_contents(__DIR__.'/../assets/original/game.json'),true,512,JSON_THROW_ON_ERROR));
    }

    private function gunner(int $seed=57): World
    {
        $w=$this->world();$s=$w->export();$s['enemySeed']=$seed;$s['raidersArrived']=true;
        $s['party'][3]['position']=array_search(41,$s['map'],true);
        $s['rooms'][41]['hp']=35;$s['rooms'][41]['strength']=30;$s['rooms'][41]['shooter']=true;
        $s['alertTicks']=255.0;$s['rangedArmed']=true;$s['rangedPending']=false;
        self::assertTrue($w->restore($s));return $w;
    }

    public function testInitialZombiesUseTheNativeRanges(): void
    {
        $w=$this->world();foreach($w->rooms as $r){if(!$r['prefix'][6]){continue;}
            self::assertGreaterThanOrEqual(26,$r['hp']);self::assertLessThanOrEqual(41,$r['hp']);
            self::assertSame(58,$r['hp']+$r['strength']);self::assertFalse($r['shooter']);
        }
    }

    public function testNextOpponentUsesObservedRandomInputsAndShooterBoundary(): void
    {
        foreach([10=>0,31=>1,45=>7,2=>15] as $seed=>$raw){
            foreach([false,true] as $phase){
                $w=$this->world();$s=$w->export();$s['enemySeed']=$seed;$s['raidersArrived']=$phase;
                $s['party'][3]['position']=array_search(41,$s['map'],true);$s['party'][3]['held']=34;$s['rooms'][41]['prefix'][6]=2;
                self::assertTrue($w->restore($s));$w->chooseAction(3);$w->activateAction();$w->confirm();
                self::assertSame(($phase?30:26)+$raw,$w->current()['hp']);
                self::assertSame($phase?65:58,$w->current()['hp']+$w->current()['strength']);
            }
        }
        foreach([29177=>true,17774=>false] as $seed=>$armed){
            $w=$this->gunner($seed);$w->rooms[41]['prefix'][6]=2;$w->party[3]['held']=34;
            $w->chooseAction(3);$w->activateAction();$w->confirm();
            self::assertSame(35,$w->current()['hp']);self::assertSame(30,$w->current()['strength']);self::assertSame($armed,$w->current()['shooter']);
        }
    }

    public function testReplyWaitsFor256TicksAndCostsSixOnBothGauges(): void
    {
        $w=$this->gunner();$w->advance(0.019);self::assertSame([70,80,80],$w->person()['gauges']);
        $w->advance(0.002);self::assertSame([64,74,80],$w->person()['gauges']);self::assertFalse($w->export()['rangedPending']);
        $w->advance(0);self::assertSame([64,74,80],$w->person()['gauges']);
    }

    public function testBusyActionsQueueOneReplyAndSaveIt(): void
    {
        foreach(['inventory','aim','combat'] as $mode){
            $w=$this->gunner();$w->mode=$mode;$w->advance(0.02);
            self::assertTrue($w->export()['rangedPending']);self::assertSame([70,80,80],$w->person()['gauges']);
            $copy=$this->world();self::assertTrue($copy->restore($w->export()));$copy->advance(0);
            self::assertSame([64,74,80],$copy->person()['gauges']);self::assertFalse($copy->export()['rangedPending']);
        }
    }

    public function testWeakScoreLeavesReplyPendingAndRetriesWithoutAnotherPeriod(): void
    {
        $w=$this->gunner(83);$s=$w->export();$s['alertTicks']=0.0;$s['rangedPending']=true;self::assertTrue($w->restore($s));
        $w->advance(0);self::assertTrue($w->export()['rangedPending']);self::assertSame([70,80,80],$w->person()['gauges']);
        $s=$w->export();$s['enemySeed']=57;self::assertTrue($w->restore($s));$w->advance(0);
        self::assertFalse($w->export()['rangedPending']);self::assertSame([64,74,80],$w->person()['gauges']);
    }

    public function testFatalRangedDamagePreservesTheUnwrittenGauge(): void
    {
        foreach([[6,80],[70,6]] as [$f,$h]){
            $w=$this->gunner();$w->party[3]['gauges'][0]=$f;$w->party[3]['gauges'][1]=$h;$w->advance(0.02);
            self::assertSame([0,$h,80],$w->person()['gauges']);self::assertSame(64,$w->person()['state']);
        }
    }

    public function testAimTimeoutEntersMeleeWithoutDispatchingThePendingReply(): void
    {
        $w=$this->gunner();$w->mode='aim';$w->advance(10.24);
        self::assertSame('combat',$w->mode);self::assertTrue($w->export()['rangedPending']);
        self::assertSame([70,80,80],$w->person()['gauges']);
    }

    public function testUnarmedAndZombieCasesDoNotScheduleShotsAndIdleStartsMelee(): void
    {
        $w=$this->gunner();$s=$w->export();$s['rangedArmed']=false;$s['rooms'][41]['shooter']=false;self::assertTrue($w->restore($s));
        $w->advance(0.02);self::assertSame([70,80,80],$w->person()['gauges']);self::assertFalse($w->export()['rangedPending']);
        $s=$w->export();$s['alertTicks']=511.0;self::assertTrue($w->restore($s));$w->advance(0.02);
        self::assertSame('combat',$w->mode);self::assertSame([70,80,80],$w->person()['gauges']);
        $z=$this->world();$z->party[3]['position']=array_search(41,$z->map,true);$z->advance(5.12);
        self::assertFalse($z->export()['rangedPending']);self::assertSame([70,80,80],$z->person()['gauges']);
    }

    public function testAlertSaveValidationAndVersion08Migration(): void
    {
        $w=$this->gunner();$save=$w->export();
        foreach(['alertTicks'=>65536,'rangedPending'=>1,'rangedArmed'=>0] as $field=>$value){$bad=$save;$bad[$field]=$value;self::assertFalse($w->restore($bad));self::assertSame($save,$w->export());}
        $bad=$save;$bad['rooms'][41]['shooter']=1;self::assertFalse($w->restore($bad));
        $old=$save;$old['version']='0.8.0';unset($old['alertTicks'],$old['rangedPending'],$old['rangedArmed']);foreach($old['rooms'] as &$r){unset($r['shooter']);}unset($r);
        self::assertTrue($w->restore($old));self::assertSame(35,$w->current()['hp']);self::assertSame(30,$w->current()['strength']);
        self::assertSame(0.0,$w->export()['alertTicks']);self::assertFalse($w->current()['shooter']);
    }
}
