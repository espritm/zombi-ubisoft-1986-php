<?php
namespace App\Tests;
use App\Model\World;
use PHPUnit\Framework\TestCase;

final class IllnessTest extends TestCase
{
    private function world(): World
    {
        return new World(json_decode(file_get_contents(__DIR__.'/../assets/original/game.json'),true,512,JSON_THROW_ON_ERROR));
    }
    private function period(World $w): void
    {
        $save=$w->export(); $save['healthTicks']=15103.0; self::assertTrue($w->restore($save)); $w->advance(0.020001);
    }
    public function testIllnessAffectsLivingAndSleepingButNotOtherStates(): void
    {
        foreach ([0,16,64,128,255] as $state) {
            $w=$this->world(); $w->party[0]['state']=$state; $w->party[0]['illness']=2;
            $this->period($w); self::assertSame($state<32?70:80,$w->party[0]['gauges'][2]);
            self::assertSame($state,$w->party[0]['state']); self::assertSame(2,$w->party[0]['illness']);
        }
        $w=$this->world(); $w->party[0]['exposure']=255; $this->period($w);
        self::assertSame(0,$w->party[0]['illness']); self::assertSame(80,$w->party[0]['gauges'][2]);
    }
    public function testZeroSurvivesOnePeriodAndUnderflowCausesDelayedDeath(): void
    {
        $w=$this->world(); $w->party[0]['illness']=1; $w->party[0]['gauges'][2]=10;
        $this->period($w); self::assertSame(0,$w->party[0]['state']); self::assertSame(0,$w->party[0]['gauges'][2]);
        $before=array_slice($w->party[0]['gauges'],0,2); $this->period($w);
        self::assertSame(64,$w->party[0]['state']); self::assertSame($before,array_slice($w->party[0]['gauges'],0,2));
        $active=$this->world(); $active->party[3]['illness']=1; $active->party[3]['gauges'][2]=9; $this->period($active);
        self::assertSame('character',$active->mode); self::assertSame(64,$active->person()['state']);
    }
    public function testCorpseCountAndAllFourBodyMarkersReduceSOnlyOnce(): void
    {
        foreach ([0,29,30,31,32] as $marker) {
            $w=$this->world();
            if ($marker===0) { $w->rooms[62]['prefix'][8]=3; }
            else { $w->rooms[62]['cells'][0]=$marker; $w->rooms[62]['cells'][1]=$marker; }
            $this->period($w); self::assertSame(76,$w->party[0]['gauges'][2]);
        }
        $w=$this->world(); $w->rooms[41]['prefix'][8]=1; $this->period($w);
        self::assertSame(80,$w->party[0]['gauges'][2]);
        $w->party[0]['inside']=90; $w->rooms[62]['prefix'][8]=1; $this->period($w);
        self::assertSame(76,$w->party[0]['gauges'][2]);
    }
    public function testCorpseUnderflowIsPermanentAndPreservesTheWrappedByte(): void
    {
        $w=$this->world(); $w->rooms[62]['prefix'][8]=1; $w->party[0]['gauges'][2]=3; $this->period($w);
        self::assertSame(255,$w->party[0]['state']); self::assertSame(255,$w->party[0]['gauges'][2]);
        $both=$this->world(); $both->rooms[62]['prefix'][8]=1; $both->party[0]['gauges'][2]=9; $both->party[0]['illness']=2;
        $this->period($both); self::assertSame(255,$both->party[0]['state']); self::assertSame(252,$both->party[0]['gauges'][2]);
    }
    public function testBusySelectorsDeferOnePassAndPauseStopsTheClock(): void
    {
        $w=$this->world(); $w->party[0]['illness']=2; $w->mode='inventory'; $w->advance(303);
        self::assertSame(80,$w->party[0]['gauges'][2]); self::assertSame(15150.0,$w->export()['healthTicks']);
        $w->mode='pause'; $w->advance(60); self::assertSame(15150.0,$w->export()['healthTicks']);
        $save=$w->export(); $copy=$this->world(); self::assertTrue($copy->restore($save)); $copy->advance(0);
        self::assertSame(70,$copy->party[0]['gauges'][2]); self::assertSame(0.0,$copy->export()['healthTicks']);
        $copy->advance(0); self::assertSame(70,$copy->party[0]['gauges'][2]);
    }
    public function testOnlyNonFatalZombieMeleeAddsWrappedExposure(): void
    {
        foreach ([false,true] as $raiders) {
            $w=$this->world(); $save=$w->export(); $save['enemySeed']=0; $save['raidersArrived']=$raiders;
            self::assertTrue($w->restore($save)); $w->party[3]['position']=array_search(41,$w->map,true);
            $w->party[3]['exposure']=250; $w->rooms[41]['hp']=255; $w->mode='combat'; $w->advance(4);
            $hit=70-$w->person()['gauges'][0]; self::assertGreaterThan(0,$hit);
            self::assertSame($raiders?250:((250+2*$hit)&255),$w->person()['exposure']);
        }
        $w=$this->world(); $w->party[0]['state']=128; $w->party[0]['gauges'][0]=255;
        $save=$w->export(); $save['enemySeed']=0; $save['raidersArrived']=true; self::assertTrue($w->restore($save));
        $w->mode='combat'; $w->advance(4); self::assertSame(2*(70-$w->person()['gauges'][0]),$w->person()['exposure']);
        $w=$this->world(); $w->party[0]['state']=128; $w->party[0]['gauges'][0]=255; $w->party[3]['gauges'][0]=1;
        $save=$w->export(); $save['enemySeed']=0; self::assertTrue($w->restore($save)); $w->mode='combat'; $w->advance(4);
        self::assertSame(64,$w->person()['state']); self::assertSame(0,$w->person()['exposure']);
    }
    public function testStrictSaveValidationAndLegacyMigration(): void
    {
        $w=$this->world(); $save=$w->export();
        foreach (['exposure','illness'] as $field) {
            foreach ([-1,256,'1',null] as $bad) { $invalid=$save; $invalid['party'][0][$field]=$bad; self::assertFalse($w->restore($invalid)); }
        }
        foreach ([-1,65536,'1',null,INF] as $bad) { $invalid=$save; $invalid['healthTicks']=$bad; self::assertFalse($w->restore($invalid)); }
        self::assertSame($save,$w->export());
        $old=$save; $old['version']='0.12.0'; $old['enemyTicks']=15000.0; unset($old['healthTicks']);
        foreach ($old['party'] as &$p) { unset($p['exposure'],$p['illness']); } unset($p);
        self::assertTrue($w->restore($old)); self::assertSame(15000.0,$w->export()['healthTicks']);
        self::assertSame(0,$w->party[0]['illness']); self::assertSame(0,$w->party[0]['exposure']);
    }
}
