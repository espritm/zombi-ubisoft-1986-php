<?php
namespace App\Tests;

use App\Model\World;
use PHPUnit\Framework\TestCase;

/** Routes use normal game actions; no position, inventory, health or enemy writes. */
final class QuestRouteTest extends TestCase
{
    private function travel(World $w, int $room): void
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
        foreach ($route as $direction) { self::assertTrue($w->move($direction)); $w->advance(0.2); }
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
}
