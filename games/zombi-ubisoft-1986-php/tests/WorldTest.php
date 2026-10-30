<?php
namespace App\Tests;

use App\Model\World;
use PHPUnit\Framework\TestCase;

/** Regression scenarios checked against controlled runs of the original CPC game. */
final class WorldTest extends TestCase
{
    private function world(): World
    {
        return new World(json_decode(file_get_contents(__DIR__ . '/../assets/original/game.json'), true, 512, JSON_THROW_ON_ERROR));
    }

    private function objectAction(World $w, int $action, int $object): void
    {
        $cell = array_search($object, $w->current()['cells'], true);
        self::assertNotFalse($cell, 'Missing object ' . $object . ' in room ' . $w->roomId());
        $w->chooseAction($action); $w->activateAction();
        $w->row = intdiv($cell, 10); $w->col = $cell % 10; $w->confirm();
    }

    public function testOriginalStartAndIndependentSurvivors(): void
    {
        $w = $this->world();
        self::assertCount(90, $w->rooms); self::assertSame(62, $w->roomId()); self::assertSame(3, $w->active);
        self::assertSame(47, $w->party[0]['held']);
        self::assertTrue($w->move(3)); self::assertSame(61, $w->roomId());
        self::assertTrue($w->move(4)); self::assertSame(60, $w->roomId());
        self::assertTrue($w->move(4)); self::assertSame(57, $w->roomId());
        $w->selectPerson(0); self::assertSame(62, $w->roomId());
        $w->selectPerson(3); self::assertSame(57, $w->roomId());
        self::assertFalse($w->move(-1));
    }

    public function testClosedVehicleKeyAndFuelGateTheEnding(): void
    {
        $w = $this->world(); $w->selectPerson(0);
        $w->chooseAction(16); $w->activateAction(); self::assertSame(62, $w->roomId());
        $this->objectAction($w, 8, 4);
        $w->chooseAction(16); $w->activateAction(); self::assertSame(90, $w->roomId());
        $this->objectAction($w, 4, 64); self::assertContains(69, $w->current()['cells']);
        $this->objectAction($w, 4, 72); self::assertSame('', $w->ending);
        $w->fuelTank = 1; $this->objectAction($w, 4, 72); self::assertSame('victory', $w->ending);
    }

    public function testInventorySwapsAndPersistentActivation(): void
    {
        $w = $this->world(); $w->party[3]['bag'] = [43, 45, 0, 0];
        $w->chooseAction(18); $w->activateAction(); $w->confirm(); self::assertSame(43, $w->person()['held']);
        $w->chooseAction(3); $w->activateAction(); self::assertSame(43, $w->person()['used']);
        $w->chooseAction(18); $w->activateAction(); $w->choice = 1; $w->confirm();
        self::assertSame(0, $w->person()['held']); self::assertSame(43, $w->person()['used']);
        $w->chooseAction(3); $w->activateAction(); self::assertSame(0, $w->person()['used']);
    }

    public function testContainersTakeOnceAndRefuseWhenInventoryFull(): void
    {
        $w = $this->world(); $w->electricity = true;
        $w->rooms[62]['cells'][0] = 24; $w->containers[24] = [52, 45, 0, 0];
        $this->objectAction($w, 1, 24); $w->confirm();
        self::assertContains(52, $w->person()['bag']); self::assertNotContains(52, $w->containers[24]);
        $w->party[3]['bag'] = [34, 35, 36, 37];
        $this->objectAction($w, 1, 24); $w->confirm();
        self::assertContains(45, $w->containers[24]); self::assertSame([34,35,36,37], $w->person()['bag']);
    }

    public function testFuseNeedsActivatedGlovesAndEnablesElectricity(): void
    {
        $w = $this->world(); $w->rooms[62]['cells'][0] = 22; $w->containers[22] = [0];
        $w->party[3]['bag'] = [51, 0, 0, 0];
        $f = $w->person()['gauges'][0]; $h = $w->person()['gauges'][1];
        $put = function () use ($w): void {
            $w->chooseAction(2); $w->activateAction(); $w->confirm(); $w->row = 0; $w->col = 0; $w->confirm();
        };
        $put(); self::assertSame($f - 20, $w->person()['gauges'][0]); self::assertSame($h - 10, $w->person()['gauges'][1]);
        self::assertContains(51, $w->person()['bag']); self::assertSame([0], $w->containers[22]);
        $w->party[3]['used'] = 45; $put(); self::assertSame([51], $w->containers[22]);
        $w->rooms[62]['cells'][1] = 62; $this->objectAction($w, 4, 62); self::assertTrue($w->electricity);
        $this->objectAction($w, 4, 67); self::assertFalse($w->electricity);
    }

    public function testLightRequiresUseAndConsumptionClampsAtMaximum(): void
    {
        $w = $this->world(); $w->rooms[62]['prefix'][7] = 255;
        $w->party[3]['held'] = 43; self::assertTrue($w->dark());
        $w->chooseAction(3); $w->activateAction(); self::assertFalse($w->dark());
        $w->party[3]['bag'] = [54, 55, 0, 0];
        $w->party[3]['gauges'][0] = $w->person()['max'][0] - 1;
        $w->chooseAction(5); $w->activateAction(); $w->confirm();
        self::assertSame($w->person()['max'][0], $w->person()['gauges'][0]); self::assertNotContains(54, $w->person()['bag']);
    }

    public function testFuelTransferRequiresCanAndOpenTanks(): void
    {
        $w = $this->world(); $w->party[3]['held'] = 42;
        $w->rooms[62]['cells'] = array_fill(0, 40, 0); $w->rooms[62]['cells'][0] = 18;
        $w->chooseAction(3); $w->activateAction(); self::assertSame(0, $w->fuelCan);
        $w->rooms[62]['cells'][1] = 52; $w->activateAction(); self::assertSame(1, $w->fuelCan);
        $w->rooms[62]['cells'][0] = 19; $w->activateAction();
        self::assertSame(0, $w->fuelCan); self::assertSame(1, $w->fuelTank);
        $w->activateAction(); self::assertSame(1, $w->fuelTank);
    }

    public function testSleepReanimationAndDefeatTimers(): void
    {
        $w = $this->world(); $w->rooms[62]['prefix'][6] = 0;
        $w->party[0]['state'] = 16; $w->party[0]['ticks'] = 119807.0;
        $w->party[0]['gauges'][2] = 10;
        $w->party[1]['state'] = 64; $w->party[1]['ticks'] = 29951.0;
        $w->advance(0.02);
        self::assertSame(0, $w->party[0]['state']); self::assertSame(18, $w->party[0]['gauges'][2]);
        self::assertSame(128, $w->party[1]['state']);
        foreach ($w->party as &$p) { $p['state'] = 64; } unset($p);
        $w->advance(0.02); self::assertSame('defeat', $w->ending);
    }

    public function testSaveRoundTripAndRejectCorruptSaveAtomically(): void
    {
        $w = $this->world(); $w->move(3); $w->fuelTank = 1; $w->electricity = true;
        $save = $w->export(); $other = $this->world();
        self::assertTrue($other->restore($save)); self::assertSame($save, $other->export());
        unset($save['party'][0]['ticks']); self::assertFalse($other->restore($save));
        self::assertSame($w->export(), $other->export());
        $save = $w->export(); $save['rooms'][62]['cells'] = 'broken';
        self::assertFalse($other->restore($save)); self::assertSame($w->export(), $other->export());
    }
}
