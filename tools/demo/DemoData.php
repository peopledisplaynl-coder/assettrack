<?php
/**
 * Fictional demo data for AssetTrack: one school foundation with three schools,
 * rooms, staff, Chromebook carts, laptops, desktops, monitors, printers,
 * touchscreens, tablets and network gear.
 *
 * Everything here is invented. Names, serial numbers, MAC addresses
 * (locally administered 02:xx range) and IP addresses (10.x private range) do
 * not belong to any real school or person. The generator is deterministic
 * (fixed seed), so screenshots stay the same between runs; dates are relative
 * to "today" so warranty/replacement badges look current.
 */
final class DemoData
{
    public const ORGANISATION = 'Stichting Onderwijs Lindewaard';
    public const PASSWORD     = 'demo1234';

    /** @var array<int,array{name:string,city:string,zip:string,address:string,color:?string,prefix:string,groups:int,carts:int}> */
    public const LOCATIONS = [
        ['name' => 'OBS De Zonnewijzer', 'city' => 'Lindewaard', 'zip' => '4801 AB', 'address' => 'Zonnebloemlaan 12', 'color' => null,      'prefix' => '10.20', 'groups' => 8, 'carts' => 2],
        ['name' => 'KC Het Vlinderveld', 'city' => 'Lindewaard', 'zip' => '4803 CD', 'address' => 'Vlinderhof 3',      'color' => '#0f766e', 'prefix' => '10.30', 'groups' => 6, 'carts' => 1],
        ['name' => 'SBO De Kompasroos',  'city' => 'Eikendaal',  'zip' => '4810 EF', 'address' => 'Kompasweg 41',      'color' => '#7c3aed', 'prefix' => '10.40', 'groups' => 4, 'carts' => 1],
    ];

    /** username, full name, role, email, location indexes (empty = all) */
    public const USERS = [
        ['demo',      'Demo Beheerder',     'superadmin', 'demo@lindewaard.example',          []],
        ['r.verbeek', 'Rianne Verbeek',     'admin',      'r.verbeek@lindewaard.example',     [0, 1]],
        ['t.kuipers', 'Thijs Kuipers',      'user',       't.kuipers@lindewaard.example',     [0]],
        ['n.amrani',  'Nadia Amrani',       'user',       'n.amrani@lindewaard.example',      [1]],
        ['b.hoekstra','Bram Hoekstra',      'visitor',    'b.hoekstra@lindewaard.example',    [2]],
    ];

    private const TEACHERS = [
        'Anouk Brouwer', 'Daan Willems', 'Fleur van Dijk', 'Jesse Smits', 'Lotte Hermans',
        'Milan de Graaf', 'Noor Peeters', 'Ruben Claassen', 'Sanne Kok', 'Thomas Wouters',
        'Yara Mertens', 'Bas Verhoeven', 'Eva Schouten', 'Lars Maas', 'Iris Huisman',
        'Koen Driessen', 'Maud Bosman', 'Sem van Leeuwen', 'Tess Bakker', 'Joris Martens',
    ];

    private int $seed = 20261023;
    private int $macCounter = 0;
    private DateTimeImmutable $today;

    public function __construct(?DateTimeImmutable $today = null)
    {
        $this->today = $today ?? new DateTimeImmutable('today');
    }

    private function rand(int $min, int $max): int
    {
        // Small LCG so output does not depend on PHP's global RNG state.
        $this->seed = ($this->seed * 1103515245 + 12345) & 0x7fffffff;
        return $min + ($this->seed % ($max - $min + 1));
    }

    private function pick(array $items)
    {
        return $items[$this->rand(0, count($items) - 1)];
    }

    private function date(int $minMonthsAgo, int $maxMonthsAgo): string
    {
        $months = $this->rand($minMonthsAgo, $maxMonthsAgo);
        $days   = $this->rand(0, 27);
        return $this->today->modify("-{$months} months")->modify("-{$days} days")->format('Y-m-d');
    }

    private static function addYears(string $date, int $years): string
    {
        return (new DateTimeImmutable($date))->modify("+{$years} years")->format('Y-m-d');
    }

    private function mac(): string
    {
        $this->macCounter++;
        $n = $this->macCounter;
        return sprintf('02:5A:%02X:%02X:%02X:%02X', $this->rand(0, 255), ($n >> 8) & 0xff, $n & 0xff, $this->rand(0, 255));
    }

    private function serial(string $prefix): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $s = $prefix;
        for ($i = 0; $i < 8; $i++) {
            $s .= $chars[$this->rand(0, strlen($chars) - 1)];
        }
        return $s;
    }

    /** @return string[] room names for a location */
    public function rooms(int $locIndex): array
    {
        $loc   = self::LOCATIONS[$locIndex];
        $rooms = [];
        if ($locIndex === 2) {
            foreach (range(1, $loc['groups']) as $g) $rooms[] = "Lokaal {$g}";
        } else {
            $names = ['Groep 1/2a', 'Groep 1/2b', 'Groep 3', 'Groep 4', 'Groep 5', 'Groep 6', 'Groep 7', 'Groep 8'];
            $rooms = array_slice($names, 0, $loc['groups']);
        }
        foreach (range(1, $loc['carts']) as $c) $rooms[] = "Chromebookkar {$c}";
        return array_merge($rooms, ['Directiekamer', 'Administratie', 'Teamkamer', 'ICT-ruimte', 'Speellokaal']);
    }

    /**
     * All demo assets. Keys match the assets table; extra keys:
     *  _loc (location index), _links_to (index of a related asset in this list),
     *  _subsidy ('1'/'0' custom field), _lease (custom field text).
     * @return array<int,array<string,mixed>>
     */
    public function assets(): array
    {
        $assets = [];
        $teacherIdx = 0;
        foreach (self::LOCATIONS as $li => $loc) {
            $rooms = $this->rooms($li);
            $groupRooms = array_values(array_filter($rooms, fn($r) => str_starts_with($r, 'Groep') || str_starts_with($r, 'Lokaal')));
            $ip = fn(int $sub) => $loc['prefix'] . '.' . $sub . '.' . $this->rand(20, 240);

            // Chromebook carts
            foreach (range(1, $loc['carts']) as $c) {
                $model = $this->pick([
                    ['Acer', 'Chromebook Spin 512', 'Intel Celeron N4500', '4GB', 'https://www.acer.com'],
                    ['Lenovo', '300e Chromebook Gen 4', 'MediaTek Kompanio 520', '4GB', 'https://www.lenovo.com'],
                    ['HP', 'Fortis 11 G10 Chromebook', 'Intel N100', '8GB', 'https://www.hp.com'],
                ]);
                $purchase = $this->date(6, 60);
                $count = $li === 0 ? 30 : 24;
                for ($i = 1; $i <= $count; $i++) {
                    $status = $this->rand(1, 100);
                    $status = $status <= 82 ? 'In gebruik' : ($status <= 92 ? 'Beschikbaar' : ($status <= 97 ? 'In reparatie' : 'Buiten gebruik'));
                    $assets[] = [
                        '_loc' => $li, 'room' => "Chromebookkar {$c}", 'brand' => $model[0], 'model' => $model[1],
                        'type' => 'Chromebook', 'serial_number' => $this->serial('CB'), 'status' => $status,
                        'assigned_to' => sprintf('Kar %d - nr %02d', $c, $i),
                        'purchase_date' => $purchase, 'warranty_end_date' => self::addYears($purchase, 3),
                        'depreciation_years' => 4, 'autoupdate_expiry' => self::addYears($purchase, 8),
                        'installed_date' => $purchase, 'operating_system' => 'ChromeOS',
                        'cpu' => $model[2], 'ram' => $model[3], 'mac_address' => $this->mac(),
                        'manufacturer_url' => $model[4],
                        'in_repair_since' => $status === 'In reparatie' ? $this->date(0, 1) : null,
                        'out_of_service_since' => $status === 'Buiten gebruik' ? $this->date(1, 6) : null,
                        'notes' => $status === 'In reparatie' ? 'Scherm gebarsten, opgehaald door leverancier.' : null,
                        '_subsidy' => $c === 1 ? '1' : '0',
                    ];
                }
            }

            // Teacher laptop + touchscreen per group
            foreach ($groupRooms as $room) {
                $teacher = self::TEACHERS[$teacherIdx++ % count(self::TEACHERS)];
                $purchase = $this->date(3, 70);
                $model = $this->pick([
                    ['HP', 'ProBook 440 G9', 'Intel Core i5-1235U', '16GB', 'https://www.hp.com'],
                    ['Dell', 'Latitude 3540', 'Intel Core i5-1335U', '16GB', 'https://www.dell.com'],
                    ['Lenovo', 'ThinkPad E14 Gen 5', 'AMD Ryzen 5 7530U', '16GB', 'https://www.lenovo.com'],
                ]);
                $assets[] = [
                    '_loc' => $li, 'room' => $room, 'brand' => $model[0], 'model' => $model[1], 'type' => 'Laptop',
                    'serial_number' => $this->serial('LT'), 'status' => 'In gebruik',
                    'assigned_to' => $teacher, 'most_recent_user' => $teacher,
                    'purchase_date' => $purchase, 'warranty_end_date' => self::addYears($purchase, 3),
                    'depreciation_years' => 4, 'installed_date' => $purchase,
                    'operating_system' => 'Windows 11 Education', 'cpu' => $model[2], 'ram' => $model[3],
                    'mac_address' => $this->mac(), 'lan_ip_address' => $ip(10), 'manufacturer_url' => $model[4],
                    '_lease' => $this->rand(0, 1) ? 'Leasecontract ICT-2024-' . $this->rand(10, 99) : null,
                ];
                $purchase = $this->date(12, 96);
                $assets[] = [
                    '_loc' => $li, 'room' => $room, 'brand' => $this->pick(['Prowise', 'Clevertouch', 'Samsung']),
                    'model' => $this->pick(['Touchscreen Ten G2 75"', 'UX Pro 86"', 'Flip Pro 75"']),
                    'type' => 'Touchscreen', 'serial_number' => $this->serial('TS'), 'status' => 'In gebruik',
                    'purchase_date' => $purchase, 'warranty_end_date' => self::addYears($purchase, 5),
                    'depreciation_years' => 7, 'installed_date' => $purchase,
                    'lan_ip_address' => $ip(30), 'mac_address' => $this->mac(),
                    'touchscreen_monitor_type' => '75 inch interactief', 'business_critical' => 0,
                ];
            }

            // Office: desktops with monitors
            foreach (['Directiekamer', 'Administratie', 'Administratie'] as $room) {
                $purchase = $this->date(8, 84);
                $desktopIndex = count($assets);
                $assets[] = [
                    '_loc' => $li, 'room' => $room, 'brand' => 'Dell', 'model' => 'OptiPlex 7010 Micro', 'type' => 'Desktop',
                    'serial_number' => $this->serial('DT'), 'status' => 'In gebruik',
                    'assigned_to' => $room === 'Directiekamer' ? 'Directie' : 'Administratie',
                    'purchase_date' => $purchase, 'warranty_end_date' => self::addYears($purchase, 3),
                    'depreciation_years' => 5, 'installed_date' => $purchase,
                    'operating_system' => 'Windows 11 Pro', 'cpu' => 'Intel Core i5-13500T', 'ram' => '16GB',
                    'mac_address' => $this->mac(), 'lan_ip_address' => $ip(10), 'monitor_count' => 2,
                    'business_critical' => $room === 'Administratie' ? 1 : 0, 'manufacturer_url' => 'https://www.dell.com',
                ];
                foreach ([1, 2] as $m) {
                    $assets[] = [
                        '_loc' => $li, 'room' => $room, 'brand' => 'Philips', 'model' => '24E1N1300A', 'type' => 'Monitor',
                        'serial_number' => $this->serial('MN'), 'status' => 'In gebruik',
                        'purchase_date' => $purchase, 'warranty_end_date' => self::addYears($purchase, 3),
                        'depreciation_years' => 6, '_links_to' => $desktopIndex,
                    ];
                }
            }

            // Printers, network, tablets, server
            foreach (['Teamkamer', 'Administratie'] as $room) {
                $purchase = $this->date(10, 80);
                $assets[] = [
                    '_loc' => $li, 'room' => $room, 'brand' => $this->pick(['Canon', 'Brother']),
                    'model' => $this->pick(['imageRUNNER C3326i', 'MFC-L8900CDW']), 'type' => 'Printer',
                    'serial_number' => $this->serial('PR'), 'status' => 'In gebruik',
                    'purchase_date' => $purchase, 'warranty_end_date' => self::addYears($purchase, 4),
                    'depreciation_years' => 5, 'lan_ip_address' => $ip(40), 'management_ip' => $ip(40),
                    'mac_address' => $this->mac(), 'business_critical' => 1,
                ];
            }
            $apRooms = array_slice($rooms, 0, 6);
            foreach ($apRooms as $n => $room) {
                $purchase = $this->date(20, 50);
                $assets[] = [
                    '_loc' => $li, 'room' => $room, 'brand' => 'Ubiquiti', 'model' => 'UniFi U6 Pro', 'type' => 'Netwerkapparatuur',
                    'serial_number' => $this->serial('AP'), 'status' => 'In gebruik', 'access_point_number' => sprintf('AP-%02d', $n + 1),
                    'purchase_date' => $purchase, 'warranty_end_date' => self::addYears($purchase, 2),
                    'depreciation_years' => 5, 'management_ip' => $ip(1), 'mac_address' => $this->mac(), 'business_critical' => 1,
                ];
            }
            $purchase = $this->date(24, 40);
            $assets[] = [
                '_loc' => $li, 'room' => 'ICT-ruimte', 'brand' => 'Ubiquiti', 'model' => 'UniFi Switch Pro 48 PoE', 'type' => 'Netwerkapparatuur',
                'serial_number' => $this->serial('SW'), 'status' => 'In gebruik', 'purchase_date' => $purchase,
                'warranty_end_date' => self::addYears($purchase, 2), 'depreciation_years' => 6,
                'management_ip' => $ip(1), 'mac_address' => $this->mac(), 'business_critical' => 1,
            ];
            foreach (range(1, $li === 0 ? 8 : 4) as $t) {
                $purchase = $this->date(12, 48);
                $assets[] = [
                    '_loc' => $li, 'room' => 'Groep 1/2a', 'brand' => 'Apple', 'model' => 'iPad (10e generatie)', 'type' => 'Tablet',
                    'serial_number' => $this->serial('IP'), 'status' => $t === 1 ? 'Beschikbaar' : 'In gebruik',
                    'assigned_to' => "Kleutertablet {$t}", 'purchase_date' => $purchase,
                    'warranty_end_date' => self::addYears($purchase, 1), 'depreciation_years' => 4,
                    'operating_system' => 'iPadOS', 'mac_address' => $this->mac(),
                ];
            }
            $assets[] = [
                '_loc' => $li, 'room' => 'Teamkamer', 'brand' => 'Samsung', 'model' => 'Galaxy A35', 'type' => 'Telefoon',
                'serial_number' => $this->serial('PH'), 'status' => 'In gebruik', 'assigned_to' => 'Conciërge',
                'phone_number' => '06-' . $this->rand(10000000, 99999999), 'purchase_date' => $this->date(2, 20),
                'depreciation_years' => 3,
            ];
        }
        // One old desktop that is due for replacement, to fill the replacement report.
        $assets[] = [
            '_loc' => 0, 'room' => 'Teamkamer', 'brand' => 'HP', 'model' => 'EliteDesk 800 G3', 'type' => 'Desktop',
            'serial_number' => $this->serial('DT'), 'status' => 'Buiten gebruik', 'purchase_date' => $this->date(90, 96),
            'warranty_end_date' => $this->date(54, 60), 'depreciation_years' => 5, 'out_of_service_since' => $this->date(1, 3),
            'operating_system' => 'Windows 10 Pro', 'notes' => 'Wordt afgevoerd bij de volgende inzameling.',
        ];
        return $assets;
    }

    /** CSV in the layout of the import screen (semicolon, UTF-8 with BOM). */
    public function importCsv(int $rows = 40): string
    {
        $header = ['Assetnummer', 'Merk', 'Model', 'Soort', 'Ruimte', 'Serienummer', 'Status', 'In gebruik bij',
            'Aankoopdatum', 'Garantie tot', 'Afschrijving jaren', 'Autoupdate vervalt', 'Besturingssysteem', 'RAM', 'CPU',
            'MAC adres', 'LAN IP', 'Fabrikant URL', 'Opmerking'];
        $fmt = fn(?string $d) => $d ? (new DateTimeImmutable($d))->format('d-m-Y') : '';
        $q = fn($v) => '"' . str_replace('"', '""', (string)$v) . '"';
        $lines = [implode(';', array_map($q, $header))];
        $n = 0;
        foreach ($this->assets() as $a) {
            if ($a['_loc'] !== 1 || ++$n > $rows) continue;
            $lines[] = implode(';', array_map($q, [
                sprintf('VB-%04d', $n), $a['brand'], $a['model'], $a['type'], $a['room'], $a['serial_number'] ?? '',
                $a['status'], $a['assigned_to'] ?? '', $fmt($a['purchase_date'] ?? null), $fmt($a['warranty_end_date'] ?? null),
                $a['depreciation_years'] ?? '', $fmt($a['autoupdate_expiry'] ?? null), $a['operating_system'] ?? '',
                $a['ram'] ?? '', $a['cpu'] ?? '', str_replace(':', '', $a['mac_address'] ?? ''), $a['lan_ip_address'] ?? '',
                $a['manufacturer_url'] ?? '', $a['notes'] ?? '',
            ]));
        }
        return "\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n";
    }
}
