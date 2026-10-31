<?php
namespace App\Scene;

use App\Model\World;
use App\View\CpcView;
use JumPHP\Renderer\Renderer;
use JumPHP\Scene\AbstractScene;

final class PlayScene extends AbstractScene
{
    private World $world;
    private CpcView $view;
    private bool $help = false;
    private bool $gallery = false;
    private bool $demo = false;
    private int $galleryRoom = 62;
    private float $time = 0;
    private int $demoStage = 0;

    public function enter(array $params = []): void
    {
        $directory = $this->engine->config->path('assets/original');
        $this->world = new World(json_decode(file_get_contents($directory . '/game.json'), true, 512, JSON_THROW_ON_ERROR));
        $this->view = new CpcView($this->sdl(), $directory);
        $this->help = false; $this->time = 0; $this->demoStage = 0;
        $this->gallery = (bool)($params['gallery'] ?? false) || isset($params['room']);
        $this->demo = (bool)($params['demo'] ?? false);
        $this->galleryRoom = max(1, min(90, (int)($params['room'] ?? 62)));
        $this->sound()->stopMusic();
        if ($params['load'] ?? false) { $this->loadGame(); }
    }

    public function update(float $dt): void
    {
        $this->time += $dt;
        $a = $this->actions(); $w = $this->world;
        if ($w->mode === 'terminal' && !$this->help) {
            if ($this->engine->input->getKeyboard()->isKeyPressed(\SDLK_F1)) { $this->help = true; return; }
            $this->terminalInput(); $w->advance($dt); return;
        }
        if ($a->isPressed('help')) { $this->help = !$this->help; return; }
        if ($this->help) {
            if ($a->isPressed('confirm') || $a->isPressed('back')) { $this->help = false; }
            return;
        }
        if ($this->gallery) {
            if ($a->isPressed('left')) { $this->galleryRoom = ($this->galleryRoom + 88) % 90 + 1; }
            if ($a->isPressed('right')) { $this->galleryRoom = $this->galleryRoom % 90 + 1; }
            if ($a->isPressed('back') || $a->isPressed('confirm')) { $this->gallery = false; }
            return;
        }
        if ($w->ending !== '') {
            if ($a->isPressed('restart') || $a->isPressed('confirm')) { $this->scenes()->switchTo(TitleScene::class); }
            return;
        }
        if ($w->mode === 'restart') {
            if ($a->isPressed('confirm')) { $this->enter(); }
            elseif ($a->isPressed('back') || $a->isPressed('cancel')) { $w->cancel(); }
            return;
        }
        if ($w->mode === 'pause') {
            if ($a->isPressed('back') || $a->isPressed('confirm') || $a->isPressed('pause')) { $w->cancel(); }
            elseif ($a->isPressed('restart')) { $w->mode = 'restart'; }
            return;
        }
        if ($a->isPressed('back') || $a->isPressed('pause')) {
            if ($w->mode === 'normal') { $w->mode = 'pause'; } else { $w->cancel(); }
            return;
        }
        if ($a->isPressed('save')) { $this->saveGame(); return; }
        if ($a->isPressed('load')) { $this->loadGame(); return; }
        if ($a->isPressed('cancel')) { $w->cancel(); }
        for ($i = 1; $i <= 4; $i++) { if ($a->isPressed('person' . $i)) { $w->selectPerson($i - 1); } }
        foreach (['character' => 10, 'inventory' => 18, 'move' => 15] as $key => $action) {
            if ($a->isPressed($key)) { $w->chooseAction($action); $w->activateAction(); }
        }
        foreach (['left' => [-1, 0], 'right' => [1, 0], 'up' => [0, -1], 'down' => [0, 1]] as $key => [$dx, $dy]) {
            if (!$a->isPressed($key)) { continue; }
            if ($w->mode === 'object') { $w->moveCursor($dx, $dy); }
            elseif ($w->mode === 'combat' || ($w->mode === 'normal' && $w->current()['prefix'][6] > 0 && $dx)) { $w->strike($dx); }
            elseif ($w->mode === 'normal') { $w->chooseAction(($w->action - 1 + ($dx ?: $dy) + 18) % 18 + 1); }
            elseif (in_array($w->mode, ['direction', 'character', 'inventory', 'container', 'lift'], true)) { $w->changeChoice($dx ?: $dy); }
        }
        if ($a->isPressed('confirm')) { $w->confirm(); }
        $this->mouse();
        if ($w->mode === 'save') { $this->saveGame(); }
        elseif ($w->mode === 'load') { $this->loadGame(); }
        if ($this->demo && $this->time >= ($this->demoStage + 1) * 2) {
            match ($this->demoStage++) { 0 => $w->move(3), 1, 2 => $w->move(4), 3 => $w->selectPerson(0), default => null };
        }
        $w->advance($dt);
    }

    private function terminalInput(): void
    {
        $w = $this->world; $a = $this->actions(); $keyboard = $this->engine->input->getKeyboard();
        if ($a->isPressed('back')) { $w->cancel(); return; }
        if ($a->isPressed('up')) { $w->changeChoice(-1); }
        if ($a->isPressed('down')) { $w->changeChoice(1); }
        foreach ($keyboard->getTyped() as $key) {
            if ($key === \SDLK_BACKSPACE || $key === \SDLK_DELETE) { $w->terminalInput = substr($w->terminalInput, 0, -1); }
            elseif (strlen($w->terminalInput) < 13 && ($key === 32 || ($key >= 97 && $key <= 122) || ($key >= 65 && $key <= 90))) {
                $w->terminalInput .= strtoupper(chr($key));
            }
        }
        // Space is text inside the console; Return or gamepad A executes.
        if ($a->isPressed('confirm') && !$keyboard->isKeyPressed(\SDLK_SPACE)) { $w->confirm(); }
        $mouse = $this->engine->input->getMouse();
        if ($mouse->isPressed() && $mouse->x >= 20 && $mouse->x < 180 && $mouse->y >= 114 && $mouse->y < 114 + 11 * count(World::TERMINAL_COMMANDS)) {
            $w->choice = (int)floor(($mouse->y - 114) / 11); $w->terminalInput = ''; $w->confirm();
        } elseif ($mouse->isPressed() && $mouse->x >= 188 && $mouse->y >= 169 && $mouse->y < 180) { $w->cancel(); }
    }

    private function mouse(): void
    {
        $m = $this->engine->input->getMouse();
        if (!$m->isPressed()) { return; }
        $x = (int)$m->x; $y = (int)$m->y; $w = $this->world;
        if ($y < 38 && $x >= 12 && $x < 172) { $w->selectPerson(intdiv($x - 12, 40)); return; }
        if ($y >= 168 && $x >= 16 && $x < 304) {
            $slot = intdiv($x - 16, 32);
            $w->chooseAction(($w->action - 1 + $slot - 4 + 36) % 18 + 1); $w->activateAction(); return;
        }
        if ($w->mode === 'object' && $x >= 8 && $x < 168 && $y >= 64 && $y < 128) {
            $w->col = intdiv($x - 8, 16); $w->row = intdiv($y - 64, 16); $w->confirm(); return;
        }
        if ($x >= 218 && $y >= 54 && $y < 114 && in_array($w->mode, ['direction','character','inventory','container','lift'], true)) {
            $count = match ($w->mode) {'direction'=>6, 'lift'=>5, 'character'=>4, 'container'=>count($w->containerChoices()), default=>count($w->inventoryChoices())};
            $choice = intdiv($y - 54, 10);
            if ($choice < $count) { $w->choice = $choice; $w->confirm(); }
        }
    }

    private function saveGame(): void
    {
        $ok = $this->engine->storage->writeJson('zombi-v02.json', $this->world->export());
        $this->world->mode = 'normal'; $this->world->message = $ok ? 'PARTIE SAUVEGARDEE' : 'ECHEC SAUVEGARDE';
    }

    private function loadGame(): void
    {
        try {
            $save = $this->engine->storage->readJson('zombi-v02.json');
            if (is_array($save) && $this->world->restore($save)) { return; }
        } catch (\Throwable) { /* Leave current state intact on a corrupt save. */ }
        $this->world->mode = 'normal'; $this->world->message = 'AUCUNE SAUVEGARDE VALIDE';
    }

    public function render(Renderer $r): void
    {
        $w = $this->world;
        if ($w->ending !== '') {
            $this->view->image($r, $w->ending);
            $this->view->text($r, 'ENTREE : RETOUR AU TITRE', 64, 184, 'blue'); return;
        }
        $this->view->game($r, $w, $this->time);
        if ($this->gallery) {
            $this->view->panel($r, 'GALERIE DES 90 DECORS', ['GAUCHE / DROITE : PARCOURIR', 'ENTREE : REVENIR AU JEU']);
            $this->view->room($r, $w, $this->galleryRoom, 80, 92);
            $this->view->text($r, sprintf('PIECE %02d / 90', $this->galleryRoom), 104, 174, 'blue');
        }
        if ($this->help) { self::helpPanel($this->view, $r); }
        elseif ($w->mode === 'pause') { $this->view->panel($r, 'PAUSE', ['ENTREE / ECHAP : REPRENDRE', 'R : RECOMMENCER', 'F1 : AIDE']); }
        elseif ($w->mode === 'restart') { $this->view->panel($r, 'RECOMMENCER LA PARTIE ?', ['ENTREE : CONFIRMER', 'ECHAP : ANNULER']); }
    }

    public static function helpPanel(CpcView $v, Renderer $r): void
    {
        $v->panel($r, 'ZOMBI - AIDE DU PROTOTYPE', ['FLECHES : ACTION / SELECTION', 'ENTREE / ESPACE : VALIDER',
            'TAB : ANNULER  ECHAP : PAUSE', '1-4 : SURVIVANT  C : CHOISIR', 'M : SE DEPLACER  I : EQUIPER',
            'SOURIS : ICONES, OBJETS, PORTRAITS', 'F5 : SAUVER  F9 : CHARGER', 'COMBAT : ALTERNER GAUCHE/DROITE',
            'VIDEO : ALLUMER PUIS POSER CASSETTE', 'ALLUMER TV PUIS ACTIONNER LECTEUR', 'QUETE ET COMBAT ENCORE PROVISOIRES', 'ENTREE / ECHAP : FERMER']);
    }
}
