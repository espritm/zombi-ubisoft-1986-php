<?php

namespace App\Model;

/** Readable PHP state, backed by the tables extracted from the user's CPC discs. */
final class World
{
    public const VERSION = '0.6.0';
    public const FLOORS = ['SOUS-SOL', 'NIVEAU 1', 'NIVEAU 2', 'NIVEAU 3', 'NIVEAU 4'];
    public const TERMINAL_COMMANDS = ['LIST', 'ELECTRICITE', 'NUIT', 'BYE'];
    public const ACTIONS = [1 => 'PRENDRE', 2 => 'POSER', 3 => 'UTILISER', 4 => 'ACTIONNER',
        5 => 'CONSOMMER', 6 => 'DORMIR', 7 => 'REVEILLER', 8 => 'OUVRIR', 9 => 'FERMER',
        10 => 'PERSONNAGE', 11 => 'PAUSE', 12 => 'RECOMMENCER', 13 => 'SAUVEGARDER',
        14 => 'CHARGER', 15 => 'SE DEPLACER', 16 => 'ENTRER', 17 => 'SORTIR', 18 => 'EQUIPER'];
    public const DIRECTIONS = ['NORD', 'MONTER', 'SUD', 'EST', 'DESCENDRE', 'OUEST'];
    private const DELTAS = [8, 56, -8, 1, -56, -1];
    private const EXIT_FIELDS = [0, 5, 2, 1, 4, 3];

    public array $rooms = [];
    public array $party = [];
    public array $containers = [];
    public array $map = [];
    public array $objects = [];
    public int $active = 3;
    public int $action = 1;
    public string $mode = 'normal';
    public int $choice = 0;
    public int $row = 1;
    public int $col = 1;
    public int $direction = 0;
    public string $pending = '';
    public int $selectedItem = 0;
    public string $message = 'BIENVENUE DANS ZOMBI';
    public bool $electricity = false;
    public int $fuelCan = 0;
    public int $fuelTank = 0;
    public int $videoCassette = 0;
    public int $frozenBodies = 0;
    public bool $raidersArrived = false;
    private int $zombieTarget = 0;
    public float $minutes = 615.0;
    public string $ending = '';
    public string $terminalInput = '';
    public array $terminalLines = [];
    public float $pressure = 0.0;
    public float $aim = 0.0;
    private float $battleTime = 0.0;
    private float $lastStrike = -100.0;
    private int $lastSide = 0;
    private float $elapsed = 0.0;
    private int $lastHour = 10;
    private array $initialMap = [];

    public function __construct(array $data)
    {
        foreach (array_merge($data['world']['rooms'], $data['world']['vehicle_interiors']) as $r) {
            $cells = [];
            foreach ($r['objects_grid'] as $line) {
                foreach ($line as $cell) { $cells[] = $cell; }
            }
            $this->rooms[$r['id']] = ['prefix' => $r['record_prefix'], 'cells' => $cells, 'hp' => 31];
            if (isset($r['map_index'])) { $this->map[$r['map_index']] = $r['id']; }
        }
        foreach ($data['characters'] as $c) {
            $raw = array_values(unpack('C*', hex2bin($c['raw_record_hex'])));
            $this->party[] = ['name' => $c['name'], 'position' => hexdec($c['map_pointer']) - 0x6726,
                'inside' => 0, 'bag' => array_slice($raw, 24, 4), 'held' => $raw[28], 'used' => 0,
                'gauges' => [$raw[18], $raw[19], $raw[17]], 'max' => [$raw[21], $raw[22], $raw[20]],
                'state' => 0, 'ticks' => 0.0, 'awakeHours' => 0, 'foodHours' => 0, 'trained' => false];
        }
        $this->containers = $data['containers'];
        foreach ($data['objects'] as $o) { $this->objects[$o['id']] = $o; }
        $this->initialMap = $this->map;
        foreach ($this->rooms as $id => $room) { if ($id <= 63) { $this->zombieTarget += $room['prefix'][6]; } }
    }

    public function roomId(?int $person = null): int
    {
        $p = $this->party[$person ?? $this->active];
        return $p['inside'] ?: ($this->map[$p['position']] ?? 62);
    }

    public function current(): array { return $this->rooms[$this->roomId()]; }
    public function person(): array { return $this->party[$this->active]; }
    public function cursorObject(): int { return $this->current()['cells'][$this->row * 10 + $this->col]; }

    public function dark(): bool
    {
        if ($this->electricity) { return false; }
        $location = $this->map[$this->person()['position']] ?? 0;
        foreach ($this->party as $person) {
            if ($person['used'] === 43 && ($this->map[$person['position']] ?? -1) === $location) { return false; }
        }
        $light = $this->current()['prefix'][7];
        $hour = ((int)floor($this->minutes / 60)) % 24;
        return $light === 255 || ($light !== 0 && ($hour < 6 || $hour >= 22));
    }

    public function selectPerson(int $index): bool
    {
        if ($index < 0 || $index > 3 || $this->party[$index]['state'] !== 0) {
            $this->message = 'PERSONNAGE INDISPONIBLE';
            return false;
        }
        $this->active = $index;
        $this->mode = 'normal';
        $this->battleTime = 0;
        $this->pressure = 0;
        $this->message = $this->person()['name'];
        return true;
    }

    public function chooseAction(int $action): void
    {
        $this->action = max(1, min(18, $action));
        $this->mode = 'normal';
        $this->message = self::ACTIONS[$this->action];
    }

    public function cancel(): void
    {
        $this->mode = 'normal';
        $this->pending = '';
        $this->message = 'ANNULE';
    }

    public function activateAction(): void
    {
        if ($this->ending !== '') { return; }
        $this->choice = 0;
        if ($this->action === 10) { $this->mode = 'character'; $this->choice = $this->active; return; }
        if ($this->person()['state'] !== 0) { $this->message = 'CHOISIR UN AUTRE SURVIVANT'; return; }
        switch ($this->action) {
            case 1: $this->pending = 'take'; $this->mode = 'object'; break;
            case 2: $this->pending = 'put'; $this->mode = 'inventory'; break;
            case 3: $this->useHeld(); break;
            case 4: $this->pending = 'interact'; $this->mode = 'object'; break;
            case 5: $this->pending = 'consume'; $this->mode = 'inventory'; break;
            case 6: $this->pending = 'sleep'; $this->mode = 'object'; break;
            case 7: $this->pending = 'wake'; $this->mode = 'object'; break;
            case 8: $this->pending = 'open'; $this->mode = 'object'; break;
            case 9: $this->pending = 'close'; $this->mode = 'object'; break;
            case 11: $this->mode = 'pause'; break;
            case 12: $this->mode = 'restart'; break;
            case 13: $this->mode = 'save'; break;
            case 14: $this->mode = 'load'; break;
            case 15: $this->mode = 'direction'; $this->choice = $this->direction; break;
            case 16: $this->enterVehicle(); break;
            case 17: $this->exitVehicle(); break;
            case 18: $this->pending = 'equip'; $this->mode = 'inventory'; break;
        }
    }

    public function inventoryChoices(): array
    {
        $p = $this->person(); $choices = [];
        foreach ($p['bag'] as $slot => $item) {
            if ($item !== 0) { $choices[] = ['item' => $item, 'slot' => $slot]; }
        }
        $choices[] = ['item' => 0, 'slot' => -1];
        if ($p['held'] !== 0) { $choices[] = ['item' => $p['held'], 'slot' => 4]; }
        return $choices;
    }

    public function moveCursor(int $dx, int $dy): void
    {
        $this->row = ($this->row + $dy + 4) % 4;
        $this->col = ($this->col + $dx + 10) % 10;
    }

    public function changeChoice(int $delta): void
    {
        $count = $this->mode === 'terminal' ? count(self::TERMINAL_COMMANDS) : ($this->mode === 'lift' ? 5 :
            (in_array($this->mode, ['direction', 'doorDirection'], true) ? 6 : ($this->mode === 'character' ? 4 :
            ($this->mode === 'container' ? count($this->containerChoices()) : count($this->inventoryChoices())))));
        $this->choice = ($this->choice + $delta + max(1, $count)) % max(1, $count);
    }

    public function containerChoices(): array
    {
        $items = $this->containers[$this->selectedItem] ?? [];
        $choices = array_values(array_filter($items, fn ($v) => $v > 0));
        return $choices ?: [0];
    }

    public function confirm(): void
    {
        switch ($this->mode) {
            case 'normal': $this->activateAction(); return;
            case 'character': $this->selectPerson($this->choice); return;
            case 'direction': $this->direction = $this->choice; $this->move($this->choice); return;
            case 'doorDirection': $this->changeDoor($this->choice); return;
            case 'lift': $this->moveLift($this->choice); return;
            case 'terminal': $this->terminalCommand($this->terminalInput ?: self::TERMINAL_COMMANDS[$this->choice]); return;
            case 'inventory': $this->pickInventory(); return;
            case 'container':
                $item = $this->containerChoices()[$this->choice] ?? 0;
                if ($item && $this->addItem($item)) {
                    foreach ($this->containers[$this->selectedItem] as &$value) {
                        if ($value === $item) { $value = 0; break; }
                    }
                    unset($value);
                    $this->mode = 'normal';
                    $this->message = self::itemName($item) . ' PRIS';
                } else { $this->message = $item ? 'INVENTAIRE PLEIN' : 'VIDE'; }
                return;
            case 'object': $this->objectAction(); return;
            case 'aim': $this->shoot(); return;
        }
    }

    public function move(int $direction): bool
    {
        if ($direction < 0 || $direction > 5) { return false; }
        $this->mode = 'normal';
        if ($this->person()['inside']) { $this->message = 'SORTIR DU VEHICULE AVANT'; return false; }
        if ($this->person()['state'] !== 0) { return false; }
        $flag = $this->current()['prefix'][self::EXIT_FIELDS[$direction]];
        $target = $this->person()['position'] + self::DELTAS[$direction];
        if ($flag === 250) { return $this->crossWindow(); }
        // Original #9C1A refuses entry to the top landing while its south passage is locked.
        if ($direction === 0 && ($this->map[$target] ?? 0) === 59 &&
            !in_array($this->rooms[59]['prefix'][2], [0, 20], true)) {
            $this->message = 'ACCES AU DERNIER ETAGE FERME'; return false;
        }
        if (!in_array($flag, [0, 20], true) || !isset($this->map[$target])) {
            $this->message = in_array($flag, [30, 240, 250], true) ? 'PASSAGE FERME OU SPECIAL' : 'PASSAGE IMPOSSIBLE';
            return false;
        }
        $this->party[$this->active]['position'] = $target;
        $this->message = self::DIRECTIONS[$direction];
        $this->pressure = 0; $this->battleTime = 0;
        return true;
    }

    public function requestDoorDirection(): void
    {
        if ($this->mode === 'object' && in_array($this->pending, ['open', 'close'], true)) {
            $this->mode = 'doorDirection'; $this->choice = 0; $this->message = 'CHOISIR LE PASSAGE';
        }
    }

    private function changeDoor(int $direction): void
    {
        $this->mode = 'normal';
        if ($this->person()['held'] !== 49 || $this->person()['inside'] !== 0 || !in_array($direction, [0,2,3,5], true)) {
            $this->message = 'OUTIL NECESSAIRE'; return;
        }
        $target = $this->map[$this->person()['position'] + self::DELTAS[$direction]] ?? null;
        $field = self::EXIT_FIELDS[$direction]; $open = $this->pending === 'open';
        $from = $open ? 30 : 20; $to = $open ? 20 : 30;
        if ($target === null) { $this->message = 'AUCUN PASSAGE'; return; }
        if ($this->current()['prefix'][$field] === $from) {
            $this->rooms[$this->roomId()]['prefix'][$field] = $to;
            $opposite = [0=>2,2=>0,3=>5,5=>3][$direction];
            $this->rooms[$target]['prefix'][self::EXIT_FIELDS[$opposite]] = $to;
        } elseif ($target === 59 && $this->rooms[59]['prefix'][2] === $from) {
            $this->rooms[59]['prefix'][2] = $to;
        } else { $this->message = 'PASSAGE NON VERROUILLABLE'; return; }
        $this->message = $open ? 'PASSAGE OUVERT' : 'PASSAGE FERME';
    }

    private function putCorpse(): void
    {
        $this->removeItem(53); $this->mode = 'normal';
        if ($this->roomId() === 11) {
            $this->frozenBodies = min(255, $this->frozenBodies + 1);
            $this->message = 'CADAVRE ENTREPOSE'; $this->checkArrival();
        } else {
            $r = &$this->rooms[$this->roomId()]; $r['prefix'][8] = min(255, $r['prefix'][8] + 1);
            $r['cells'][30] = 53; $r['cells'][31] = 53; $this->message = 'CADAVRE POSE';
        }
    }

    /** #828D–#8367: frozen quota and three blocked entrances, not radio activation. */
    private function checkArrival(): void
    {
        if ($this->raidersArrived || $this->frozenBodies < $this->zombieTarget) { return; }
        foreach ([64,65,66] as $id) { if ($this->rooms[$id]['prefix'][9] === 0) { return; } }
        $this->raidersArrived = true;
        for ($id = 64; $id <= 86; $id++) { $this->rooms[$id]['prefix'][6] = 0; }
        $r = &$this->rooms[78]; $r['prefix'][9] = 78; $r['prefix'][6] = 2;
        for ($i = 0; $i < 30; $i++) { $r['cells'][$i] = 255; }
        for ($i = 32; $i < 40; $i++) { $r['cells'][$i] = 0; }
        $r['cells'][17] = 5; unset($r);
        $this->rooms[65]['prefix'][6] = 2; $this->rooms[65]['prefix'][2] = 20; $this->rooms[65]['prefix'][9] = 0;
        for ($i = 0; $i < 30; $i++) {
            $this->rooms[77]['cells'][$i] = $this->rooms[65]['cells'][$i]; $this->rooms[65]['cells'][$i] = 0;
        }
        for ($i = 32; $i < 40; $i++) { $this->rooms[77]['cells'][$i] = 0; }
        $this->rooms[19]['prefix'][6] = 2; $this->rooms[19]['prefix'][0] = 20;
        $this->rooms[77]['prefix'][6] = 1; $this->rooms[77]['prefix'][9] = 77;
        if ($this->rooms[21]['prefix'][1] === 20) { $this->rooms[21]['prefix'][6] = 1; }
        $this->rooms[22]['prefix'][6] = 1; $this->rooms[23]['prefix'][6] = 1;
        $position = array_search(77, $this->map, true);
        foreach ($this->party as &$person) { if ($person['inside'] === 87 && $position !== false) { $person['position'] = $position; } }
        unset($person);
        $this->pressure = 0; $this->battleTime = 0; $this->message = 'UN AUTRE CAMION EST ARRIVE';
    }

    /** #9A2B/#9B11: the cabin is room 63, moved between five map cells. */
    private function relocateLift(int $target, bool $called): void
    {
        $old = array_search(63, $this->map, true);
        if ($old === false) { return; }
        $oldLanding = $this->map[$old + 8] ?? null;
        $newLanding = $this->map[$target + 8] ?? null;
        foreach ($this->party as &$person) {
            if ($person['position'] === $old) { $person['position'] = $target; }
        }
        unset($person);
        unset($this->map[$old]); $this->map[$target] = 63;
        if ($oldLanding !== null && (!$called || $this->rooms[$oldLanding]['prefix'][2] < 15)) {
            $this->rooms[$oldLanding]['prefix'][2] = 10;
        }
        if ($newLanding !== null && ($called || $this->rooms[$newLanding]['prefix'][2] < 15)) {
            $this->rooms[$newLanding]['prefix'][2] = 0;
        }
        $this->pressure = 0; $this->battleTime = 0;
    }

    public function moveLift(int $floor): bool
    {
        if ($floor < 0 || $floor > 4 || $this->roomId() !== 63 || $this->person()['state'] !== 0) { return false; }
        $this->relocateLift(2 + 56 * $floor, false);
        $this->mode = 'normal'; $this->message = self::FLOORS[$floor];
        return true;
    }

    /** #9CA4: leave the rope in the last free source cell and reach its facade room. */
    private function crossWindow(): bool
    {
        $source = $this->roomId();
        $destination = [35 => 64, 38 => 66, 46 => 65][$source] ?? null;
        if ($destination === null) { $this->message = 'PASSAGE SPECIAL INCONNU'; return false; }
        if ($this->person()['used'] !== 41) {
            $this->party[$this->active]['state'] = 255;
            $this->party[$this->active]['used'] = 0;
            $this->mode = 'character'; $this->choice = $this->active;
            $this->message = 'CHUTE ! CHOISIR UN SURVIVANT'; return false;
        }
        $free = null;
        for ($i = 39; $i >= 1; $i--) {
            if ($this->rooms[$source]['cells'][$i] === 0) { $free = $i; break; }
        }
        $target = array_search($destination, $this->map, true);
        if ($free === null || $target === false) { $this->message = 'PAS DE PLACE POUR LA CORDE'; return false; }
        $this->rooms[$source]['cells'][$free] = 41;
        $this->removeItem(41);
        $this->party[$this->active]['position'] = $target;
        $this->pressure = 0; $this->battleTime = 0;
        $this->message = 'DESCENTE PAR LA CORDE'; return true;
    }

    /** #98FA–#99D8: fixed truck routes, copied object rows and blocked entrances. */
    public function driveTruck(): bool
    {
        $interior = $this->person()['inside'];
        $routes = [87 => [77, 65, 19, 0, 2], 88 => [72, 66, 31, 1, 3], 89 => [82, 64, 32, 3, 1]];
        if (!isset($routes[$interior]) || $this->person()['state'] !== 0) { return false; }
        if (!in_array(68, $this->current()['cells'], true)) { $this->message = 'METTRE LE CONTACT'; return false; }
        [$source, $target, $blocked, $exit, $opposite] = $routes[$interior];
        if (($this->map[$this->person()['position']] ?? 0) !== $source || $this->rooms[$source]['prefix'][9] !== 77) {
            $this->message = "PLUS D'ESSENCE"; return false;
        }
        $position = array_search($target, $this->map, true);
        if ($position === false) { return false; }
        for ($i = 0; $i < 30; $i++) {
            $this->rooms[$target]['cells'][$i] = $this->rooms[$source]['cells'][$i];
            $this->rooms[$source]['cells'][$i] = 0;
        }
        for ($i = 32; $i < 40; $i++) { $this->rooms[$target]['cells'][$i] = 0; }
        $this->rooms[$source]['prefix'][9] = 0;
        $this->rooms[$target]['prefix'][9] = 77;
        $this->rooms[$blocked]['prefix'][$exit] = 10;
        $this->rooms[$target]['prefix'][$opposite] = 10;
        foreach ($this->party as &$survivor) {
            if ($survivor['inside'] === $interior) { $survivor['position'] = $position; }
        }
        unset($survivor);
        $this->mode = 'normal'; $this->pressure = 0; $this->battleTime = 0;
        $this->message = 'ACCES BLOQUE. MOTEUR ARRETE'; $this->checkArrival(); return true;
    }

    /** Original #973E–#97E1: hints and the active survivor's training flag. */
    public function terminalCommand(string $command): bool
    {
        if ($this->mode !== 'terminal' || $this->person()['state'] !== 0) { return false; }
        $command = substr(strtoupper(trim($command)), 0, 13);
        $matched = null;
        foreach (array_merge(self::TERMINAL_COMMANDS, ['ZOMBI DEAD']) as $known) {
            if (str_starts_with($command, $known)) { $matched = $known; break; }
        }
        $this->terminalInput = '';
        if ($matched === 'BYE') { $this->mode = 'normal'; $this->message = 'TERMINAL DECONNECTE'; return true; }
        $lines = match ($matched) {
            'LIST' => ['ELECTRICITE', 'NUIT'],
            'ELECTRICITE' => ['FUSIBLES.'],
            'NUIT' => ['22h-6h.'],
            'ZOMBI DEAD' => ['SHOOT BRAIN.'],
            default => ['SYNTAX ERROR'],
        };
        if ($matched === 'ZOMBI DEAD') { $this->party[$this->active]['trained'] = true; }
        $this->terminalLines = array_slice(array_merge($this->terminalLines, ['>' . $command], $lines, ['RDY:']), -4);
        return $matched !== null;
    }

    private function addItem(int $item): bool
    {
        $slot = array_search(0, $this->party[$this->active]['bag'], true);
        if ($slot === false) { return false; }
        $this->party[$this->active]['bag'][$slot] = $item;
        return true;
    }

    private function removeItem(int $item): void
    {
        $p = &$this->party[$this->active];
        if ($p['held'] === $item) { $p['held'] = 0; }
        else {
            $slot = array_search($item, $p['bag'], true);
            if ($slot !== false) { $p['bag'][$slot] = 0; }
        }
        if ($p['used'] === $item) { $p['used'] = 0; }
    }

    private function replaceObject(int $old, int $new): void
    {
        foreach ($this->rooms[$this->roomId()]['cells'] as &$cell) {
            if ($cell === $old) { $cell = $new; }
        }
        unset($cell);
    }

    private function pickInventory(): void
    {
        $selected = $this->inventoryChoices()[$this->choice] ?? ['item' => 0, 'slot' => -1];
        $item = $selected['item']; $slot = $selected['slot']; $p = &$this->party[$this->active];
        if ($this->pending === 'equip') {
            if ($slot === 4) { $this->mode = 'normal'; return; }
            if ($slot < 0) {
                $slot = array_search(0, $p['bag'], true);
                if ($slot === false && $p['held'] !== 0) { $this->message = 'INVENTAIRE PLEIN'; return; }
            }
            $held = $p['held'];
            $p['held'] = $item;
            if ($slot !== false) { $p['bag'][$slot] = $held; }
            $this->mode = 'normal'; $this->message = $item ? self::itemName($item) . ' EN MAIN' : 'MAIN VIDE';
        } elseif ($this->pending === 'consume') {
            if (!in_array($item, [54, 55], true)) { $this->message = 'CECI NE SE CONSOMME PAS'; return; }
            $gauge = $item === 54 ? 0 : 2;
            $p['gauges'][$gauge] = min($p['max'][$gauge], $p['gauges'][$gauge] + ($item === 54 ? 2 : 4));
            if ($item === 55) { $p['foodHours'] = 0; }
            $this->removeItem($item);
            $this->message = 'CONSOMME'; $this->mode = 'normal';
        } elseif ($this->pending === 'put' && $item !== 0) {
            $this->selectedItem = $item;
            if ($item === 53) { $this->putCorpse(); } else { $this->mode = 'object'; }
        } else { $this->message = 'CHOISIR UN OBJET'; }
    }

    private function objectAction(): void
    {
        if ($this->dark()) { $this->message = 'IL FAIT TROP SOMBRE'; return; }
        $item = $this->cursorObject();
        if ($this->pending === 'take') {
            if ($item >= 22 && $item <= 32) {
                $this->selectedItem = $item; $this->mode = 'container'; $this->choice = 0; return;
            }
            if ($item < 33 || $item > 55) { $this->message = 'RIEN A PRENDRE ICI'; return; }
            if (!$this->addItem($item)) { $this->message = 'INVENTAIRE PLEIN'; return; }
            if ($item === 48) { $this->rooms[$this->roomId()]['cells'][$this->row * 10 + $this->col] = 0; }
            elseif ($item === 53) {
                $this->rooms[$this->roomId()]['prefix'][8] = max(0, $this->current()['prefix'][8] - 1);
                if ($this->current()['prefix'][8] === 0) { $this->replaceObject(53, 255); }
            } elseif (!in_array($item, [54, 55], true)) { $this->replaceObject($item, 0); }
            $this->message = self::itemName($item) . ' PRIS';
        } elseif ($this->pending === 'open' || $this->pending === 'close') {
            $open = $this->pending === 'open';
            if (($open && ($item < 1 || $item > 13)) || (!$open && ($item < 14 || $item > 26))) {
                $this->message = 'ACTION IMPOSSIBLE'; return;
            }
            if (in_array($item, [7, 8, 20, 21], true)) {
                $from = $open ? 240 : 250; $to = $open ? 250 : 240;
                foreach ($this->rooms[$this->roomId()]['prefix'] as $i => &$value) {
                    if ($i < 6 && $value === $from) { $value = $to; break; }
                }
                unset($value);
            }
            $this->replaceObject($item, $item + ($open ? 13 : -13));
            $this->message = $open ? 'OUVERT' : 'FERME';
        } elseif ($this->pending === 'put') {
            if ($item === 65) {
                if ($this->selectedItem !== 50) { $this->message = 'IL FAUT UNE CASSETTE'; return; }
                if ($this->videoCassette !== 0) { $this->message = 'CASSETTE DEJA INSEREE'; return; }
                $this->videoCassette = 50;
            } elseif ($item >= 22 && $item <= 32) {
                if ($item === 22) {
                    if ($this->selectedItem !== 51) { $this->message = 'IL FAUT DES FUSIBLES'; return; }
                    if ($this->person()['used'] !== 45) {
                        $this->damage(20, 10); $this->message = 'DECHARGE ! METTRE LES GANTS'; $this->mode = 'normal'; return;
                    }
                }
                $slots = $this->containers[$item];
                $free = array_search(0, $slots, true);
                if ($free === false) { $this->message = 'CONTENANT PLEIN'; return; }
                $this->containers[$item][$free] = $this->selectedItem;
            } elseif ($item === 0) {
                if (!$this->placeObject($this->selectedItem)) { return; }
            } else { $this->message = 'CHOISIR UNE PLACE LIBRE'; return; }
            $this->removeItem($this->selectedItem); $this->message = 'OBJET POSE';
        } elseif ($this->pending === 'interact') {
            $this->interact($item);
        } elseif ($this->pending === 'sleep') {
            if (!in_array($item, [56, 58], true)) { $this->message = 'CHOISIR UN LIT'; return; }
            $this->party[$this->active]['state'] = 16; $this->party[$this->active]['ticks'] = 0.0;
            $this->replaceObject($item, $item + 1); $this->party[$this->active]['bed'] = $item + 1;
            $this->message = 'REPOS. CHOISIR UN SURVIVANT';
        } elseif ($this->pending === 'wake') {
            if (!in_array($item, [57, 59], true)) { $this->message = 'PERSONNE NE DORT ICI'; return; }
            foreach ($this->party as &$person) {
                if ($person['state'] === 16 && ($person['bed'] ?? 0) === $item && $this->roomIdFor($person) === $this->roomId()) {
                    $person['state'] = 0; $person['ticks'] = 0.0;
                }
            }
            unset($person); $this->replaceObject($item, $item - 1); $this->message = 'REVEILLE';
        }
        if (!in_array($this->mode, ['lift', 'terminal'], true)) { $this->mode = 'normal'; }
    }

    private function placeObject(int $item): bool
    {
        if (!isset($this->objects[$item])) { return false; }
        $w = (int)ceil($this->objects[$item]['width'] / 16); $h = (int)ceil($this->objects[$item]['height'] / 16);
        foreach ([[0, 0], [1 - $w, 0], [0, 1 - $h], [1 - $w, 1 - $h]] as [$dx, $dy]) {
            $indices = []; $ok = true;
            for ($y = 0; $y < $h; $y++) {
                for ($x = 0; $x < $w; $x++) {
                    $col = $this->col + $dx + $x; $row = $this->row + $dy + $y;
                    if ($col < 0 || $col > 9 || $row < 0 || $row > 3 || $this->current()['cells'][$row * 10 + $col] !== 0) { $ok = false; }
                    $indices[] = $row * 10 + $col;
                }
            }
            if ($ok) {
                foreach ($indices as $i) { $this->rooms[$this->roomId()]['cells'][$i] = $item; }
                return true;
            }
        }
        $this->message = 'PAS ASSEZ DE PLACE'; return false;
    }

    private function interact(int $item): void
    {
        $new = null;
        switch ($item) {
            case 60: $new = 65; break;
            case 61: $new = 66; break;
            case 62:
                if (!in_array(51, $this->containers[22], true)) { $this->message = 'IL MANQUE LES FUSIBLES'; return; }
                $this->electricity = true; $new = 67; break;
            case 67: $this->electricity = false; $new = 62; break;
            case 63: case 64:
                if ($this->person()['held'] !== ($item === 63 ? 48 : 47)) { $this->message = 'METTRE LA BONNE CLE EN MAIN'; return; }
                $new = $item + 5; break;
            case 68: case 69:
                if ($this->person()['held'] !== ($item === 68 ? 48 : 47)) { $this->message = 'CLE NECESSAIRE'; return; }
                $new = $item - 5; break;
            case 66: $new = 61; break;
            case 72:
                if (!in_array(69, $this->current()['cells'], true)) { $this->message = 'METTRE LE CONTACT'; return; }
                if ($this->fuelTank === 0) { $this->message = "PLUS D'ESSENCE"; return; }
                $this->ending = 'victory'; return;
            case 71: $this->driveTruck(); return;
            case 73:
                $target = $this->person()['position'] - 8;
                if (in_array($target, [2, 58, 114, 170, 226], true)) {
                    $this->relocateLift($target, true); $this->message = 'ASCENSEUR APPELE';
                } else { $this->message = 'APPEL IMPOSSIBLE'; }
                return;
            case 74:
                if ($this->roomId() === 63) {
                    $this->mode = 'lift'; $this->choice = intdiv($this->person()['position'] - 2, 56);
                    $this->message = 'CHOISIR UN ETAGE';
                }
                return;
            case 75:
                $this->mode = 'terminal'; $this->choice = 0; $this->terminalInput = '';
                $this->terminalLines = ['LOGIN', 'RDY:']; $this->message = 'TERMINAL CONNECTE'; return;
            case 65: case 70:
                if ($this->videoCassette !== 50) { $this->message = 'INSERER UNE CASSETTE'; return; }
                if (!in_array(66, $this->current()['cells'], true)) { $this->message = 'ALLUMER LE TELEVISEUR'; return; }
                // Original #988F–#98EA: 80 minutes, then train everyone located in room 29.
                $this->minutes += 80;
                foreach ($this->party as &$viewer) {
                    if (($this->map[$viewer['position']] ?? 0) === 29) { $viewer['trained'] = true; }
                }
                unset($viewer);
                $this->replaceObject($item, 65); $this->message = 'SHOOT BRAIN.'; return;
            default: $this->message = 'AUCUN EFFET'; return;
        }
        $this->replaceObject($item, $new); $this->message = 'ACTION EFFECTUEE';
    }

    private function useHeld(): void
    {
        $p = &$this->party[$this->active]; $p['used'] = $p['held'];
        $item = $p['held']; $this->mode = 'normal';
        if (in_array($item, [0, 41, 43, 45], true)) { $this->message = $item ? self::itemName($item) . ' ACTIVE' : 'OBJET DESACTIVE'; return; }
        if ($item === 42) {
            $cells = $this->current()['cells'];
            if (!in_array(52, $cells, true)) { $this->message = 'POSER LE BIDON A PROXIMITE'; return; }
            if (in_array(18, $cells, true)) { $this->fuelCan = 1; $this->message = 'BIDON REMPLI'; return; }
            if (in_array(19, $cells, true) && $this->fuelCan > 0) { $this->fuelCan--; $this->fuelTank++; $this->message = 'RESERVOIR REMPLI'; return; }
            $this->message = 'BIDON VIDE OU RESERVOIR FERME'; return;
        }
        if (in_array($item, [34, 35, 36], true) && $this->current()['prefix'][6] > 0) {
            $this->mode = 'aim'; $this->aim = 0; $this->message = 'VALIDER QUAND LA CIBLE EST AU CENTRE'; return;
        }
        if (in_array($item, [37, 38, 39], true) && $this->current()['prefix'][6] > 0) {
            $this->mode = 'combat'; $this->message = 'ALTERNER GAUCHE ET DROITE'; return;
        }
        $this->message = 'USAGE SPECIAL EN COURS DE PORTAGE';
    }

    private function enterVehicle(): void
    {
        if ($this->person()['inside']) { $this->message = 'DEJA A INTERIEUR'; return; }
        foreach ($this->current()['cells'] as $item) {
            if ($item >= 14 && $item <= 17) {
                $this->party[$this->active]['inside'] = $item + 73;
                $this->mode = 'normal'; $this->message = 'DANS LE VEHICULE'; return;
            }
        }
        $this->message = 'OUVRIR LA PORTE DU VEHICULE';
    }

    private function exitVehicle(): void
    {
        if (!$this->person()['inside']) { $this->message = 'DEJA DEHORS'; return; }
        $this->party[$this->active]['inside'] = 0; $this->mode = 'normal'; $this->message = 'SORTIE DU VEHICULE';
    }

    public function strike(int $side): void
    {
        if ($this->current()['prefix'][6] <= 0 || $this->person()['state'] !== 0) { return; }
        if ($side !== $this->lastSide && $this->elapsed - $this->lastStrike < 0.20) { $this->pressure = min(28, $this->pressure + 2); }
        $this->lastSide = $side; $this->lastStrike = $this->elapsed;
        if ($this->pressure >= 12) {
            $this->rooms[$this->roomId()]['hp'] -= 2 + (int)floor($this->person()['gauges'][0] / 16);
            if ($this->current()['hp'] <= 0) { $this->enemyDefeated(); }
        }
    }

    private function shoot(): void
    {
        $phase = fmod($this->aim, 2.0); $cursor = $phase <= 1 ? $phase : 2 - $phase;
        $tolerance = 0.08 + $this->person()['gauges'][1] / 1000 + ($this->person()['trained'] ? 0.08 : 0);
        $this->mode = 'normal';
        if (abs($cursor - 0.5) < $tolerance) {
            $this->rooms[$this->roomId()]['hp'] = 0; $this->enemyDefeated();
        } else { $this->message = 'TIR MANQUE'; }
    }

    private function enemyDefeated(): void
    {
        $r = &$this->rooms[$this->roomId()]; $r['prefix'][6] = max(0, $r['prefix'][6] - 1);
        $r['hp'] = 31;
        // Original #93E2 uses corpse 53 in the two leftmost bottom cells, not truck key 48.
        $r['prefix'][8] = min(255, $r['prefix'][8] + 1);
        $r['cells'][30] = 53; $r['cells'][31] = 53;
        $this->pressure = 0; $this->mode = 'normal'; $this->message = 'ZOMBI ABATTU';
    }

    private function damage(int $f, int $h): void
    {
        $p = &$this->party[$this->active];
        $p['gauges'][0] = max(0, $p['gauges'][0] - $f); $p['gauges'][1] = max(0, $p['gauges'][1] - $h);
        if ($p['gauges'][0] === 0 || $p['gauges'][1] === 0) {
            $p['state'] = 64; $p['ticks'] = 0.0; $p['used'] = 0;
            $this->mode = 'character'; $this->choice = $this->active; $this->message = 'CHOISIR UN SURVIVANT';
        }
    }

    private function roomIdFor(array $p): int { return $p['inside'] ?: ($this->map[$p['position']] ?? 62); }

    public function advance(float $dt): void
    {
        if ($this->ending !== '' || in_array($this->mode, ['pause', 'restart', 'save', 'load'], true)) { return; }
        $this->elapsed += $dt; $this->minutes += $dt * 0.2;
        $this->checkArrival();
        if ($this->mode === 'aim') { $this->aim += $dt * 1.2; }
        foreach ($this->party as &$p) {
            if (in_array($p['state'], [16, 64], true)) { $p['ticks'] += $dt * 50; }
            if ($p['state'] === 16 && $p['ticks'] >= 119808) {
                $p['state'] = 0; $p['awakeHours'] = 0;
                $p['gauges'][2] = min($p['max'][2], $p['gauges'][2] + 8);
                if (isset($p['bed'])) {
                    $room = $this->roomIdFor($p);
                    foreach ($this->rooms[$room]['cells'] as &$cell) { if ($cell === $p['bed']) { $cell--; } }
                    unset($cell, $p['bed']);
                }
            } elseif ($p['state'] === 64 && $p['ticks'] >= 29952) {
                $p['state'] = 128; $p['gauges'][0] = $p['max'][0]; $p['gauges'][1] = max(0, $p['max'][1] - 20);
            }
        }
        unset($p);
        $hour = (int)floor($this->minutes / 60);
        if ($hour !== $this->lastHour) {
            $this->lastHour = $hour;
            foreach ($this->party as &$p) {
                $p['foodHours']++; $p['awakeHours']++;
                if ($p['foodHours'] >= 7) { $p['gauges'][2] = max(0, $p['gauges'][2] - 4); }
            }
            unset($p);
        }
        if ($this->current()['prefix'][6] > 0 && $this->person()['state'] === 0) {
            $this->battleTime += $dt;
            if ($this->battleTime >= 4.0) { $this->battleTime = 0; $hit = mt_rand(3, 6); $this->damage($hit, $hit); }
            if ($this->elapsed - $this->lastStrike > 0.2) { $this->pressure = max(0, $this->pressure - $dt * 4); }
        }
        if (count(array_filter($this->party, fn ($p) => $p['state'] < 32)) === 0) { $this->ending = 'defeat'; }
    }

    public function export(): array
    {
        return ['version' => self::VERSION, 'map' => $this->map, 'rooms' => $this->rooms, 'party' => $this->party,
            'containers' => $this->containers, 'active' => $this->active, 'minutes' => $this->minutes,
            'electricity' => $this->electricity, 'fuelCan' => $this->fuelCan, 'fuelTank' => $this->fuelTank,
            'videoCassette' => $this->videoCassette, 'frozenBodies' => $this->frozenBodies,
            'raidersArrived' => $this->raidersArrived, 'zombieTarget' => $this->zombieTarget];
    }

    public function restore(array $save): bool
    {
        if (!in_array($save['version'] ?? '', ['0.2.0', '0.3.0', '0.4.0', '0.5.0', self::VERSION], true) || !is_array($save['party'] ?? null) || count($save['party']) !== 4 ||
            !is_array($save['rooms'] ?? null) || count($save['rooms']) !== 90 || !is_array($save['containers'] ?? null)) { return false; }
        $cassette = $save['videoCassette'] ?? ($save['version'] === self::VERSION ? null : 0);
        if (!in_array($cassette, [0, 50], true)) { return false; }
        $frozen = $save['frozenBodies'] ?? ($save['version'] === self::VERSION ? null : 0);
        $raiders = $save['raidersArrived'] ?? ($save['version'] === self::VERSION ? null : false);
        if (!is_int($frozen) || $frozen < 0 || $frozen > 255 || !is_bool($raiders) ||
            ($save['version'] === self::VERSION && ($save['zombieTarget'] ?? null) !== $this->zombieTarget)) { return false; }
        $map = ($save['version'] === '0.2.0') ? $this->initialMap : ($save['map'] ?? null);
        if (!is_array($map)) { return false; }
        $lift = array_keys($map, 63, true);
        if (count($lift) !== 1 || !in_array($lift[0], [2, 58, 114, 170, 226], true)) { return false; }
        $fixed = array_filter($map, fn ($id) => $id !== 63);
        $expected = array_filter($this->initialMap, fn ($id) => $id !== 63);
        ksort($fixed); ksort($expected);
        if ($fixed !== $expected) { return false; }
        foreach ($save['party'] as $p) {
            if (!is_array($p) || !isset($p['name'], $p['position'], $p['inside'], $p['state'], $p['gauges'], $p['bag'], $p['held'],
                $p['max'], $p['ticks'], $p['used'], $p['awakeHours'], $p['foodHours'], $p['trained']) ||
                !is_array($p['bag']) || count($p['bag']) !== 4 || !is_array($p['gauges']) || count($p['gauges']) !== 3 ||
                !is_array($p['max']) || count($p['max']) !== 3 ||
                ($p['inside'] !== 0 && !in_array($p['inside'], [87, 88, 89, 90], true)) || !isset($map[$p['position']])) { return false; }
            if (!is_string($p['name']) || !array_is_list($p['bag']) || !array_is_list($p['gauges']) || !array_is_list($p['max']) ||
                !is_numeric($p['ticks']) || !is_finite((float)$p['ticks']) || $p['ticks'] < 0 || !is_bool($p['trained'])) { return false; }
            foreach (['position', 'inside', 'state', 'awakeHours', 'foodHours'] as $key) {
                if (!is_int($p[$key]) || $p[$key] < 0) { return false; }
            }
            foreach (array_merge($p['bag'], [$p['held'], $p['used']]) as $item) {
                if (!is_int($item) || $item < 0 || $item > 75) { return false; }
            }
            foreach (array_merge($p['gauges'], $p['max']) as $gauge) {
                if (!is_int($gauge) || $gauge < 0 || $gauge > 255) { return false; }
            }
        }
        foreach ($this->rooms as $id => $r) {
            if (!isset($save['rooms'][$id]['prefix'], $save['rooms'][$id]['cells'], $save['rooms'][$id]['hp']) ||
                !is_array($save['rooms'][$id]['prefix']) || !is_array($save['rooms'][$id]['cells']) ||
                count($save['rooms'][$id]['prefix']) !== 10 || count($save['rooms'][$id]['cells']) !== 40) { return false; }
            if (!array_is_list($save['rooms'][$id]['prefix']) || !array_is_list($save['rooms'][$id]['cells']) ||
                !is_int($save['rooms'][$id]['hp']) || $save['rooms'][$id]['hp'] < 0) { return false; }
            foreach (array_merge($save['rooms'][$id]['prefix'], $save['rooms'][$id]['cells']) as $value) {
                if (!is_int($value) || $value < 0 || $value > 255) { return false; }
            }
        }
        foreach ($this->containers as $id => $slots) {
            if (!isset($save['containers'][$id]) || !is_array($save['containers'][$id]) || count($save['containers'][$id]) !== count($slots)) { return false; }
            foreach ($save['containers'][$id] as $item) {
                if (!is_int($item) || $item < 0 || $item > 75) { return false; }
            }
        }
        $this->rooms = $save['rooms']; $this->party = $save['party']; $this->containers = $save['containers']; $this->map = $map;
        $this->videoCassette = $cassette;
        $this->frozenBodies = $frozen; $this->raidersArrived = $raiders;
        $this->active = max(0, min(3, (int)($save['active'] ?? 3))); $this->minutes = max(0, (float)($save['minutes'] ?? 615));
        $this->electricity = (bool)($save['electricity'] ?? false); $this->fuelCan = max(0, (int)($save['fuelCan'] ?? 0));
        $this->fuelTank = max(0, (int)($save['fuelTank'] ?? 0)); $this->lastHour = (int)floor($this->minutes / 60);
        $this->pressure = 0; $this->battleTime = 0; $this->lastStrike = -100; $this->lastSide = 0;
        $this->terminalInput = ''; $this->terminalLines = [];
        $this->mode = 'normal'; $this->ending = ''; $this->message = 'PARTIE CHARGEE'; return true;
    }

    public static function itemName(int $id): string
    {
        $names = [0 => 'MAIN VIDE', 9 => 'BOITIER', 10 => 'ARMOIRE', 11 => 'ARMOIRE', 12 => 'ARMOIRE', 13 => 'PLACARD',
            22 => 'BOITIER OUVERT', 23 => 'ARMOIRE OUVERTE', 24 => 'ARMOIRE OUVERTE', 25 => 'ARMOIRE OUVERTE', 26 => 'PLACARD OUVERT',
            33 => 'SOINS', 34 => 'PISTOLET', 35 => 'PISTOLET', 36 => 'FUSIL', 37 => 'COUTEAU', 38 => 'MARTEAU', 39 => 'HACHE',
            40 => 'RADIO', 41 => 'CORDE', 42 => 'TUYAU', 43 => 'LAMPE', 44 => 'JUMELLES', 45 => 'GANTS', 46 => 'PLAN',
            47 => 'CLE HELICO', 48 => 'CLE CAMION', 49 => 'OUTIL', 50 => 'CASSETTE', 51 => 'FUSIBLES', 52 => 'BIDON', 53 => 'CADAVRE',
            54 => 'BOISSON', 55 => 'NOURRITURE', 56 => 'LIT', 57 => 'LIT OCCUPE', 58 => 'LIT', 59 => 'LIT OCCUPE',
            62 => 'INTERRUPTEUR', 63 => 'CONTACT', 64 => 'CONTACT', 67 => 'INTERRUPTEUR', 68 => 'CONTACT', 69 => 'CONTACT',
            71 => 'DEMARRER', 72 => 'DECOLLER', 73 => 'ASCENSEUR', 74 => 'ASCENSEUR', 75 => 'TERMINAL'];
        return $names[$id] ?? ($id === 255 ? 'DECOR' : ($id >= 1 && $id <= 21 ? 'PORTE / OUVERTURE' : 'EQUIPEMENT'));
    }
}
