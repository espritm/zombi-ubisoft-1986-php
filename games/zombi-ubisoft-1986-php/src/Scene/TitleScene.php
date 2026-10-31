<?php
namespace App\Scene;

use App\View\CpcView;
use JumPHP\Renderer\Renderer;
use JumPHP\Scene\AbstractScene;

final class TitleScene extends AbstractScene
{
    private CpcView $view;
    private bool $help = false;

    public function enter(array $params = []): void
    {
        $this->help = false;
        $directory = $this->engine->config->path('assets/original');
        $this->view = new CpcView($this->sdl(), $directory);
        $this->sound()->loadMusic('intro', $directory . '/intro.ogg');
        $this->sound()->playMusic('intro', true);
    }

    public function update(float $dt): void
    {
        $a = $this->actions();
        if ($a->isPressed('help')) { $this->help = !$this->help; return; }
        if ($this->help) {
            if ($a->isPressed('confirm') || $a->isPressed('back')) { $this->help = false; }
            return;
        }
        if ($a->isPressed('confirm') || ($this->engine->input->getMouse()->isPressed() && $this->engine->input->getMouse()->y >= 152)) {
            $this->scenes()->switchTo(PlayScene::class);
        }
        elseif ($a->isPressed('character')) { $this->scenes()->switchTo(PlayScene::class, ['load' => true]); }
        elseif ($a->isPressed('gallery')) { $this->scenes()->switchTo(PlayScene::class, ['gallery' => true]); }
    }

    public function render(Renderer $r): void
    {
        $this->view->image($r, 'title');
        $r->fillRect(0, 152, 320, 48, [0, 0, 0]);
        $this->view->text($r, 'ZOMBI - PROTOTYPE CPC 0.5', 56, 156, 'blue');
        $this->view->text($r, 'ENTREE:JOUER  C:CONTINUER', 64, 169);
        $this->view->text($r, 'F1:AIDE  G:90 DECORS', 80, 182);
        if ($this->help) { PlayScene::helpPanel($this->view, $r); }
    }
}
