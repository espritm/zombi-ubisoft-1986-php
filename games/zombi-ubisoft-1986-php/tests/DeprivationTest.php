<?php
namespace App\Tests;
use App\Model\World;
use PHPUnit\Framework\TestCase;

final class DeprivationTest extends TestCase
{
    private function world(): World
    {
        return new World(json_decode(file_get_contents(__DIR__.'/../assets/original/game.json'),true,512,JSON_THROW_ON_ERROR));
    }
    private function period(World $w): void
    {
        $save=$w->export(); $save['healthTicks']=15103.0; self::assertTrue($w->restore($save)); $w->advance(0.020001);
    }
    public function testHungerThresholdAndByteWrapFollowPeriodicHealth(): void
    {
        foreach ([5=>80,6=>76,7=>76,254=>76,255=>80] as $before=>$s) {
            $w=$this->world(); $w->party[0]['foodHours']=$before; $this->period($w);
            self::assertSame(($before+1)&255,$w->party[0]['foodHours']); self::assertSame($s,$w->party[0]['gauges'][2]);
        }
        $w=$this->world(); $w->minutes+=180; $w->advance(0.01);
        self::assertSame(0,$w->party[0]['foodHours']); self::assertSame(0,$w->party[0]['awakeHours']);
    }
    public function testFatigueThresholdsAndTheNative22Branch(): void
    {
        foreach ([17=>0,18=>4,19=>4,20=>8,21=>12,22=>12,25=>12,255=>0] as $before=>$loss) {
            $w=$this->world(); $w->party[0]['awakeHours']=$before; $this->period($w);
            self::assertSame(($before+1)&255,$w->party[0]['awakeHours']); self::assertSame(80-$loss,$w->party[0]['gauges'][2]);
            self::assertSame(0,$w->party[0]['state']);
        }
    }
    public function testForcedSleepResetsTheTimerOnEveryPeriodRegardlessOfPreviousState(): void
    {
        foreach ([0,16,64,128,255] as $state) {
            $w=$this->world(); $w->party[0]['state']=$state; $w->party[0]['ticks']=1000;
            $w->party[0]['awakeHours']=26; $this->period($w);
            self::assertSame(16,$w->party[0]['state']); self::assertLessThan(2,$w->party[0]['ticks']);
            self::assertSame(27,$w->party[0]['awakeHours']); self::assertSame(80,$w->party[0]['gauges'][2]);
            $w->party[0]['ticks']=1000; $this->period($w); self::assertLessThan(2,$w->party[0]['ticks']);
            self::assertSame(28,$w->party[0]['awakeHours']);
        }
        $w=$this->world(); $w->party[3]['awakeHours']=26; $this->period($w);
        self::assertSame('character',$w->mode); self::assertSame(16,$w->person()['state']);
    }
    public function testSequentialUnderflowCanBePermanentOrBecomeIllnessDeath(): void
    {
        foreach (['alone'=>[255,255],'illness'=>[0,64],'corpse'=>[251,255]] as $case=>[$s,$state]) {
            $w=$this->world(); $w->party[0]['foodHours']=6; $w->party[0]['gauges'][2]=3;
            if ($case==='illness') { $w->party[0]['illness']=1; }
            if ($case==='corpse') { $w->rooms[62]['prefix'][8]=1; }
            $this->period($w); self::assertSame($s,$w->party[0]['gauges'][2]); self::assertSame($state,$w->party[0]['state']);
        }
    }
    public function testWakeAndConsumptionResetOnlyTheirOwnCounter(): void
    {
        $w=$this->world(); $w->party[0]['state']=16; $w->party[0]['ticks']=119807;
        $w->party[0]['awakeHours']=20; $w->party[0]['foodHours']=5; $w->party[0]['gauges'][2]=50;
        $w->advance(0.020001); self::assertSame(0,$w->party[0]['awakeHours']); self::assertSame(5,$w->party[0]['foodHours']);
        self::assertSame(58,$w->party[0]['gauges'][2]);
        foreach ([54,55] as $item) {
            $w=$this->world(); $w->party[3]['bag'][0]=$item; $w->party[3]['foodHours']=6; $w->party[3]['awakeHours']=20;
            $w->chooseAction(5); $w->activateAction(); $w->confirm();
            self::assertSame($item===55?0:6,$w->person()['foodHours']); self::assertSame(20,$w->person()['awakeHours']);
        }
    }
    public function testCountersAreSavedAndLegacyValuesMigrateAsBytes(): void
    {
        $w=$this->world(); $w->party[0]['foodHours']=6; $w->party[0]['awakeHours']=21;
        $save=$w->export(); $copy=$this->world(); self::assertTrue($copy->restore($save)); $this->period($copy);
        self::assertSame(64,$copy->party[0]['gauges'][2]);
        foreach (['foodHours','awakeHours'] as $field) {
            $bad=$save; $bad['party'][0][$field]=256; self::assertFalse($w->restore($bad));
            $bad['version']='0.13.0'; self::assertTrue($w->restore($bad)); self::assertSame(0,$w->party[0][$field]);
        }
    }
    public function testWakePrecedesACoincidentPeriodicFatiguePass(): void
    {
        $w=$this->world(); $w->party[0]['state']=16; $w->party[0]['ticks']=119807;
        $w->party[0]['awakeHours']=26; $w->party[0]['foodHours']=5; $w->party[0]['gauges'][2]=50;
        $this->period($w); self::assertSame(0,$w->party[0]['state']); self::assertSame(58,$w->party[0]['gauges'][2]);
        self::assertSame(1,$w->party[0]['awakeHours']); self::assertSame(6,$w->party[0]['foodHours']);
    }
}
