<?php
namespace App\Tests;

use App\Model\World;
use PHPUnit\Framework\TestCase;

final class CombatTest extends TestCase
{
    private function world(int $weapon=0): World
    {
        $w=new World(json_decode(file_get_contents(__DIR__.'/../assets/original/game.json'),true,512,JSON_THROW_ON_ERROR));
        $save=$w->export();$save['enemySeed']=0;self::assertTrue($w->restore($save));
        $w->party[3]['position']=array_search(41,$w->map,true);$w->party[3]['held']=$weapon;$w->party[3]['used']=$weapon;
        $w->rooms[41]['hp']=31;$w->rooms[41]['strength']=27;$w->rooms[41]['shooter']=false;$w->mode='combat';
        return $w;
    }

    private function rhythm(World $w,int $count,bool $alternating=true): void
    {
        for($i=0;$i<$count;$i++){$w->strike($alternating&&$i%2?-1:1);$w->advance(0.05);}
        $w->advance(0.000001);
    }

    private function shot(World $w,int $cell): void
    {
        $w->chooseAction(3);$w->activateAction();$w->aim=($cell-2+0.1)/56;$w->confirm();
    }

    public function testMeleeUsesTimedRhythmRatherThanInstantDamagePerKey(): void
    {
        $w=$this->world();$this->rhythm($w,12);self::assertSame(31,$w->current()['hp']);self::assertSame(3.0,$w->pressure);
        $this->rhythm($w,68);self::assertSame(27,$w->current()['hp']);self::assertSame(23,$w->current()['strength']);
        foreach([false,true] as $alternating){
            $slow=$this->world();
            for($i=0;$i<40;$i++){$slow->strike($alternating&&$i%2?-1:1);$slow->advance(0.1);}
            $slow->advance(0.000001);self::assertSame(0.0,$slow->pressure);self::assertSame(31,$slow->current()['hp']);
        }
    }

    public function testObservedWeaponBonusesAtTheSamePressurePeak(): void
    {
        foreach([0=>4,34=>7,35=>7,36=>8,37=>7,38=>9,39=>8] as $weapon=>$damage){
            $w=$this->world($weapon);$this->rhythm($w,80);
            self::assertSame(31-$damage,$w->current()['hp']);self::assertSame(27-$damage,$w->current()['strength']);
            self::assertSame($weapon,$w->person()['held']);
        }
    }

    public function testEnemyAttackIsConditionalAndDamageIsThreeToSix(): void
    {
        $w=$this->world();$w->advance(4);self::assertSame([70,80,80],$w->person()['gauges']);
        $hit=$this->world();$hit->rooms[41]['hp']=32;$hit->advance(4);$loss=70-$hit->person()['gauges'][0];
        self::assertGreaterThanOrEqual(3,$loss);self::assertLessThanOrEqual(6,$loss);self::assertSame(80-$loss,$hit->person()['gauges'][1]);
    }

    public function testNativeCentralShotsReduceHealthAndStrengthAndNeedAnotherShot(): void
    {
        $w=$this->world(34);$this->shot($w,28);
        self::assertSame(19,$w->current()['hp']);self::assertSame(15,$w->current()['strength']);self::assertSame('aim',$w->mode);
        self::assertSame(1.92,$w->shotDelay);$w->confirm();self::assertSame(19,$w->current()['hp']);
        $w->advance(1.92);$w->confirm();self::assertSame(7,$w->current()['hp']);self::assertSame(3,$w->current()['strength']);
        $w->advance(1.92);$w->confirm();self::assertSame(0,$w->current()['prefix'][6]);self::assertSame(1,$w->current()['prefix'][8]);
        self::assertSame([53,53],array_slice($w->current()['cells'],30,2));
    }

    public function testCounterIntuitiveNativeOutsideWindowBranchIsRetained(): void
    {
        $w=$this->world(34);$this->shot($w,26);self::assertSame(0,$w->current()['prefix'][6]);
        $trained=$this->world(34);$trained->party[3]['trained']=true;$this->shot($trained,26);self::assertSame(19,$trained->current()['hp']);
        $rifle=$this->world(36);$this->shot($rifle,26);self::assertSame(19,$rifle->current()['hp']);
    }

    public function testAimWidthUsesGaugeHRifleAndTrainingAndTimesOut(): void
    {
        $w=$this->world(34);self::assertSame(4,$w->aimWidth());$w->party[3]['trained']=true;self::assertSame(6,$w->aimWidth());
        $w->party[3]['held']=36;self::assertSame(7,$w->aimWidth());$w->party[3]['trained']=false;self::assertSame(5,$w->aimWidth());
        $w->party[3]['gauges'][1]=40;self::assertSame(3,$w->aimWidth());
        $w->chooseAction(3);$w->activateAction();$w->advance(10.24);self::assertSame('combat',$w->mode);
    }

    public function testDamagedEnemySavesAndVersion07StrengthMigrates(): void
    {
        $w=$this->world(34);$this->shot($w,28);$save=$w->export();$copy=$this->world();self::assertTrue($copy->restore($save));
        self::assertSame(19,$copy->current()['hp']);self::assertSame(15,$copy->current()['strength']);
        $bad=$save;$bad['rooms'][41]['strength']=-1;self::assertFalse($copy->restore($bad));self::assertSame($save['rooms'],$copy->export()['rooms']);
        $legacy=$save;$legacy['version']='0.7.0';foreach($legacy['rooms'] as &$r){unset($r['strength']);}unset($r);
        self::assertTrue($copy->restore($legacy));self::assertSame(27,$copy->current()['strength']);
    }
}
