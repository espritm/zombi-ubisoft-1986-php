<?php
namespace App\Tests;

use App\Model\World;
use PHPUnit\Framework\TestCase;

/** Isolated controls complement the native watched-write verification. */
final class EnemyClockTest extends TestCase
{
    private function world(): World
    {
        return new World(json_decode(file_get_contents(__DIR__.'/../assets/original/game.json'),true,512,JSON_THROW_ON_ERROR));
    }

    private function isolated(): World
    {
        $w=$this->world();$save=$w->export();$save['enemySeed']=0;self::assertTrue($w->restore($save));
        for($id=1;$id<=62;$id++){
            for($field=0;$field<6;$field++){$w->rooms[$id]['prefix'][$field]=30;}
            $w->rooms[$id]['prefix'][6]=0;
        }
        foreach([[19,0],[31,1],[32,3]] as [$id,$field]){$w->rooms[$id]['prefix'][$field]=10;}
        return $w;
    }

    public function testCadenceIsIndependentOfDisplayedHourAndCanResumeExactly(): void
    {
        $w=$this->isolated();$w->rooms[18]['prefix'][6]=1;$w->rooms[18]['prefix'][1]=20;
        $w->rooms[19]['hp']=7;$w->rooms[19]['strength']=3;
        $w->advance(302.0);self::assertSame(1,$w->rooms[18]['prefix'][6]);self::assertSame(0,$w->rooms[19]['prefix'][6]);
        $copy=$this->world();self::assertTrue($copy->restore($w->export()));
        $w->advance(0.08);$copy->advance(0.08);
        self::assertSame(0,$w->rooms[18]['prefix'][6]);self::assertSame(1,$w->rooms[19]['prefix'][6]);
        self::assertGreaterThanOrEqual(26,$w->rooms[19]['hp']);self::assertLessThanOrEqual(41,$w->rooms[19]['hp']);
        self::assertSame(58,$w->rooms[19]['hp']+$w->rooms[19]['strength']);self::assertFalse($w->rooms[19]['shooter']);
        self::assertSame($w->export(),$copy->export());self::assertEqualsWithDelta(0,$w->export()['enemyTicks'],0.001);
    }

    public function testFiveEnemyCapAndLiftBoundary(): void
    {
        $w=$this->isolated();$w->rooms[18]['prefix'][6]=1;$w->rooms[18]['prefix'][1]=20;$w->rooms[19]['prefix'][6]=5;
        $w->rooms[33]['prefix'][6]=1;$w->rooms[33]['prefix'][2]=0;$lift=$w->rooms[63]['prefix'][6];
        $w->advance(302.08);
        self::assertSame(1,$w->rooms[18]['prefix'][6]);self::assertSame(5,$w->rooms[19]['prefix'][6]);
        self::assertSame(1,$w->rooms[33]['prefix'][6]);self::assertSame($lift,$w->rooms[63]['prefix'][6]);
    }

    public function testUnusedRandomDirectionLetsEnemyStay(): void
    {
        $w=$this->isolated();$save=$w->export();$save['enemySeed']=2;self::assertTrue($w->restore($save));
        $w->rooms[18]['prefix'][6]=1;$w->rooms[18]['prefix'][1]=20;$w->advance(302.08);
        self::assertSame(1,$w->rooms[18]['prefix'][6]);self::assertSame(0,$w->rooms[19]['prefix'][6]);
    }

    public function testClosedDoorAllowsNewEntrantsButBarricadeStopsThem(): void
    {
        foreach([10=>0,30=>3] as $flag=>$incoming){
            $w=$this->isolated();foreach([[19,0],[31,1],[32,3]] as [$id,$field]){$w->rooms[$id]['prefix'][$field]=$flag;}
            $w->advance(302.08);self::assertSame(34+$incoming,$w->export()['zombieTarget']);
            self::assertSame($incoming,array_sum(array_map(fn($id)=>$w->rooms[$id]['prefix'][6],[19,31,32])));
        }
    }

    public function testSaturatedEntrancesDoNotIncreaseQuota(): void
    {
        $w=$this->isolated();foreach([[19,0],[31,1],[32,3]] as [$id,$field]){$w->rooms[$id]['prefix'][$field]=30;$w->rooms[$id]['prefix'][6]=5;}
        $w->advance(302.08);self::assertSame(34,$w->export()['zombieTarget']);
        foreach([19,31,32] as $id){self::assertSame(5,$w->rooms[$id]['prefix'][6]);}
    }

    public function testNewEntrantsRaiseTheFreezerRequirement(): void
    {
        $w=$this->isolated();foreach([[19,0],[31,1],[32,3]] as [$id,$field]){$w->rooms[$id]['prefix'][$field]=30;}
        $w->advance(302.08);self::assertSame(37,$w->export()['zombieTarget']);
        foreach([64,65,66] as $id){$w->rooms[$id]['prefix'][9]=77;}
        $w->frozenBodies=36;$w->advance(0);self::assertFalse($w->raidersArrived);
        $w->frozenBodies=37;$w->advance(0);self::assertTrue($w->raidersArrived);
    }

    public function testPausedTimeAndOldSaveMigration(): void
    {
        $w=$this->world();$w->mode='pause';$before=$w->export();$w->advance(1000);self::assertSame($before,$w->export());
        $legacy=$before;$legacy['version']='0.6.0';unset($legacy['enemyTicks'],$legacy['enemySeed']);
        self::assertTrue($w->restore($legacy));self::assertSame(34,$w->export()['zombieTarget']);self::assertSame(0.0,$w->export()['enemyTicks']);
    }
}
