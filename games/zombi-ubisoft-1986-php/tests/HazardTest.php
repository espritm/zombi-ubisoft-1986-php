<?php
namespace App\Tests;

use App\Model\World;
use PHPUnit\Framework\TestCase;

final class HazardTest extends TestCase
{
    private function world(int $room=38): World
    {
        $w=new World(json_decode(file_get_contents(__DIR__.'/../assets/original/game.json'),true,512,JSON_THROW_ON_ERROR));
        $w->party[3]['position']=array_search($room,$w->map,true); $w->electricity=true;
        $w->party[3]['bag']=[51,33,45,48]; $w->party[3]['held']=43; $w->party[3]['used']=43;
        return $w;
    }

    private function shock(World $w): void
    {
        $w->rooms[38]['cells'][32]=22; $w->containers[22]=[0];
        $w->chooseAction(2); $w->activateAction(); $w->confirm(); $w->row=3; $w->col=2; $w->confirm();
    }

    public function testFallingPlacesBodyBelowEachFacadeWithoutMovingThePartyRecord(): void
    {
        foreach ([[35,64,5],[38,66,3],[46,65,0]] as [$source,$destination,$direction]) {
            $w=$this->world($source); $w->rooms[$source]['prefix'][[5=>3,3=>1,0=>0][$direction]]=250;
            $w->rooms[$destination]['cells']=array_fill(0,40,255); $w->rooms[$destination]['cells'][29]=$w->rooms[$destination]['cells'][30]=0;
            $before=$w->person(); $sourceRoom=$w->rooms[$source]; $other=$w->party[0];
            self::assertFalse($w->move($direction)); self::assertSame(255,$w->person()['state']);
            self::assertSame($before['position'],$w->person()['position']); self::assertSame($before['gauges'],$w->person()['gauges']);
            self::assertSame([32,32],array_slice($w->rooms[$destination]['cells'],29,2));
            self::assertSame($sourceRoom,$w->rooms[$source]); self::assertSame([51,33,45,48,43],$w->containers[32]);
            self::assertSame([0,0,0,0],$w->person()['bag']); self::assertSame(0,$w->person()['held']);
            self::assertSame(0,$w->person()['used']); self::assertSame($other,$w->party[0]);
            self::assertSame('character',$w->mode); self::assertSame('',$w->ending);
        }
    }

    public function testFallTransfersWithoutPlacementAndBeforeLastSurvivorDefeat(): void
    {
        foreach ([false,true] as $last) {
            $w=$this->world(46); $w->rooms[65]['cells']=array_fill(0,40,255);
            if ($last) { for ($i=0;$i<3;$i++) { $w->party[$i]['state']=255; } }
            $w->party[3]['bag']=[51,0,45,0]; self::assertFalse($w->move(0));
            self::assertNotContains(32,$w->rooms[65]['cells']); self::assertSame([51,45,43,0,0],$w->containers[32]);
            self::assertSame($last?'defeat':'',$w->ending);
        }
    }

    public function testHeldRopeDoesNotProtectButActiveRopeKeepsItsTraversal(): void
    {
        foreach ([false,true] as $active) {
            $w=$this->world(46); $w->party[3]['held']=41; $w->party[3]['used']=$active?41:0;
            self::assertSame($active,$w->move(0)); self::assertSame($active?0:255,$w->person()['state']);
            self::assertSame($active?65:46,$w->roomId());
            if ($active) { self::assertContains(41,$w->rooms[46]['cells']); self::assertSame([0,0,0,0,0],$w->containers[32]); }
            else { self::assertSame(41,$w->containers[32][4]); }
        }
    }

    public function testShockUsesSequentialByteChecksIncludingExactZeroAndHighByteEdges(): void
    {
        foreach ([[19,80,19,80,true],[20,80,0,70,false],[70,9,50,9,true],[70,10,50,0,false],
            [20,10,0,0,false],[19,9,19,9,true],[20,9,0,9,true],[0,80,0,80,true],
            [70,80,50,70,false],[147,80,127,70,false],[148,80,148,80,true],[70,137,50,127,false],[70,138,50,138,true]] as [$f,$h,$ef,$eh,$fatal]) {
            $w=$this->world(); $w->party[3]['gauges']=[$f,$h,80]; $w->party[3]['ticks']=321.0; $position=$w->person()['position'];
            $this->shock($w); self::assertSame([$ef,$eh,80],$w->person()['gauges']); self::assertSame($fatal?255:0,$w->person()['state']);
            self::assertSame(321.0,$w->person()['ticks']); self::assertSame($position,$w->person()['position']);
            self::assertSame([0],$w->containers[22]); self::assertSame($fatal?'character':'normal',$w->mode);
            self::assertSame($fatal?[51,33,45,48,43]:[0,0,0,0,0],$w->containers[32]);
            self::assertSame($fatal?[0,0,0,0]:[51,33,45,48],$w->person()['bag']);
            self::assertSame($fatal?0:43,$w->person()['used']); self::assertSame(0,$w->rooms[38]['prefix'][8]);
            $w->advance(600); self::assertNotSame(128,$w->party[3]['state']);
        }
    }

    public function testFatalShockTransfersEvenWhenNoTwoCellsAreFree(): void
    {
        $w=$this->world(); $w->rooms[38]['cells']=array_fill(0,40,255); $w->party[3]['gauges'][0]=19;
        $this->shock($w); self::assertNotContains(32,$w->rooms[38]['cells']); self::assertSame([51,33,45,48,43],$w->containers[32]);
    }

    public function testLastFatalShockEndsBeforeBodyPlacementAndTransfer(): void
    {
        $w=$this->world(); for ($i=0;$i<3;$i++) { $w->party[$i]['state']=255; }
        $w->party[3]['gauges'][0]=19; $before=$w->person(); $this->shock($w);
        self::assertSame('defeat',$w->ending); self::assertSame(255,$w->person()['state']); self::assertSame(0,$w->person()['used']);
        self::assertSame($before['bag'],$w->person()['bag']); self::assertSame($before['held'],$w->person()['held']);
        self::assertSame([0,0,0,0,0],$w->containers[32]); self::assertNotContains(32,$w->rooms[38]['cells']);
    }

    public function testOnlyActiveGlovesProtectAndAllowFuseInstallation(): void
    {
        foreach ([false,true] as $active) {
            $w=$this->world(); $w->party[3]['held']=45; $w->party[3]['used']=$active?45:43;
            $this->shock($w); self::assertSame($active?[70,80,80]:[50,70,80],$w->person()['gauges']);
            self::assertSame([$active?51:0],$w->containers[22]); self::assertSame($active?0:51,$w->person()['bag'][0]);
            self::assertSame(0,$w->person()['state']);
        }
    }

    public function testHazardInventoriesAndLegacy016SavesSurviveReload(): void
    {
        $w=$this->world(46); $w->move(0); $save=$w->export(); $copy=$this->world(); self::assertTrue($copy->restore($save));
        self::assertSame($save,$copy->export()); self::assertSame(255,$copy->party[3]['state']);
        $save['version']='0.16.0'; self::assertTrue($copy->restore($save)); self::assertSame($w->containers,$copy->containers);
        self::assertTrue($copy->selectPerson(0)); self::assertSame(62,$copy->roomId());
    }
}
