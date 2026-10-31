<?php

namespace App\View;

use App\Model\World;
use JumPHP\Renderer\Renderer;
use JumPHP\Renderer\Sdl;

/** Original CPC graphics, kept outside the public repository. */
final class CpcView
{
    public function __construct(private Sdl $sdl, string $directory)
    {
        foreach (['rooms', 'trucks', 'objects', 'actions', 'portraits', 'frame', 'title', 'victory', 'defeat',
            'font-black', 'font-white', 'font-blue', 'font-red'] as $name) {
            $sdl->loadTexture('cpc:' . $name, $directory . '/' . $name . '.png');
        }
    }

    public function image(Renderer $r, string $name, float $x = 0, float $y = 0, ?array $source = null,
        ?float $width = null, ?float $height = null): void
    {
        $texture = $this->sdl->getTexture('cpc:' . $name);
        $r->copy($texture['texture'], $source, $x, $y, $width, $height);
    }

    public function text(Renderer $r, string $text, int $x, int $y, string $color = 'white'): void
    {
        $text = strtoupper(strtr($text, ['é'=>'e','è'=>'e','ê'=>'e','à'=>'a','ç'=>'c','ô'=>'o','ù'=>'u']));
        for ($i = 0; $i < strlen($text); $i++) {
            $code = ord($text[$i]);
            $this->image($r, 'font-' . $color, $x + $i * 8, $y,
                [($code % 16) * 8, intdiv($code, 16) * 8, 8, 8], 8, 8);
        }
    }

    public function object(Renderer $r, World $w, int $id, int $x, int $y, bool $small = false): void
    {
        if (!isset($w->objects[$id])) { return; }
        $o = $w->objects[$id];
        $scale = $small ? min(1, 24 / max($o['width'], $o['height'])) : 1;
        $this->image($r, 'objects', $x, $y, [$o['x'], $o['y'], $o['width'], $o['height']],
            $o['width'] * $scale, $o['height'] * $scale);
    }

    public function room(Renderer $r, World $w, int $id, int $x = 8, int $y = 48): void
    {
        $variants = [64 => [0, 77], 65 => [1, 77], 66 => [2, 77], 72 => [3, 0], 77 => [4, 0], 82 => [5, 0]];
        if (isset($variants[$id]) && $w->rooms[$id]['prefix'][9] === $variants[$id][1]) {
            $this->image($r, 'trucks', $x, $y, [$variants[$id][0] * 160, 0, 160, 80], 160, 80);
        } else {
            $this->image($r, 'rooms', $x, $y, [(($id - 1) % 10) * 160, intdiv($id - 1, 10) * 80, 160, 80], 160, 80);
        }
        $seen = [];
        foreach ($w->rooms[$id]['cells'] as $index => $item) {
            if ($item <= 0 || $item >= 255 || (isset($seen[$item]) && $item !== 48)) { continue; }
            $seen[$item] = true;
            $this->object($r, $w, $item, $x + ($index % 10) * 16, $y + 16 + intdiv($index, 10) * 16);
        }
    }

    public function game(Renderer $r, World $w, float $time): void
    {
        $this->image($r, 'frame');
        if (!$w->dark()) { $this->room($r, $w, $w->roomId()); }
        else { $r->fillRect(8, 48, 160, 80, [0, 0, 0]); }
        foreach ($w->party as $i => $p) {
            $portrait = $p['state'] >= 128 ? $i + 4 : ($p['state'] >= 32 ? 8 : $i);
            $this->image($r, 'portraits', 12 + $i * 40, 4, [$portrait * 32, 0, 32, 32], 32, 32);
            if ($i === $w->active) { $r->drawRect(11 + $i * 40, 3, 34, 34, [255, 255, 255]); }
        }
        $p = $w->person();
        $this->text($r, substr($p['name'], 0, 12), 224, 0, 'black');
        $this->image($r, 'portraits', 228, 20, [$w->active * 32, 0, 32, 32], 32, 32);
        $this->object($r, $w, $p['held'], 272, 24, true);
        foreach ($p['gauges'] as $i => $value) {
            $this->text($r, ['F', 'H', 'S'][$i], 224, 64 + 16 * $i, 'black');
            $r->fillRect(232, 64 + 16 * $i, 80, 6, [0, 0, 0]);
            $r->fillRect(232, 64 + 16 * $i, 80 * max(0, min(1, $value / max(1, $p['max'][$i]))), 6, [0, 128, 255]);
        }
        $minutes = (int)$w->minutes;
        $r->fillRect(234, 114, 78, 19, [0, 0, 0]);
        $r->drawRect(244, 115, 48, 17, [0, 128, 255]);
        $this->text($r, sprintf('%02d:%02d', intdiv($minutes, 60) % 24, $minutes % 60), 248, 120, 'red');
        $message = $w->message . '   ';
        $offset = strlen($message) > 14 ? ((int)($time * 3) % strlen($message)) : 0;
        $r->fillRect(100, 140, 120, 12, [0, 0, 0]);
        $shown = strlen($message) > 14 ? substr($message . $message, $offset, 14) : trim($message);
        $this->text($r, $shown, 104, 142);
        $this->text($r, 'PROTOTYPE 0.5  F1:AIDE', 8, 154, 'blue');
        for ($slot = 0; $slot < 9; $slot++) {
            $action = (($w->action - 1 + $slot - 4 + 36) % 18) + 1;
            $this->image($r, 'actions', 16 + $slot * 32, 168, [(($action + 3) % 18) * 32, 0, 32, 32], 32, 32);
        }
        $r->drawRect(143, 167, 34, 33, [0, 128, 255]);
        if ($w->current()['prefix'][6] > 0) {
            $this->image($r, 'portraits', 180, 4, [128, 0, 32, 32], 32, 32);
            $r->fillRect(184, 70, 8, 50, [0, 0, 0]);
            $r->fillRect(184, 120 - $w->pressure * 1.7, 8, $w->pressure * 1.7, [255, 0, 0]);
        }
        if ($w->mode === 'object') {
            $r->drawRect(8 + $w->col * 16, 64 + $w->row * 16, 16, 16, [0, 128, 255]);
        }
        if (in_array($w->mode, ['inventory', 'container', 'direction', 'character', 'lift'], true)) {
            $r->fillRect(218, 52, 102, 62, [0, 0, 0]);
            $choices = match ($w->mode) {
                'direction' => World::DIRECTIONS,
                'lift' => World::FLOORS,
                'character' => array_column($w->party, 'name'),
                'container' => array_map(World::itemName(...), $w->containerChoices()),
                default => array_map(fn ($c) => World::itemName($c['item']), $w->inventoryChoices()),
            };
            foreach ($choices as $i => $label) {
                $this->text($r, ($w->choice === $i ? '>' : ' ') . substr($label, 0, 11), 220, 54 + $i * 10,
                    $w->choice === $i ? 'blue' : 'white');
            }
        }
        if ($w->mode === 'aim') {
            $r->fillRect(72, 130, 96, 8, [0, 0, 0]);
            $phase = fmod($w->aim, 2); $cursor = $phase <= 1 ? $phase : 2 - $phase;
            $r->drawRect(112, 130, 16, 8, [0, 128, 255]);
            $r->fillRect(72 + $cursor * 94, 130, 2, 8, [255, 0, 0]);
        }
        if ($w->mode === 'terminal') {
            $this->panel($r, 'TERMINAL - ENTREE : EXECUTER', $w->terminalLines);
            $this->text($r, '>' . $w->terminalInput . '_', 20, 95, 'blue');
            foreach (World::TERMINAL_COMMANDS as $i => $command) {
                $this->text($r, ($w->choice === $i ? '>' : ' ') . $command, 20, 114 + 11 * $i,
                    $w->choice === $i ? 'blue' : 'white');
            }
            $this->text($r, 'ECHAP : SORTIR', 188, 169, 'blue');
        }
    }

    public function panel(Renderer $r, string $title, array $lines): void
    {
        $r->fillRect(8, 16, 304, 168, [0, 0, 0]);
        $r->drawRect(8, 16, 304, 168, [0, 128, 255]);
        $this->text($r, $title, 20, 25, 'blue');
        foreach ($lines as $i => $line) { $this->text($r, $line, 20, 44 + $i * 11); }
    }
}
