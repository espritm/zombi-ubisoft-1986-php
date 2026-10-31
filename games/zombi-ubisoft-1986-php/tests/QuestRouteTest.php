<?php
namespace App\Tests;

use App\Model\World;
use PHPUnit\Framework\TestCase;

/** Routes use normal game actions; no position, inventory, health or enemy writes. */
final class QuestRouteTest extends TestCase
{
    private function travel(World $w, int $room, bool $fight = false): void
    {
        $goal = array_search($room, $w->map, true);
        self::assertNotFalse($goal);
        $start = $w->person()['position']; $queue = [[$start, []]]; $seen = [$start => true];
        $deltas = [8,56,-8,1,-56,-1]; $fields = [0,5,2,1,4,3]; $route = null;
        for ($i = 0; $i < count($queue); $i++) {
            [$position,$steps] = $queue[$i];
            if ($position === $goal) { $route = $steps; break; }
            foreach ($deltas as $direction => $delta) {
                $next = $position + $delta;
                if (isset($seen[$next]) || !isset($w->map[$next]) ||
                    !in_array($w->rooms[$w->map[$position]]['prefix'][$fields[$direction]], [0,20], true)) { continue; }
                if ($direction === 0 && $w->map[$next] === 59 && !in_array($w->rooms[59]['prefix'][2],[0,20],true)) { continue; }
                $seen[$next] = true; $queue[] = [$next,array_merge($steps,[$direction])];
            }
        }
        self::assertNotNull($route, 'No ordinary route to room ' . $room);
        foreach ($route as $direction) { self::assertTrue($w->move($direction)); $w->advance(0.2); if($fight){$this->fight($w);} }
        self::assertSame($room,$w->roomId());
    }

    private function object(World $w, int $action, int $object): void
    {
        $cell = array_search($object,$w->current()['cells'],true); self::assertNotFalse($cell);
        $w->chooseAction($action); $w->activateAction();
        $w->row = intdiv($cell,10); $w->col = $cell%10; $w->confirm(); $w->advance(0.2);
    }

    public function testVideoRouteFromNewGameWithoutInjectedPositionsOrItems(): void
    {
        $w = new World(json_decode(file_get_contents(__DIR__.'/../assets/original/game.json'),true,512,JSON_THROW_ON_ERROR));
        self::assertSame(62,$w->roomId()); self::assertSame(3,$w->active);
        $this->travel($w,48); $this->object($w,1,50); self::assertContains(50,$w->person()['bag']);
        $this->travel($w,29); $this->object($w,4,60);
        $w->chooseAction(2); $w->activateAction();
        foreach ($w->inventoryChoices() as $i=>$entry) { if ($entry['item']===50) { $w->choice=$i; break; } }
        $w->confirm(); $cell=array_search(65,$w->current()['cells'],true); self::assertNotFalse($cell);
        $w->row=intdiv($cell,10);$w->col=$cell%10;$w->confirm();
        self::assertSame(50,$w->videoCassette); self::assertNotContains(50,$w->person()['bag']);
        $this->object($w,4,61); $before=$w->minutes; $this->object($w,4,65);
        self::assertEqualsWithDelta(80.04,$w->minutes-$before,0.001); self::assertTrue($w->person()['trained']);
        self::assertFalse($w->party[0]['trained']); $this->travel($w,62);
        self::assertSame(0,$w->person()['state']); self::assertSame(47,$w->party[0]['held']);
    }

    private function fight(World $w): void
    {
        for($i=0;$i<500 && $w->current()['prefix'][6]>0;$i++){
            self::assertSame(0,$w->person()['state']);
            if(in_array($w->person()['held'],[34,35,36],true)){
                $w->chooseAction(3);$w->activateAction();$w->advance(0.4);$w->confirm();
            }else{$w->strike($i%2 ? -1 : 1);$w->advance(0.06);}
        }
        self::assertSame(0,$w->current()['prefix'][6]);
    }

    private function inventory(World $w,int $action,int $item): void
    {
        $w->chooseAction($action);$w->activateAction();$found=false;
        foreach($w->inventoryChoices() as $i=>$entry){if($entry['item']===$item){$w->choice=$i;$found=true;break;}}
        self::assertTrue($found,'Missing inventory item '.$item);$w->confirm();$w->advance(0.2);
    }

    private function floor(World $w,int $item): void
    {
        $this->inventory($w,2,$item);$cells=$w->current()['cells'];$cell=null;
        for($i=39;$i>=0;$i--){if($cells[$i]===0){$cell=$i;break;}}
        self::assertNotNull($cell);$w->row=intdiv($cell,10);$w->col=$cell%10;$w->confirm();
        self::assertSame('normal',$w->mode);
    }

    private function freezeBag(World $w): void
    {
        $this->travel($w,11,true);
        while(in_array(53,$w->person()['bag'],true)){$this->inventory($w,2,53);}
    }

    public function testCompleteEscapeFromFreshGameWithoutStateInjection(): void
    {
        $w=new World(json_decode(file_get_contents(__DIR__.'/../assets/original/game.json'),true,512,JSON_THROW_ON_ERROR));
        self::assertTrue($w->selectPerson(2));$this->travel($w,38,true);$this->object($w,8,13);$this->object($w,1,26);
        $w->choice=0;$w->confirm();$this->inventory($w,18,43);$w->chooseAction(3);$w->activateAction();$this->travel($w,17,true);
        self::assertTrue($w->selectPerson(1));
        $this->travel($w,41,true);$this->object($w,1,35);$this->inventory($w,18,35);
        $this->travel($w,47,true);$this->object($w,1,49);
        $this->travel($w,63,true);$this->object($w,4,74);$w->choice=4;$w->confirm();
        $this->inventory($w,18,49);$w->chooseAction(8);$w->activateAction();$w->requestDoorDirection();$w->choice=0;$w->confirm();
        self::assertTrue($w->move(0));self::assertSame(59,$w->roomId());$this->object($w,1,48);
        $this->floor($w,49);
        self::assertTrue($w->move(2));$this->object($w,4,74);$w->choice=1;$w->confirm();$this->inventory($w,18,35);
        $this->travel($w,58,true);$this->object($w,8,12);$this->object($w,1,25);$w->choice=0;$w->confirm();
        $this->travel($w,52,true);$this->object($w,8,11);$this->object($w,1,24);$w->choice=0;$w->confirm();
        $this->object($w,1,24);$w->choice=0;$w->confirm();self::assertContains(45,$w->person()['bag']);
        $this->travel($w,17,true);$this->object($w,8,9);$this->inventory($w,18,45);$w->chooseAction(3);$w->activateAction();
        $this->inventory($w,2,51);$cell=array_search(22,$w->current()['cells'],true);self::assertNotFalse($cell);
        $w->row=intdiv($cell,10);$w->col=$cell%10;$w->confirm();self::assertContains(51,$w->containers[22]);
        $this->inventory($w,18,35);$this->travel($w,63,true);$this->object($w,4,74);$w->choice=4;$w->confirm();
        self::assertTrue($w->move(0));$this->object($w,4,62);self::assertTrue($w->electricity);
        self::assertTrue($w->move(2));$this->object($w,4,74);$w->choice=1;$w->confirm();
        $this->travel($w,26,true);$this->object($w,1,42);
        foreach([[77,1],[72,2],[82,3]] as [$source,$door]){
            $this->inventory($w,18,35);$this->travel($w,$source,true);$this->object($w,8,$door);
            $w->chooseAction(16);$w->activateAction();$this->inventory($w,18,48);$this->object($w,4,63);$this->object($w,4,71);
            $w->chooseAction(17);$w->activateAction();
        }
        self::assertTrue($w->selectPerson(3));$this->travel($w,41,true);$this->object($w,1,34);$this->inventory($w,18,34);
        $picked=0;
        for($id=1;$id<=63;$id++){
            if($w->rooms[$id]['prefix'][6]===0 && $w->rooms[$id]['prefix'][8]===0){continue;}
            $this->travel($w,$id,true);
            while($w->rooms[$id]['prefix'][8]>0){
                if(!in_array(0,$w->person()['bag'],true)){$this->freezeBag($w);$this->travel($w,$id,true);}
                $count=$w->rooms[$id]['prefix'][8];$this->object($w,1,53);self::assertSame($count-1,$w->rooms[$id]['prefix'][8],$w->message);$picked++;
            }
        }
        $this->freezeBag($w);self::assertSame(34,$picked);self::assertSame(34,$w->frozenBodies);self::assertTrue($w->raidersArrived);
        self::assertTrue($w->selectPerson(1));$this->inventory($w,18,35);$this->travel($w,78,true);
        $this->object($w,8,5);$this->floor($w,52);$this->inventory($w,18,42);$w->chooseAction(3);$w->activateAction();
        self::assertSame(1,$w->fuelCan);$this->object($w,1,52);$this->travel($w,62,true);
        $this->object($w,8,6);$this->floor($w,52);$w->chooseAction(3);$w->activateAction();self::assertSame(1,$w->fuelTank);
        $this->object($w,8,4);$w->chooseAction(16);$w->activateAction();
        self::assertTrue($w->selectPerson(3));$this->travel($w,62,true);$w->chooseAction(16);$w->activateAction();
        foreach([2,0] as $person){self::assertTrue($w->selectPerson($person));$this->travel($w,62,true);$w->chooseAction(16);$w->activateAction();}
        self::assertSame([90,90,90,90],array_column($w->party,'inside'));self::assertSame([0,0,0,0],array_column($w->party,'state'));
        $this->object($w,4,64);$this->object($w,4,72);self::assertSame('victory',$w->ending);
    }
}
