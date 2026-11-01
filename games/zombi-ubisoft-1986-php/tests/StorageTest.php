<?php
namespace App\Tests;
use App\Model\World;
use PHPUnit\Framework\TestCase;

final class StorageTest extends TestCase
{
    private function world(): World
    {
        $w=new World(json_decode(file_get_contents(__DIR__.'/../assets/original/game.json'),true,512,JSON_THROW_ON_ERROR));
        $w->electricity=true; $w->rooms[62]['cells'][24]=23; $w->rooms[62]['cells'][26]=$w->rooms[62]['cells'][27]=29;
        $w->containers[23]=[33,0]; $w->containers[29]=[33,0,45,0,0]; return $w;
    }
    private function put(World $w,int $item,int $target,int $choice=0): void
    {
        $w->chooseAction(2); $w->activateAction(); $w->choice=$choice; $w->confirm();
        if ($w->mode!=='object') { return; }
        $i=array_search($target,$w->current()['cells'],true); self::assertIsInt($i); $w->row=intdiv($i,10); $w->col=$i%10; $w->confirm();
    }
    private function open(World $w,int $target): void
    {
        $w->chooseAction(1); $w->activateAction(); self::assertSame('object',$w->mode);
        $i=array_search($target,$w->current()['cells'],true); self::assertIsInt($i); $w->row=intdiv($i,10); $w->col=$i%10; $w->confirm();
    }
    public function testTakeNeedsTwoFreeSlotsIncludingTheHeldSlot(): void
    {
        foreach ([[[43,33,45,48],34,false],[[43,33,45,48],0,false],[[43,33,45,0],34,false],[[43,33,45,0],0,true],[[43,33,0,0],34,true]] as [$bag,$held,$allowed]) {
            $w=$this->world(); $w->party[3]['bag']=$bag; $w->party[3]['held']=$held; $before=$w->export();
            $w->chooseAction(1); $w->activateAction(); self::assertSame($allowed?'object':'normal',$w->mode); self::assertSame($before,$w->export());
        }
    }
    public function testDepositsFillFirstFreeSlotAndRespectFourBodySlots(): void
    {
        foreach ([23,29] as $target) {
            $w=$this->world(); $w->party[3]['bag']=[43,0,0,0]; $w->party[3]['used']=43; $this->put($w,43,$target);
            self::assertSame(43,$w->containers[$target][1]); self::assertSame(0,$w->person()['bag'][0]); self::assertSame(0,$w->person()['used']);
            $w=$this->world(); $w->party[3]['bag']=[43,0,0,0]; $w->party[3]['used']=43;
            $w->containers[$target]=$target===29?[33,45,48,50,0]:[33,45]; $before=$w->export(); $this->put($w,43,$target);
            self::assertSame($before,$w->export());
        }
    }
    public function testFifthDeathTransferSlotRemainsLootableButIsNotAnExtraDepositSlot(): void
    {
        $w=$this->world(); $w->containers[29]=[33,45,48,50,43]; $this->open($w,29); $w->choice=5; $w->confirm();
        self::assertSame(43,$w->person()['bag'][0]); self::assertSame(0,$w->containers[29][4]);
        $this->put($w,43,29); self::assertSame(43,$w->person()['bag'][0]); self::assertSame(0,$w->containers[29][4]);
    }
    public function testBodiesInsideCupboardOrOtherBodyRetainTheirOwnContentsAcrossReload(): void
    {
        foreach ([23,29] as $target) {
            $w=$this->world(); $w->party[3]['bag']=[30,0,0,0]; $w->containers[30]=[37,38,0,0,43];
            $w->containers[$target]=$target===29?[0,0,0,0,0]:[0,0]; $this->put($w,30,$target);
            self::assertSame(30,$w->containers[$target][0]); self::assertSame(0,$w->person()['bag'][0]);
            $copy=$this->world(); self::assertTrue($copy->restore($w->export())); $this->open($copy,$target); $copy->choice=$target===29?1:0; $copy->confirm();
            self::assertSame(30,$copy->person()['bag'][0]); self::assertSame(0,$copy->containers[$target][0]); self::assertSame([37,38,0,0,43],$copy->containers[30]);
            self::assertContains($target,$copy->current()['cells']);
        }
    }
    public function testDuplicateDepositAlwaysClearsFirstBagMatchAndDeactivatesHeldCopy(): void
    {
        foreach ([0,3] as $choice) {
            $w=$this->world(); $w->party[3]['bag']=[43,33,0,0]; $w->party[3]['held']=43; $w->party[3]['used']=43;
            $this->put($w,43,23,$choice); self::assertSame([0,33,0,0],$w->person()['bag']); self::assertSame(43,$w->person()['held']);
            self::assertSame(0,$w->person()['used']); self::assertSame([33,43],$w->containers[23]);
        }
    }
    public function testFoodAndDrinkCannotBeDeposited(): void
    {
        foreach ([54,55] as $item) {
            $w=$this->world(); $w->party[3]['bag']=[$item,0,0,0]; $before=$w->export(); $this->put($w,$item,23);
            self::assertSame($before,$w->export()); self::assertSame('normal',$w->mode);
        }
    }
    public function testPortableContainersCanBeTakenAndPlacedWithoutLosingContents(): void
    {
        foreach ([27,28] as $id) {
            $w=$this->world(); foreach ([24,25,34,35] as $cell) { $w->rooms[62]['cells'][$cell]=$id; }
            $w->containers[$id][0]=33; $contents=$w->containers[$id];
            $this->open($w,$id); self::assertSame($id,$w->containerChoices()[0]); $w->confirm(); self::assertSame($id,$w->person()['bag'][0]);
            self::assertNotContains($id,$w->current()['cells']); self::assertSame($contents,$w->containers[$id]);
            $w->chooseAction(2); $w->activateAction(); $w->confirm(); $w->row=2; $w->col=4; $w->confirm();
            self::assertContains($id,$w->current()['cells']); self::assertSame($contents,$w->containers[$id]);
        }
    }
}
