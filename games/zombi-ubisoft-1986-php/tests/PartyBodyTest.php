<?php
namespace App\Tests;

use App\Model\World;
use PHPUnit\Framework\TestCase;

final class PartyBodyTest extends TestCase
{
    private function world(): World
    {
        $w = new World(json_decode(file_get_contents(__DIR__.'/../assets/original/game.json'),true,512,JSON_THROW_ON_ERROR));
        $w->party[3]['held']=34; return $w;
    }

    private function eliminate(World $w, int $index=0): void
    {
        $w->chooseAction(3); $w->activateAction(); $w->choice=$index; $w->confirm();
    }

    private function openBody(World $w, int $body=29): void
    {
        $cell=array_search($body,$w->current()['cells'],true);
        self::assertIsInt($cell); $w->row=intdiv($cell,10); $w->col=$cell%10;
        $w->chooseAction(1); $w->activateAction(); $w->confirm();
        self::assertSame('container',$w->mode);
    }

    public function testEliminationPacksAllFiveItemsWithoutCorruptingAnotherBody(): void
    {
        $w=$this->world(); $w->party[0]['bag']=[33,37,45,48]; $w->party[0]['held']=43; $w->party[0]['used']=43;
        $other=$w->containers[30]; $gauges=$w->party[0]['gauges']; $this->eliminate($w);
        self::assertSame([33,37,45,48,43],$w->containers[29]); self::assertSame($other,$w->containers[30]);
        self::assertSame([0,0,0,0],$w->party[0]['bag']); self::assertSame(0,$w->party[0]['held']);
        self::assertSame(0,$w->party[0]['used']); self::assertSame($gauges,$w->party[0]['gauges']);
        self::assertSame([29,29],array_slice($w->rooms[62]['cells'],38,2));
        self::assertSame(0,$w->rooms[62]['prefix'][8]); self::assertSame(0,$w->frozenBodies);
        self::assertSame(3,$w->active);
    }

    public function testSparseInventoryOverwritesOnlyThePackedPrefix(): void
    {
        $w=$this->world(); $w->containers[29]=[50,51,52,53,54];
        $w->party[0]['bag']=[33,0,45,0]; $w->party[0]['held']=43;
        $this->eliminate($w); self::assertSame([33,45,43,53,54],$w->containers[29]);
    }

    public function testBodyPlacementUsesTheFlatGridAndTransfersEvenWithoutRoom(): void
    {
        foreach ([[],[37,39],[29,30],[0,1]] as $free) {
            $w=$this->world(); $w->rooms[62]['cells']=array_fill(0,40,255);
            foreach ($free as $i) { $w->rooms[62]['cells'][$i]=0; }
            $w->party[0]['bag']=[45,0,0,0]; $this->eliminate($w);
            $expected=in_array($free,[[29,30],[0,1]],true)?$free:[];
            self::assertSame($expected,array_keys($w->rooms[62]['cells'],29,true));
            self::assertSame(45,$w->containers[29][0]); self::assertSame([0,0,0,0],$w->party[0]['bag']);
        }
    }

    public function testLootAndTransportPreserveIdentityAndContentsAcrossRoomsAndReload(): void
    {
        $w=$this->world(); $w->party[0]['bag']=[33,37,45,48]; $w->party[0]['held']=43; $this->eliminate($w);
        $this->openBody($w); self::assertSame([29,33,37,45,48,43],$w->containerChoices());
        $w->choice=1; $w->confirm(); self::assertSame(33,$w->person()['bag'][0]); self::assertSame(0,$w->containers[29][0]);
        self::assertContains(29,$w->current()['cells']);
        $this->openBody($w); $w->confirm(); self::assertContains(29,$w->person()['bag']); self::assertNotContains(29,$w->current()['cells']);
        self::assertTrue($w->move(3)); self::assertSame(61,$w->roomId());
        $w->chooseAction(2); $w->activateAction(); $w->choice=1; $w->confirm(); self::assertSame('object',$w->mode);
        $w->row=3; $w->col=8; $w->confirm(); self::assertSame([29,29],array_slice($w->current()['cells'],38,2));
        self::assertNotContains(29,$w->person()['bag']); self::assertSame([0,37,45,48,43],$w->containers[29]);
        $copy=$this->world(); self::assertTrue($copy->restore($w->export()));
        self::assertSame($w->containers,$copy->containers); self::assertSame($w->rooms,$copy->rooms);
        $this->openBody($copy); $copy->choice=4; $copy->confirm(); self::assertContains(43,$copy->person()['bag']);
        self::assertSame(0,$copy->containers[29][4]); self::assertSame(0,$copy->frozenBodies);
    }

    public function testFullInventoryDoesNotRemoveBodyOrLoot(): void
    {
        $w=$this->world(); $w->party[0]['bag']=[33,0,0,0]; $this->eliminate($w);
        $w->party[3]['bag']=[37,38,39,45]; $this->openBody($w); $before=$w->export(); $w->confirm();
        self::assertSame($before,$w->export()); $w->choice=1; $w->confirm(); self::assertSame($before,$w->export());
    }

    public function testCreatedMarkersFeedPeriodicCorpsePenalty(): void
    {
        $w=$this->world(); $this->eliminate($w); $before=$w->party[3]['gauges'][2];
        $save=$w->export(); $save['healthTicks']=15103.0; self::assertTrue($w->restore($save));
        $w->advance(0.02); self::assertSame($before-4,$w->party[3]['gauges'][2]);
        self::assertSame(0,$w->frozenBodies);
    }

    public function testSleepingAndVehicleTargetsLeaveBodyInShootersRoom(): void
    {
        $w=$this->world(); $pos=array_search(34,$w->map,true); $w->party[0]['position']=$w->party[3]['position']=$pos;
        $w->party[0]['state']=16; $w->party[0]['bed']=57; $w->rooms[34]['cells'][23]=$w->rooms[34]['cells'][24]=57;
        $this->eliminate($w); self::assertSame(56,$w->rooms[34]['cells'][23]); self::assertSame(57,$w->party[0]['bed']);
        self::assertContains(29,$w->rooms[34]['cells']);
        $w=$this->world(); $w->party[0]['inside']=90; $this->eliminate($w);
        self::assertContains(29,$w->rooms[62]['cells']); self::assertNotContains(29,$w->rooms[90]['cells']);
        $w=$this->world(); $this->eliminate($w,3); self::assertContains(32,$w->rooms[62]['cells']);
        self::assertSame(34,$w->containers[32][0]); self::assertTrue($w->selectPerson(1));
    }

    public function testCombatDeathAndReanimationTransferThenRemoveOnlyOwnMapMarkers(): void
    {
        $w=$this->world(); $w->party[3]['bag']=[33,0,45,0]; $w->party[3]['gauges'][0]=1;
        (new \ReflectionMethod($w,'combatDamage'))->invoke($w,6);
        self::assertSame(64,$w->party[3]['state']); self::assertSame([33,45,34,0,0],$w->containers[32]);
        self::assertSame([32,32],array_slice($w->rooms[62]['cells'],38,2));
        self::assertTrue($w->selectPerson(0)); $w->party[3]['ticks']=29951.0; $w->party[3]['inside']=90;
        $w->rooms[62]['cells'][36]=31; $w->rooms[90]['cells'][38]=32; $w->advance(0.02);
        self::assertSame(128,$w->party[3]['state']); self::assertNotContains(32,$w->rooms[62]['cells']);
        self::assertContains(31,$w->rooms[62]['cells']); self::assertContains(32,$w->rooms[90]['cells']);
        self::assertSame([33,45,34,0,0],$w->containers[32]);
    }

    public function testLegacyBodySlotsMigrateAndMalformedNewRecordsAreAtomic(): void
    {
        $w=$this->world(); $base=$w->export();
        foreach (['0.2.0','0.14.0','0.15.0'] as $version) {
            $save=$base; $save['version']=$version;
            for ($id=29;$id<=32;$id++) { $save['containers'][$id]=[33,0,45,43]; }
            self::assertTrue($w->restore($save)); self::assertSame([33,0,45,43,0],$w->containers[29]);
        }
        $save=$base; $save['version']='0.15.0'; $save['party'][0]['bed']=57;
        self::assertTrue($w->restore($save)); self::assertSame(57,$w->party[0]['bed']);
        $before=$w->export();
        foreach ([[33,0,0,0],[33,0,0,0,0,0],[33,0,0,0,'43'],[1=>33,2=>0,3=>0,4=>0,5=>0]] as $bad) {
            $save=$base; $save['containers'][29]=$bad; self::assertFalse($w->restore($save)); self::assertSame($before,$w->export());
        }
    }
}
