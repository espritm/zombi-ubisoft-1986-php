<?php
namespace App\Tests;
use App\Model\World;
use PHPUnit\Framework\TestCase;

final class ManualWakeTest extends TestCase
{
    private function world(int $bed=57): World
    {
        $w=new World(json_decode(file_get_contents(__DIR__.'/../assets/original/game.json'),true,512,JSON_THROW_ON_ERROR));
        $w->electricity=true; $w->active=0;
        $w->party[0]['position']=$w->party[3]['position']=array_search(34,$w->map,true);
        $w->party[3]['state']=16; $w->party[3]['bed']=$bed; $w->party[3]['ticks']=1000.0;
        $w->party[3]['gauges'][2]=50; $w->party[3]['awakeHours']=20; $w->party[3]['foodHours']=5;
        foreach ($bed===57?[23,24]:[25,26] as $cell) { $w->rooms[34]['cells'][$cell]=$bed; }
        return $w;
    }
    private function wake(World $w,int $col=3): void
    {
        $w->chooseAction(7); $w->activateAction(); $w->row=2; $w->col=$col; $w->confirm();
    }
    public function testBothBedTypesWakeWithoutRecoveryOrCounterReset(): void
    {
        foreach ([57=>3,59=>5] as $bed=>$col) {
            $w=$this->world($bed); $before=$w->party[3]; $this->wake($w,$col);
            self::assertSame(0,$w->party[3]['state']); self::assertArrayNotHasKey('bed',$w->party[3]);
            foreach (['ticks','gauges','awakeHours','foodHours','position'] as $key) { self::assertSame($before[$key],$w->party[3][$key]); }
            self::assertSame($bed-1,$w->current()['cells'][20+$col]); self::assertSame(0,$w->active);
        }
    }
    public function testNativeSearchIgnoresTargetRoom(): void
    {
        $w=$this->world(); $w->party[3]['position']=array_search(62,$w->map,true); $roof=$w->rooms[62];
        $this->wake($w); self::assertSame(0,$w->party[3]['state']); self::assertSame(62,$w->roomId(3));
        self::assertSame($roof,$w->rooms[62]); self::assertSame(56,$w->rooms[34]['cells'][23]);
    }
    public function testOnlyTheFirstMatchingPartyRecordIsChanged(): void
    {
        $w=$this->world(); $w->party[0]['bed']=57; $before=$w->party[3]; $this->wake($w);
        self::assertArrayNotHasKey('bed',$w->party[0]); self::assertSame($before,$w->party[3]);
        self::assertSame(56,$w->rooms[34]['cells'][23]);
    }
    public function testTheNativeSearchDoesNotFilterPreviousState(): void
    {
        foreach ([0,16,64,128,255] as $state) {
            $w=$this->world(); $w->party[3]['state']=$state; $before=$w->party[3]['gauges']; $this->wake($w);
            self::assertSame(0,$w->party[3]['state']); self::assertSame($before,$w->party[3]['gauges']);
            self::assertSame(1000.0,$w->party[3]['ticks']);
        }
    }
    public function testFreeAndOrphanBedsDoNotWakeAnyoneOrFreezeTheSimulation(): void
    {
        foreach ([false,true] as $orphan) {
            $w=$this->world();
            if ($orphan) { unset($w->party[3]['bed']); }
            else { $w->rooms[34]['cells'][23]=$w->rooms[34]['cells'][24]=56; }
            $before=$w->party; $cells=$w->current()['cells']; $this->wake($w);
            self::assertSame($before,$w->party); self::assertSame($cells,$w->current()['cells']);
            self::assertSame('normal',$w->mode); $minutes=$w->minutes; $w->advance(0.02); self::assertGreaterThan($minutes,$w->minutes);
        }
    }
    public function testEarlyWakeDoesNotPreventForcedSleepAtTheNextFatiguePass(): void
    {
        $w=$this->world(); $w->party[3]['awakeHours']=26; $this->wake($w);
        $save=$w->export(); $save['healthTicks']=15103.0; self::assertTrue($w->restore($save)); $w->advance(0.020001);
        self::assertSame(16,$w->party[3]['state']); self::assertSame(27,$w->party[3]['awakeHours']);
        self::assertSame(0.0,$w->party[3]['ticks']); self::assertArrayNotHasKey('bed',$w->party[3]);
    }
    public function testASecondSleepStartsANewTimerAndKeepsFatigue(): void
    {
        $w=$this->world(); $this->wake($w); self::assertTrue($w->selectPerson(3));
        $w->chooseAction(6); $w->activateAction(); $w->row=2; $w->col=3; $w->confirm();
        self::assertSame(16,$w->person()['state']); self::assertSame(57,$w->person()['bed']);
        self::assertSame(0.0,$w->person()['ticks']); self::assertSame(20,$w->person()['awakeHours']);
    }
    public function testBedSaveValidationAndLegacyStaleAssociationMigration(): void
    {
        $w=$this->world(); $save=$w->export(); $copy=$this->world(); self::assertTrue($copy->restore($save));
        self::assertSame(57,$copy->party[3]['bed']); $this->wake($copy);
        self::assertTrue($w->restore($copy->export())); self::assertArrayNotHasKey('bed',$w->party[3]);
        self::assertSame(1000.0,$w->party[3]['ticks']);
        foreach ([0,56,58,'57',null,[]] as $bad) { $invalid=$save; $invalid['party'][3]['bed']=$bad; self::assertFalse($w->restore($invalid)); }
        $old=$save; $old['version']='0.14.0'; $old['party'][3]['state']=0; self::assertTrue($w->restore($old));
        self::assertArrayNotHasKey('bed',$w->party[3]);
        $old['party'][3]['state']=16; self::assertTrue($w->restore($old)); self::assertSame(57,$w->party[3]['bed']);
        $old['party'][3]['bed']='57'; self::assertFalse($w->restore($old));
    }
}
