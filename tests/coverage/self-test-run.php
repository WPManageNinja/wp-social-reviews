<?php

require __DIR__ . '/fixtures/executed.php';

if (wpsrCoverageExecutedFixture(40) !== 41) {
    fwrite(STDERR, "Coverage executed fixture behavior failed.\n");
    exit(1);
}

echo "COVERAGE-FIXTURE: executed function returned 41; untouched fixture not loaded\n";
