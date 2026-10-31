<?php
namespace App\Tests;

use App\Model\World;
use PHPUnit\Framework\TestCase;

/** Negative controls accompany the complete fresh-game route in QuestRouteTest. */
final class ArrivalTest extends TestCase
{
    private function world(): World
    {
        return new World(json_decode(file_get_contents(__DIR__.'/../assets/original/game.json'),true,512,JSON_THROW_ON_ERROR));
    }

    private function putBody(World $w): void
    {
        $w->party[3]['bag']=[53,0,0,0];$w->chooseAction(2);$w->activateAction();$w->confirm();
    }

    public function testArrivalNeedsQuotaAndAllThreeParkedTrucksWithoutRadio(): void
    {
        foreach(['short','unparked','ready'] as $case){
            $w=$this->world();$w->party[3]['position']=array_search(11,$w->map,true);
            $w->frozenBodies=$case==='short'?32:33;
            foreach([64,65,66] as $room){$w->rooms[$room]['prefix'][9]=($case==='unparked'&&$room===66)?0:77;}
            $this->putBody($w);self::assertNotContains(53,$w->person()['bag']);
            self::assertSame($case==='ready',$w->raidersArrived);
            if($case==='ready'){
                self::assertSame(34,$w->frozenBodies);self::assertSame(5,$w->rooms[78]['cells'][17]);
                self::assertSame(78,$w->rooms[78]['prefix'][9]);self::assertSame(2,$w->rooms[78]['prefix'][6]);
                self::assertSame(20,$w->rooms[19]['prefix'][0]);self::assertSame(20,$w->rooms[65]['prefix'][2]);
                self::assertSame(0,$w->rooms[65]['prefix'][9]);self::assertSame(77,$w->rooms[77]['prefix'][9]);
                $w->rooms[78]['prefix'][6]=1;$w->advance(0.2);self::assertSame(1,$w->rooms[78]['prefix'][6]);
                $save=$w->export();$copy=$this->world();self::assertTrue($copy->restore($save));
                self::assertSame($save,$copy->export());
            }else{self::assertNotContains(5,$w->rooms[78]['cells']);}
        }
    }

    public function testSharedTorchRequiresSamePosition(): void
    {
        $w=$this->world();$w->party[3]['position']=array_search(17,$w->map,true);self::assertTrue($w->dark());
        $w->party[2]['used']=43;self::assertTrue($w->dark());
        $w->party[2]['position']=$w->person()['position'];self::assertFalse($w->dark());
        $w->party[2]['used']=0;self::assertTrue($w->dark());
    }

    public function testTopLandingStaysLockedWithoutToolAndOneKeyCellIsTaken(): void
    {
        $w=$this->world();$w->party[3]['position']=array_search(63,$w->map,true);$w->moveLift(4);
        $w->chooseAction(8);$w->activateAction();$w->requestDoorDirection();$w->confirm();
        self::assertSame(30,$w->rooms[59]['prefix'][2]);self::assertFalse($w->move(0));
        $w->party[3]['held']=49;$w->chooseAction(8);$w->activateAction();$w->requestDoorDirection();$w->confirm();
        self::assertSame(20,$w->rooms[59]['prefix'][2]);self::assertTrue($w->move(0));
        $before=count(array_keys($w->current()['cells'],48,true));$cell=array_search(48,$w->current()['cells'],true);
        self::assertNotFalse($cell);$w->chooseAction(1);$w->activateAction();$w->row=intdiv($cell,10);$w->col=$cell%10;$w->confirm();
        self::assertSame($before-1,count(array_keys($w->current()['cells'],48,true)));self::assertContains(48,$w->person()['bag']);
    }

    public function testCorpseDropAndLastPickupRetainOriginalEmptyMarkers(): void
    {
        $w=$this->world();$this->putBody($w);self::assertSame(1,$w->current()['prefix'][8]);
        self::assertSame([53,53],array_slice($w->current()['cells'],30,2));
        $w->chooseAction(1);$w->activateAction();$w->row=3;$w->col=0;$w->confirm();
        self::assertSame(0,$w->current()['prefix'][8]);self::assertSame([255,255],array_slice($w->current()['cells'],30,2));
        self::assertContains(53,$w->person()['bag']);self::assertSame(0,$w->frozenBodies);
    }

    public function testProgressSaveValidationAndLegacyMigration(): void
    {
        $w=$this->world();$save=$w->export();
        foreach(['frozenBodies'=>-1,'raidersArrived'=>1,'zombieTarget'=>35] as $field=>$value){
            $bad=$save;$bad[$field]=$value;self::assertFalse($w->restore($bad));self::assertSame($save,$w->export());
        }
        $legacy=$save;$legacy['version']='0.5.0';unset($legacy['frozenBodies'],$legacy['raidersArrived'],$legacy['zombieTarget']);
        self::assertTrue($w->restore($legacy));self::assertSame(0,$w->frozenBodies);self::assertFalse($w->raidersArrived);
    }
}
