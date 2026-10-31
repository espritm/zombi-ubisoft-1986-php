<?php
namespace App\Tests;
use App\Model\World;
use PHPUnit\Framework\TestCase;

final class HealingTest extends TestCase
{
    private function world(): World
    {
        $w=new World(json_decode(file_get_contents(__DIR__.'/../assets/original/game.json'),true,512,JSON_THROW_ON_ERROR));
        $w->party[3]['held']=33; $w->party[0]['gauges']=[40,30,80]; return $w;
    }
    private function use(World $w): void { $w->chooseAction(3); $w->activateAction(); }
    public function testHealsTenPointsWithCapsAndKeepsTheKit(): void
    {
        foreach ([[40,30,80],[68,67,80]] as $before) {
            $w=$this->world(); $w->party[0]['gauges']=$before; $this->use($w);
            self::assertSame('target',$w->mode); self::assertSame('heal',$w->pending);
            self::assertTrue($w->selectTarget(0));
            self::assertSame([min(70,$before[0]+10),min(70,$before[1]+10),80],$w->party[0]['gauges']);
            self::assertSame(33,$w->person()['held']); self::assertSame(0,$w->party[0]['state']);
        }
    }
    public function testRejectsUnavailableAndAbsentTargetsButAllowsAnotherVehicle(): void
    {
        foreach ([16,64,128,255] as $state) {
            $w=$this->world(); $w->party[0]['state']=$state; $before=$w->party[0]; $this->use($w);
            self::assertFalse($w->selectTarget(0)); self::assertSame($before,$w->party[0]);
            self::assertSame(15000.0,$w->export()['healingTicks']);
        }
        $w=$this->world(); $w->party[0]['position']=array_search(41,$w->map,true); $this->use($w);
        self::assertFalse($w->selectTarget(0));
        $w=$this->world(); $w->party[0]['inside']=90; $this->use($w);
        self::assertTrue($w->selectTarget(0)); self::assertSame([50,40,80],$w->party[0]['gauges']);
    }
    public function testCooldownStartsBeforeCancelIsSharedAndExpiresAt15000Ticks(): void
    {
        $w=$this->world(); $this->use($w); $w->cancel(); $this->use($w); self::assertSame('normal',$w->mode);
        self::assertTrue($w->selectPerson(1)); $w->party[1]['held']=33;
        $this->use($w); self::assertSame('normal',$w->mode);
        $w->advance(299.98); $this->use($w); self::assertSame('normal',$w->mode);
        $w->advance(0.020001); $this->use($w); self::assertSame('target',$w->mode);
        self::assertTrue($w->selectTarget(0)); self::assertSame([50,40,80],$w->party[0]['gauges']);
    }
    public function testCooldownPausesAndSurvivesReloadAndStrictValidation(): void
    {
        $w=$this->world(); $this->use($w); $w->selectTarget(0); $w->advance(30);
        $save=$w->export(); self::assertSame(13500.0,$save['healingTicks']);
        $copy=$this->world(); self::assertTrue($copy->restore($save)); $this->use($copy); self::assertSame('normal',$copy->mode);
        $copy->mode='pause'; $copy->advance(1000); self::assertSame(13500.0,$copy->export()['healingTicks']);
        foreach ([-1,15001,'100',null,INF,NAN] as $bad) {
            $invalid=$save; $invalid['healingTicks']=$bad; self::assertFalse($copy->restore($invalid));
            self::assertSame(13500.0,$copy->export()['healingTicks']);
        }
        $old=$save; $old['version']='0.11.0'; unset($old['healingTicks']); self::assertTrue($copy->restore($old));
        self::assertSame(0.0,$copy->export()['healingTicks']); self::assertSame([50,40,80],$copy->party[0]['gauges']);
    }
}
