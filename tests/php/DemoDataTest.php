<?php
require_once dirname(__DIR__, 2) . '/tools/demo/DemoData.php';

test('demo data is deterministic', function () {
    $day = new DateTimeImmutable('2026-10-23');
    assertSame((new DemoData($day))->assets(), (new DemoData($day))->assets());
});
test('demo data uses only fictional MAC (02:...) and private IP ranges', function () {
    foreach ((new DemoData())->assets() as $a) {
        if (!empty($a['mac_address'])) assertTrue(str_starts_with($a['mac_address'], '02:'), 'MAC ' . $a['mac_address']);
        foreach (['lan_ip_address', 'management_ip'] as $k) {
            if (!empty($a[$k])) assertTrue(str_starts_with($a[$k], '10.'), "IP {$a[$k]}");
        }
        assertTrue(isValidMAC($a['mac_address'] ?? '00:00:00:00:00:00'));
    }
});
test('demo import CSV has a BOM, a header and rows', function () {
    $csv = (new DemoData())->importCsv(10);
    assertSame("\xEF\xBB\xBF", substr($csv, 0, 3));
    assertSame(11, count(array_filter(explode("\r\n", $csv))));
});
