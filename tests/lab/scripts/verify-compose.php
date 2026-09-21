<?php
/** Parse rendered Compose JSON; reject unsafe mounts, ports, networks, or service drift. */
if (PHP_SAPI !== 'cli') {
    exit(2);
}
$repo = isset($argv[1]) ? realpath($argv[1]) : false;
$mode = isset($argv[2]) ? (string) $argv[2] : 'config';
$pro = isset($argv[3]) ? realpath($argv[3]) : false;
$data = json_decode(stream_get_contents(STDIN), true);
$errors = [];
if (!$repo || !$pro || !is_array($data)) {
    fwrite(STDERR, "compose-contract: invalid repository/Pro path or JSON.\n");
    exit(1);
}
$normalize = function ($path) {
    $path = str_replace('\\', '/', (string) $path);
    // Docker Desktop reports host binds through its VM's /host_mnt prefix.
    if (strpos($path, '/host_mnt/') === 0) {
        $path = substr($path, strlen('/host_mnt'));
    }
    $real = realpath($path);
    return $real !== false ? str_replace('\\', '/', $real) : $path;
};
$repo = $normalize($repo);
$pro = $normalize($pro);

if ($mode === 'runtime') {
    foreach ($data as $container) {
        $labels = (array) ($container['Config']['Labels'] ?? []);
        $service = (string) ($labels['com.docker.compose.service'] ?? '');
        if (!in_array($service, ['db', 'wordpress'], true)) {
            continue;
        }
        $runtimeImage = $service === 'db' ? 'mariadb:11.4' : 'wordpress:php8.2-apache';
        if (($container['Config']['Image'] ?? '') !== $runtimeImage) {
            $errors[] = 'runtime ' . $service . ' image drifted from ' . $runtimeImage;
        }
        $ports = (array) ($container['HostConfig']['PortBindings'] ?? []);
        if ($service === 'wordpress') {
            $binding = $ports['80/tcp'][0] ?? null;
            if (count($ports) !== 1 || !is_array($binding)
                || ($binding['HostIp'] ?? '') !== '127.0.0.1' || ($binding['HostPort'] ?? '') !== '8892') {
                $errors[] = 'runtime WordPress must expose exactly 127.0.0.1:8892 to container port 80';
            }
        } elseif ($ports) {
            $errors[] = 'runtime ' . $service . ' exposes host ports';
        }
        $networks = (array) ($container['NetworkSettings']['Networks'] ?? []);
        if (count($networks) !== 1) {
            $errors[] = 'runtime ' . $service . ' must attach to exactly one lab network';
        } else {
            $network = reset($networks);
            if (!in_array($service, (array) ($network['Aliases'] ?? []), true)) {
                $errors[] = 'runtime ' . $service . ' lacks its exact internal DNS alias';
            }
        }
        $mounts = (array) ($container['Mounts'] ?? []);
        if ($service === 'wordpress') {
            $required = [
                '/workspace/wp-social-ninja' => $repo,
                '/var/www/html/wp-content/plugins/wp-social-ninja' => $repo,
                '/workspace/wp-social-ninja-pro' => $pro,
                '/var/www/html/wp-content/plugins/wp-social-ninja-pro' => $pro,
                '/var/www/html/wp-content/mu-plugins' => $repo . '/tests/lab/mu-plugins',
            ];
            foreach ($required as $target => $source) {
                $match = null;
                foreach ($mounts as $mount) {
                    if (($mount['Destination'] ?? '') === $target) {
                        $match = $mount;
                        break;
                    }
                }
                if (!$match || $normalize($match['Source'] ?? '') !== $source || !empty($match['RW'])) {
                    $errors[] = 'runtime mount is missing, foreign, or writable: ' . $target;
                }
            }
        }
    }
    if ($errors) {
        fwrite(STDERR, "compose-contract: FAIL\n  - " . implode("\n  - ", array_unique($errors)) . "\n");
        exit(1);
    }
    echo "compose-contract: runtime mounts and loopback-only web port enforced.\n";
    exit(0);
}

$services = (array) ($data['services'] ?? []);
$serviceNames = array_keys($services);
sort($serviceNames, SORT_STRING);
if ($serviceNames !== ['db', 'php-tests', 'wordpress']) {
    $errors[] = 'foundation must render exactly db, php-tests, wordpress services: ' . json_encode($serviceNames);
}
$expectedImages = [
    'db' => 'mariadb:11.4',
    'wordpress' => 'wordpress:php8.2-apache',
    'php-tests' => 'wp-social-ninja-php-tests:local',
];
foreach ($expectedImages as $service => $image) {
    if (($services[$service]['image'] ?? '') !== $image) {
        $errors[] = $service . ' image drifted from foundation runtime: ' . (string) ($services[$service]['image'] ?? '');
    }
}
$networkNames = array_keys((array) ($data['networks'] ?? []));
sort($networkNames, SORT_STRING);
if ($networkNames !== ['default']) {
    $errors[] = 'foundation must render exactly one network: ' . json_encode($networkNames);
}
$network = (array) ($data['networks']['default'] ?? []);
if (empty($network['internal']) || !empty($network['external'])) {
    $errors[] = 'default lab network must be internal and non-external (no egress)';
}

foreach (['db', 'wordpress', 'php-tests'] as $service) {
    $serviceNetworks = array_keys((array) ($services[$service]['networks'] ?? []));
    if ($serviceNetworks !== ['default']) {
        $errors[] = $service . ' must attach only to default internal network';
    }
    $ports = (array) ($services[$service]['ports'] ?? []);
    if ($service === 'wordpress') {
        $port = $ports[0] ?? null;
        if (count($ports) !== 1 || !is_array($port) || ($port['host_ip'] ?? '') !== '127.0.0.1'
            || (int) ($port['published'] ?? 0) !== 8892 || (int) ($port['target'] ?? 0) !== 80) {
            $errors[] = 'WordPress must expose exactly loopback 127.0.0.1:8892:80';
        }
    } elseif ($ports) {
        $errors[] = $service . ' must expose no host ports';
    }
}

$expectedDb = getenv('WPSR_LAB_DB_NAME') ?: 'wpsocialninja_test_lab';
$expectedPrefix = getenv('WPSR_LAB_TABLE_PREFIX') ?: 'wpsntest_';
foreach (['wordpress', 'php-tests'] as $service) {
    $environment = (array) ($services[$service]['environment'] ?? []);
    if (($environment['WORDPRESS_DB_HOST'] ?? '') !== 'db:3306') {
        $errors[] = $service . ' database host must be db:3306';
    }
    if (($environment['WORDPRESS_DB_NAME'] ?? '') !== $expectedDb) {
        $errors[] = $service . ' database name differs from guarded lab value';
    }
    if (($environment['WORDPRESS_TABLE_PREFIX'] ?? '') !== $expectedPrefix) {
        $errors[] = $service . ' table prefix differs from guarded lab value';
    }
    if (($environment['WPSR_TEST_LAB'] ?? '') !== '1') {
        $errors[] = $service . ' lacks WPSR_TEST_LAB=1';
    }
}
if (($services['db']['environment']['MARIADB_DATABASE'] ?? '') !== $expectedDb) {
    $errors[] = 'DB service database name differs from WordPress lab value';
}

$requiredMounts = [
    'wordpress' => [
        '/workspace/wp-social-ninja' => $repo,
        '/var/www/html/wp-content/plugins/wp-social-ninja' => $repo,
        '/workspace/wp-social-ninja-pro' => $pro,
        '/var/www/html/wp-content/plugins/wp-social-ninja-pro' => $pro,
        '/var/www/html/wp-content/mu-plugins' => $repo . '/tests/lab/mu-plugins',
    ],
    'php-tests' => [
        '/workspace/wp-social-ninja' => $repo,
        '/var/www/html/wp-content/plugins/wp-social-ninja' => $repo,
        '/workspace/wp-social-ninja-pro' => $pro,
        '/var/www/html/wp-content/plugins/wp-social-ninja-pro' => $pro,
        '/var/www/html/wp-content/mu-plugins' => $repo . '/tests/lab/mu-plugins',
    ],
];
foreach ($requiredMounts as $service => $mounts) {
    $rendered = (array) ($services[$service]['volumes'] ?? []);
    foreach ($mounts as $target => $source) {
        $match = null;
        foreach ($rendered as $mount) {
            if (($mount['target'] ?? '') === $target) {
                $match = $mount;
                break;
            }
        }
        if (!$match || ($match['type'] ?? '') !== 'bind'
            || $normalize($match['source'] ?? '') !== $source || empty($match['read_only'])) {
            $errors[] = $service . ' mount is missing, foreign, or writable: ' . $target;
        }
    }
    foreach ($rendered as $mount) {
        if (($mount['type'] ?? '') === 'bind') {
            $source = $normalize($mount['source'] ?? '');
            if ($source !== $repo && strpos($source, $repo . '/') !== 0 && $source !== $pro) {
                $errors[] = $service . ' binds unregistered path outside family: ' . $source;
            }
            if (($mount['target'] ?? '') === '/var/run/docker.sock') {
                $errors[] = $service . ' must not mount Docker socket';
            }
        }
    }
    if (!empty($services[$service]['privileged']) || !empty($services[$service]['network_mode'])
        || !empty($services[$service]['devices'])) {
        $errors[] = $service . ' requests privileged/host/device access';
    }
}
if ((string) ($services['php-tests']['user'] ?? '') !== '33:33') {
    $errors[] = 'php-tests must run as www-data 33:33';
}
if (($services['php-tests']['working_dir'] ?? '') !== '/workspace/wp-social-ninja') {
    $errors[] = 'php-tests working directory drifted';
}

if ($errors) {
    fwrite(STDERR, "compose-contract: FAIL\n  - " . implode("\n  - ", array_unique($errors)) . "\n");
    exit(1);
}
echo "compose-contract: rendered services, internal no-egress network, loopback-only web port, and read-only family mounts enforced.\n";
