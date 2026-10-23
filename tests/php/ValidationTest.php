<?php
test('isValidMAC accepts colon, dash and plain notation', function () {
    assertTrue(isValidMAC('d8:f3:bc:43:76:85'));
    assertTrue(isValidMAC('D8-F3-BC-43-76-85'));
    assertTrue(isValidMAC('d8f3bc437685'));
});
test('isValidMAC rejects wrong lengths and characters', function () {
    assertTrue(!isValidMAC('d8f3bc43768'));
    assertTrue(!isValidMAC('d8f3bc4376856'));
    assertTrue(!isValidMAC('zzzz'));
    assertTrue(!isValidMAC('d8:f3:bc:43:76'));
});
test('normalizeMAC gives AA:BB:CC:DD:EE:FF', function () {
    assertSame('D8:F3:BC:43:76:85', normalizeMAC('d8f3bc437685'));
    assertSame('D8:F3:BC:43:76:85', normalizeMAC('d8-f3-bc-43-76-85'));
    assertSame('not-a-mac', normalizeMAC('not-a-mac'));
});
test('isValidIP accepts IPv4/IPv6 and rejects garbage', function () {
    assertTrue(isValidIP('10.20.30.40'));
    assertTrue(isValidIP('fe80::1'));
    assertTrue(!isValidIP('300.1.1.1'));
});
test('formatDate formats and tolerates empty values', function () {
    assertSame('05-03-2024', formatDate('2024-03-05'));
    assertSame('', formatDate(null));
});
test('calculateAssetFields derives replacement date from purchase + depreciation', function () {
    $a = calculateAssetFields(['purchase_date' => '2022-01-15', 'depreciation_years' => 4]);
    assertSame('2026-01-15', $a['replacement_due_date']);
    assertSame('2026-01-15', $a['advised_replacement_date']);
});
test('sanitize escapes HTML', function () {
    assertSame('&lt;b&gt;x&lt;/b&gt;', sanitize(' <b>x</b> '));
});
